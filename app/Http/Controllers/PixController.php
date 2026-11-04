<?php

namespace App\Http\Controllers;

use App\Models\PixPedido;
use App\Support\CompraLivro;
use App\Support\Pix;
use App\Support\Plano;
use App\Support\Promocao;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Compra do livro do sistema por Pix direto na chave do dono: a pessoa paga o QR Code, clica em "Já paguei" e um
 * administrador confere o extrato e aprova (o que libera o plano vitalício) ou recusa (a pessoa pode tentar de novo).
 */
class PixController extends Controller
{
    /** Tela do pagamento: reaproveita o pedido em aberto ou cria um. */
    public function pagar(Request $request): View|RedirectResponse
    {
        $user = $request->user();
        if (Plano::comprouLivro($user)) {
            return redirect()->route('planos.index')->with('ok', 'Você já comprou o livro do sistema.');
        }
        if (!Pix::disponivel()) {
            return redirect()->route('planos.index')->with('limite', 'A compra por Pix ainda não está disponível. Tente novamente mais tarde.');
        }

        // um pedido por vez: clique duplo ou duas abas não criam dois pedidos
        $pedido = Cache::lock('pix-pedido:' . $user->id, 10)->block(5, function () use ($user) {
            return PixPedido::where('user_id', $user->id)->whereIn('status', PixPedido::ABERTOS)->latest('id')->first()
                ?? PixPedido::create(['user_id' => $user->id, 'codigo' => Pix::novoCodigo(), 'valor' => CompraLivro::preco(), 'status' => 'aguardando']);
        });
        // Pix ainda não pago segue o preço de agora (promoção relâmpago); quem o gerou durante a promoção ainda paga o
        // preço dela por um tempo depois do fim. updated_at marca quando o valor foi definido.
        if ($pedido->status === 'aguardando') {
            $valor = Promocao::precoDoPedido(CompraLivro::precoCheio(), (int) optional($pedido->updated_at ?? $pedido->created_at)->getTimestamp());
            if (round((float) $pedido->valor, 2) !== round($valor, 2)) {
                $pedido->forceFill(['valor' => $valor])->save();
            }
        }
        $recusado = PixPedido::where('user_id', $user->id)->where('status', 'recusado')->where('id', '<', $pedido->id)->exists()
            && $pedido->status === 'aguardando';

        return view('pix', [
            'pedido'     => $pedido,
            'copiaECola' => Pix::copiaECola((float) $pedido->valor, $pedido->codigo),
            'preco'      => 'R$ ' . number_format((float) $pedido->valor, 2, ',', '.'),
            'recusado'   => $recusado,
        ]);
    }

    /** "Já paguei": o pedido vai para a conferência do administrador. */
    public function informar(Request $request): RedirectResponse
    {
        $dados = $request->validate(['pagador' => 'nullable|string|max:80']);
        $pedido = PixPedido::where('user_id', $request->user()->id)->whereIn('status', PixPedido::ABERTOS)->latest('id')->first();
        if (!$pedido) {
            return redirect()->route('livro.pix');
        }
        $pedido->forceFill([
            'status'       => 'informado',
            'pagador'      => trim((string) ($dados['pagador'] ?? '')) ?: $pedido->pagador,
            'informado_em' => $pedido->informado_em ?? now(),
        ])->save();

        return redirect()->route('livro.pix')->with('ok', 'Recebemos seu aviso. Assim que o pagamento for conferido, o plano vitalício é ativado na sua conta.');
    }

    /** Painel do administrador: avisos de pagamento para conferir e o histórico recente. */
    public function admin(Request $request): View
    {
        abort_unless(Pix::admin($request->user()) && Pix::disponivel(), 403);

        $abertos = PixPedido::with('user')->whereIn('status', PixPedido::ABERTOS)
            ->orderByRaw("CASE WHEN status = 'informado' THEN 0 ELSE 1 END")->orderByDesc('informado_em')->orderByDesc('id')->limit(100)->get();
        $decididos = PixPedido::with('user')->whereIn('status', ['aprovado', 'recusado'])->orderByDesc('decidido_em')->limit(30)->get();

        return view('admin-pix', ['abertos' => $abertos, 'decididos' => $decididos]);
    }

    /** Aprova (libera o vitalício) ou recusa um pedido. */
    public function decidir(Request $request, int $id): RedirectResponse
    {
        abort_unless(Pix::admin($request->user()) && Pix::disponivel(), 403);
        $acao = $request->validate(['acao' => 'required|in:aprovar,recusar'])['acao'];

        $msg = DB::transaction(function () use ($id, $acao, $request) {
            $pedido = PixPedido::with('user')->lockForUpdate()->findOrFail($id);
            if (!in_array($pedido->status, PixPedido::ABERTOS, true)) {
                return "O pedido {$pedido->codigo} já estava {$pedido->status}.";
            }
            $pedido->forceFill(['status' => $acao === 'aprovar' ? 'aprovado' : 'recusado', 'decidido_em' => now(), 'decidido_por' => $request->user()->id])->save();
            if ($acao === 'aprovar' && $pedido->user) {
                CompraLivro::liberar($pedido->user, 'pix:' . $pedido->codigo);
            }

            return $acao === 'aprovar'
                ? "Pedido {$pedido->codigo} aprovado: plano vitalício ativado para " . ($pedido->user->name ?? 'a conta') . '.'
                : "Pedido {$pedido->codigo} recusado.";
        });

        return redirect()->route('admin.pix')->with('ok', $msg);
    }
}

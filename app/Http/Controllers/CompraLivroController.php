<?php

namespace App\Http\Controllers;

use App\Support\AsaasCliente;
use App\Support\CompraLivro;
use App\Support\Documento;
use App\Support\Plano;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;

/** Compra do info-produto do livro do sistema: cobrança única no Asaas; o pagamento libera o plano vitalício. */
class CompraLivroController extends Controller
{
    public function comprar(Request $request, AsaasCliente $asaas): RedirectResponse
    {
        $user = $request->user();
        if (Plano::comprouLivro($user)) {
            return redirect()->route('planos.index')->with('ok', 'Você já comprou o livro do sistema.');
        }
        if (!CompraLivro::disponivel()) {
            return redirect()->route('planos.index')->with('limite', 'A compra ainda não está disponível. Tente novamente mais tarde.');
        }

        // O Asaas exige CPF/CNPJ válido: sem ele, pede na tela de dados e volta aqui
        $documento = Documento::limpar($user->cpf_cnpj);
        if (!Documento::valido($documento)) {
            return redirect()->route('livro.compra.dados');
        }

        // um pedido por vez: clique duplo ou duas abas não criam duas cobranças
        $trava = Cache::lock('compra-livro:' . $user->id, 30);
        if (!$trava->get()) {
            return redirect()->route('planos.index')->with('limite', 'Seu pagamento já está sendo aberto. Aguarde alguns segundos e tente de novo.');
        }

        try {
            $user->refresh(); // a cobrança pode ter sido criada pelo pedido anterior
            // Já tem uma cobrança deste livro? Paga: libera. Em aberto: reaproveita a mesma fatura (não cria outra).
            if (!empty($user->asaas_cobranca_id)) {
                $anterior = $asaas->buscarCobranca($user->asaas_cobranca_id);
                if ($anterior && ($anterior['externalReference'] ?? '') === CompraLivro::referencia($user)) {
                    if (in_array($anterior['status'] ?? '', AsaasCliente::PAGOS, true)) {
                        CompraLivro::liberar($user, $anterior['id']);

                        return redirect()->route('planos.index')->with('ok', 'Pagamento confirmado: plano vitalício ativado.');
                    }
                    if (in_array($anterior['status'] ?? '', ['PENDING', 'OVERDUE'], true) && !empty($anterior['invoiceUrl'])
                        && (float) ($anterior['value'] ?? 0) === round(CompraLivro::preco(), 2)) {
                        return redirect()->away($anterior['invoiceUrl']);
                    }
                }
            }

            $cliente  = $asaas->cliente($user->name, $user->email, $documento);
            $cobranca = $asaas->cobranca($cliente, CompraLivro::preco(), 'Praga Vermelha RPG · Livro do sistema (info-produto)', CompraLivro::referencia($user));
            $user->forceFill(['asaas_cobranca_id' => $cobranca['id']])->save();
        } catch (\Throwable $e) {
            report($e); // o detalhe vai para o log; a pessoa vê uma mensagem genérica

            return redirect()->route('planos.index')->with('limite', 'Não foi possível abrir o pagamento agora. Tente novamente em alguns minutos.');
        } finally {
            $trava->release();
        }

        if (empty($cobranca['invoiceUrl'])) {
            return redirect()->route('planos.index')->with('limite', 'A cobrança ainda está sendo gerada. Tente novamente em instantes.');
        }

        return redirect()->away($cobranca['invoiceUrl']);
    }

    /** Tela que pede o CPF/CNPJ antes de abrir o pagamento. */
    public function dados(Request $request): View|RedirectResponse
    {
        if (Plano::comprouLivro($request->user()) || !CompraLivro::disponivel()) {
            return redirect()->route('planos.index');
        }

        return view('compra-dados', ['documento' => (string) $request->user()->cpf_cnpj, 'preco' => CompraLivro::precoTexto()]);
    }

    /** Guarda o CPF/CNPJ e segue direto para o pagamento. */
    public function salvarDados(Request $request, AsaasCliente $asaas): RedirectResponse
    {
        $request->validate([
            'cpf_cnpj' => ['required', 'string', 'max:20', function ($atributo, $valor, $falhar) {
                if (!Documento::valido($valor)) {
                    $falhar('Informe um CPF ou CNPJ válido.');
                }
            }],
        ], ['cpf_cnpj.required' => 'Informe seu CPF ou CNPJ.']);

        $request->user()->forceFill(['cpf_cnpj' => Documento::limpar($request->input('cpf_cnpj'))])->save();

        return $this->comprar($request, $asaas);
    }

    /**
     * Webhook do Asaas (sem login e sem CSRF; a autenticação é o token no header "asaas-access-token").
     * O aviso só diz qual cobrança mudou: o status e a referência são conferidos direto na API do Asaas, então um
     * aviso forjado não libera nada. Se a conferência falhar, responde erro para o Asaas reenviar depois.
     */
    public function webhook(Request $request, AsaasCliente $asaas): JsonResponse
    {
        $esperado = (string) config('asaas.webhook_token');
        if ($esperado === '' || !hash_equals($esperado, (string) $request->header('asaas-access-token'))) {
            return response()->json(['message' => 'Token inválido.'], 401);
        }

        $id = (string) $request->input('payment.id', '');
        if ($id === '' || !CompraLivro::usuarioDaReferencia((string) $request->input('payment.externalReference', ''))) {
            return response()->json(['ok' => true]); // não é cobrança do livro (ou evento sem pagamento): nada a fazer
        }
        if (!CompraLivro::temColunas()) {
            return response()->json(['message' => 'Banco sem as colunas de planos.'], 503); // o Asaas reenvia quando estiver pronto
        }

        try {
            $cobranca = $asaas->buscarCobranca($id);
        } catch (\Throwable $e) {
            report($e);

            return response()->json(['message' => 'Não deu para conferir a cobrança agora.'], 503);
        }
        $user = CompraLivro::usuarioDaReferencia($cobranca['externalReference'] ?? null);
        // Só libera; estorno ou cancelamento depois não tiram o plano de quem comprou
        if ($cobranca && $user && in_array($cobranca['status'] ?? '', AsaasCliente::PAGOS, true)) {
            CompraLivro::liberar($user, $cobranca['id'] ?? $id);
        }

        return response()->json(['ok' => true]);
    }
}

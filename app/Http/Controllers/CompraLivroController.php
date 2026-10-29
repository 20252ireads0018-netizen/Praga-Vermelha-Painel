<?php

namespace App\Http\Controllers;

use App\Support\AsaasCliente;
use App\Support\CompraLivro;
use App\Support\Documento;
use App\Support\Plano;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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

        try {
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
            report($e);

            return redirect()->route('planos.index')->with('limite', 'Não foi possível abrir o pagamento agora: ' . $e->getMessage());
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

    /** Webhook do Asaas (sem login e sem CSRF; a autenticação é o token no header "asaas-access-token"). */
    public function webhook(Request $request): JsonResponse
    {
        $esperado = (string) config('asaas.webhook_token');
        if ($esperado === '' || !hash_equals($esperado, (string) $request->header('asaas-access-token'))) {
            return response()->json(['message' => 'Token inválido.'], 401);
        }

        try {
            $evento    = (string) $request->input('event');
            $pagamento = (array) $request->input('payment', []);
            $pago      = in_array($evento, ['PAYMENT_CONFIRMED', 'PAYMENT_RECEIVED'], true)
                || in_array($pagamento['status'] ?? '', AsaasCliente::PAGOS, true);
            $user      = CompraLivro::usuarioDaReferencia($pagamento['externalReference'] ?? null);

            // Só libera; estorno ou cancelamento depois não tiram o plano de quem comprou
            if ($pago && $user && CompraLivro::temColunas()) {
                CompraLivro::liberar($user, $pagamento['id'] ?? null);
            }
        } catch (\Throwable $e) {
            report($e); // responde 200 mesmo assim para o Asaas não pausar a fila de webhooks
        }

        return response()->json(['ok' => true]);
    }
}

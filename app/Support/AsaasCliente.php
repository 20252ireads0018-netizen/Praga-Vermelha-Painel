<?php

namespace App\Support;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/** Cliente mínimo da API v3 do Asaas (sandbox ou produção, conforme ASAAS_ENV). */
class AsaasCliente
{
    /** Status de cobrança que contam como pago. */
    public const PAGOS = ['CONFIRMED', 'RECEIVED', 'RECEIVED_IN_CASH'];

    public static function configurado(): bool
    {
        return (string) config('asaas.api_key') !== '';
    }

    private function http(): PendingRequest
    {
        $chave = (string) config('asaas.api_key');
        if ($chave === '') {
            throw new RuntimeException('ASAAS_API_KEY não configurada no .env.');
        }

        $base = config('asaas.environment') === 'production'
            ? config('asaas.urls.production', 'https://api.asaas.com/v3')
            : config('asaas.urls.sandbox', 'https://api-sandbox.asaas.com/v3');

        return Http::baseUrl($base)
            ->withHeaders(['access_token' => $chave, 'User-Agent' => 'PragaVermelhaRPG'])
            ->acceptJson()
            ->asJson()
            ->timeout(20);
    }

    private function resposta(Response $r): array
    {
        if ($r->failed()) {
            throw new RuntimeException($r->json('errors.0.description') ?: ('Asaas respondeu ' . $r->status()));
        }

        return $r->json() ?? [];
    }

    /** Reaproveita o cliente com o mesmo e-mail ou cria um novo; completa o CPF/CNPJ se faltar. Retorna o id (cus_...). */
    public function cliente(string $nome, string $email, string $cpfCnpj): string
    {
        $existente = $this->resposta($this->http()->get('/customers', ['email' => $email, 'limit' => 1]))['data'][0] ?? null;

        if (!empty($existente['id'])) {
            if (empty($existente['cpfCnpj'])) {
                $this->resposta($this->http()->post("/customers/{$existente['id']}", ['cpfCnpj' => $cpfCnpj]));
            }

            return $existente['id'];
        }

        return $this->resposta($this->http()->post('/customers', [
            'name' => $nome, 'email' => $email, 'cpfCnpj' => $cpfCnpj,
        ]))['id'];
    }

    /** Cobrança avulsa; a pessoa escolhe Pix, boleto ou cartão na página da fatura (invoiceUrl). */
    public function cobranca(string $clienteId, float $valor, string $descricao, string $referencia): array
    {
        return $this->resposta($this->http()->post('/payments', [
            'customer'          => $clienteId,
            'billingType'       => 'UNDEFINED',
            'value'             => round($valor, 2),
            'dueDate'           => now()->addDays(3)->toDateString(),
            'description'       => $descricao,
            'externalReference' => $referencia,
        ]));
    }

    /** Dados de uma cobrança (status, invoiceUrl, externalReference...). null se ela não existe mais. */
    public function buscarCobranca(string $id): ?array
    {
        $r = $this->http()->get("/payments/{$id}");
        if ($r->status() === 404) {
            return null;
        }
        $dados = $this->resposta($r);

        return empty($dados['deleted']) ? $dados : null;
    }
}

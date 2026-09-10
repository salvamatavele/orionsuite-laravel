<?php

namespace OrionSuite\Payments;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use OrionSuite\Enums\PaymentStatus;
use OrionSuite\Support\PhoneNormalizer;

class PagarClient
{
    protected string $baseUrl;

    public function __construct(
        protected string $apiKey,
        protected string $signingSecret,
        protected ?string $webhookSecret = null,
        string $baseUrl = 'https://api.pagar.co.mz/api/v1',
        protected float $minAmount = 20.0,
        protected float $maxAmount = 40000.0,
        protected string $currency = 'MZN',
        protected bool $enabled = true,
        protected string $disabledMessage = 'Os pagamentos via Pagar encontram-se temporariamente suspensos para manutenção.',
    ) {
        $clean = rtrim($baseUrl, '/');
        if (! str_ends_with($clean, '/api/v1')) {
            $clean .= '/api/v1';
        }
        $this->baseUrl = $clean;
    }

    public static function fromConfig(array $config): self
    {
        return new self(
            apiKey: (string) ($config['api_key'] ?? ''),
            signingSecret: (string) ($config['signing_secret'] ?? ''),
            webhookSecret: $config['webhook_secret'] ?? null,
            baseUrl: (string) ($config['base_url'] ?? 'https://api.pagar.co.mz/api/v1'),
            minAmount: (float) ($config['min_amount'] ?? 20.0),
            maxAmount: (float) ($config['max_amount'] ?? 40000.0),
            currency: (string) ($config['currency'] ?? 'MZN'),
            enabled: (bool) ($config['enabled'] ?? true),
            disabledMessage: (string) ($config['disabled_message'] ?? 'Os pagamentos via Pagar encontram-se temporariamente suspensos para manutenção.'),
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Limites Dinâmicos e Kill-Switch
    |--------------------------------------------------------------------------
    */
    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function setEnabled(bool $enabled): self
    {
        $this->enabled = $enabled;

        return $this;
    }

    public function enable(): self
    {
        return $this->setEnabled(true);
    }

    public function disable(): self
    {
        return $this->setEnabled(false);
    }

    public function getMinAmount(): float
    {
        return $this->minAmount;
    }

    public function setMinAmount(float $min): self
    {
        $this->minAmount = $min;

        return $this;
    }

    public function getMaxAmount(): float
    {
        return $this->maxAmount;
    }

    public function setMaxAmount(float $max): self
    {
        $this->maxAmount = $max;

        return $this;
    }

    /**
     * Resolve URL final e Canonical Path para HMAC
     */
    protected function resolveUrl(string $path): array
    {
        $cleanPath = '/'.ltrim(str_starts_with($path, '/api/v1') ? substr($path, 7) : $path, '/');
        $canonicalPath = '/api/v1'.$cleanPath;
        $url = $this->baseUrl.$cleanPath;

        return [$url, $canonicalPath];
    }

    /**
     * Assinatura Canonical HMAC-SHA256 exigida pela API Pagar
     */
    public function generateSignatureHeaders(string $path, array $body, ?string $idempotencyKey = null): array
    {
        $timestamp = (string) round(microtime(true) * 1000);
        $nonce = Str::random(24);
        $rawBody = json_encode($body, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $bodyHash = hash('sha256', $rawBody);

        [, $canonicalPath] = $this->resolveUrl($path);

        $canonical = implode("\n", [
            $timestamp,
            $nonce,
            'POST',
            $canonicalPath,
            $bodyHash,
        ]);

        $signature = hash_hmac('sha256', $canonical, $this->signingSecret);

        $headers = [
            'Authorization' => 'Bearer '.$this->apiKey,
            'Content-Type' => 'application/json',
            'X-Pagar-Timestamp' => $timestamp,
            'X-Pagar-Nonce' => $nonce,
            'X-Pagar-Signature' => 'v1='.$signature,
        ];

        if ($idempotencyKey) {
            $headers['Idempotency-Key'] = $idempotencyKey;
        }

        return [$headers, $rawBody];
    }

    /**
     * Cobrança C2B (M-Pesa / e-Mola)
     */
    public function createPayment(array $data): array
    {
        if (! $this->isEnabled()) {
            Log::info('Pagar C2B skipped: gateway is disabled.', ['reference' => $data['reference'] ?? null]);

            return [
                'success' => false,
                'status' => PaymentStatus::Failed->value,
                'code' => 'GATEWAY_DISABLED',
                'message' => $this->disabledMessage,
                'raw' => ['disabled' => true],
            ];
        }

        $amount = (float) ($data['amountMzn'] ?? $data['amount'] ?? 0);
        if ($amount < $this->minAmount || $amount > $this->maxAmount) {
            return [
                'success' => false,
                'status' => PaymentStatus::Failed->value,
                'code' => 'AMOUNT_OUT_OF_BOUNDS',
                'message' => "O valor deve estar entre {$this->minAmount} MZN e {$this->maxAmount} MZN.",
            ];
        }

        $phone = PhoneNormalizer::normalize($data['payerPhone'] ?? $data['phone'] ?? '');
        $method = strtoupper((string) ($data['method'] ?? PhoneNormalizer::detectPaymentMethod($phone) ?? 'MPESA'));

        $path = '/payments';
        $body = [
            'reference' => (string) ($data['reference'] ?? 'REF-'.Str::random(10)),
            'title' => (string) ($data['title'] ?? 'Pagamento'),
            'description' => $data['description'] ?? null,
            'amountMzn' => (int) round($amount),
            'method' => $method,
            'payerPhone' => $phone,
        ];

        $idempotencyKey = $data['idempotencyKey'] ?? 'pay:'.$body['reference'];
        [$headers, $rawBody] = $this->generateSignatureHeaders($path, $body, $idempotencyKey);
        [$url] = $this->resolveUrl($path);

        try {
            $response = Http::withHeaders($headers)
                ->withBody($rawBody, 'application/json')
                ->timeout(30)
                ->post($url);

            $json = $response->json() ?? [];

            if ($response->status() === 202 || $response->successful()) {
                $payment = $json['payment'] ?? [];

                return [
                    'success' => true,
                    'paymentId' => $payment['id'] ?? null,
                    'status' => $payment['status'] ?? PaymentStatus::Processing->value,
                    'reference' => $payment['reference'] ?? $body['reference'],
                    'message' => 'Pedido de pagamento enviado com sucesso. Aguardando confirmação no telemóvel.',
                    'raw' => $json,
                ];
            }

            return [
                'success' => false,
                'status' => PaymentStatus::Failed->value,
                'message' => $json['message'] ?? 'Falha ao processar pagamento na Pagar API.',
                'raw' => $json,
            ];
        } catch (\Throwable $e) {
            Log::error('Pagar createPayment exception', ['error' => $e->getMessage()]);

            return [
                'success' => false,
                'status' => PaymentStatus::Failed->value,
                'message' => 'Erro na comunicação com a API Pagar.',
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Carregamento de Carteira (Top-up)
     */
    public function createTopup(array $data): array
    {
        if (! $this->isEnabled()) {
            return [
                'success' => false,
                'message' => $this->disabledMessage,
            ];
        }

        $phone = PhoneNormalizer::normalize($data['paymentPhone'] ?? $data['phone'] ?? '');
        $path = '/wallet/topups';
        $body = [
            'reference' => (string) ($data['reference'] ?? 'TOPUP-'.Str::random(10)),
            'amountMzn' => (int) round($data['amountMzn'] ?? $data['amount'] ?? 0),
            'method' => strtoupper($data['method'] ?? 'MPESA'),
            'paymentPhone' => $phone,
        ];

        $idempotencyKey = $data['idempotencyKey'] ?? 'topup:'.$body['reference'];
        [$headers, $rawBody] = $this->generateSignatureHeaders($path, $body, $idempotencyKey);
        [$url] = $this->resolveUrl($path);

        $response = Http::withHeaders($headers)
            ->withBody($rawBody, 'application/json')
            ->timeout(30)
            ->post($url);

        return $response->json() ?? [];
    }

    /**
     * Consulta de Top-up por Reference
     */
    public function getTopupByReference(string $reference): ?array
    {
        [$url] = $this->resolveUrl('/wallet/topups/by-reference/'.$reference);
        $response = Http::withHeaders(['Authorization' => 'Bearer '.$this->apiKey])
            ->timeout(15)
            ->get($url);

        return $response->successful() ? ($response->json()['topup'] ?? null) : null;
    }

    /**
     * Consulta de pagamento por ID ou Reference
     */
    public function getPayment(string $paymentId): ?array
    {
        [$url] = $this->resolveUrl('/payments/'.$paymentId);
        $response = Http::withHeaders(['Authorization' => 'Bearer '.$this->apiKey])
            ->timeout(15)
            ->get($url);

        return $response->successful() ? ($response->json()['payment'] ?? null) : null;
    }

    public function getPaymentByReference(string $reference): ?array
    {
        [$url] = $this->resolveUrl('/payments/by-reference/'.$reference);
        $response = Http::withHeaders(['Authorization' => 'Bearer '.$this->apiKey])
            ->timeout(15)
            ->get($url);

        return $response->successful() ? ($response->json()['payment'] ?? null) : null;
    }

    /**
     * Listagem paginada de pagamentos
     */
    public function listPayments(array $query = []): array
    {
        [$url] = $this->resolveUrl('/payments');
        $response = Http::withHeaders(['Authorization' => 'Bearer '.$this->apiKey])
            ->timeout(15)
            ->get($url, $query);

        return $response->json() ?? [];
    }

    /**
     * Consulta saldo da Carteira
     */
    public function getWallet(): ?array
    {
        [$url] = $this->resolveUrl('/wallet');
        $response = Http::withHeaders(['Authorization' => 'Bearer '.$this->apiKey])
            ->timeout(15)
            ->get($url);

        return $response->successful() ? ($response->json()['wallet'] ?? null) : null;
    }

    /**
     * Listagem paginada de transações da carteira
     */
    public function listTransactions(array $query = []): array
    {
        [$url] = $this->resolveUrl('/wallet/transactions');
        $response = Http::withHeaders(['Authorization' => 'Bearer '.$this->apiKey])
            ->timeout(15)
            ->get($url, $query);

        return $response->json() ?? [];
    }

    /**
     * Payout B2C (Envio de fundos)
     */
    public function createPayout(array $data): array
    {
        if (! $this->isEnabled()) {
            return [
                'success' => false,
                'message' => $this->disabledMessage,
            ];
        }

        $path = '/payouts';
        $body = [
            'reference' => (string) ($data['reference'] ?? 'PO-'.Str::random(10)),
            'description' => $data['description'] ?? 'Payout',
            'amountMzn' => (int) round($data['amountMzn'] ?? $data['amount']),
            'method' => strtoupper($data['method'] ?? 'MPESA'),
            'recipient' => [
                'phone' => PhoneNormalizer::normalize($data['recipientPhone'] ?? $data['phone'] ?? ''),
                'name' => $data['recipientName'] ?? $data['name'] ?? 'Destinatário',
            ],
        ];

        $idempotencyKey = $data['idempotencyKey'] ?? 'payout:'.$body['reference'];
        [$headers, $rawBody] = $this->generateSignatureHeaders($path, $body, $idempotencyKey);
        [$url] = $this->resolveUrl($path);

        $response = Http::withHeaders($headers)
            ->withBody($rawBody, 'application/json')
            ->timeout(30)
            ->post($url);

        return $response->json() ?? [];
    }

    /**
     * Listagem paginada de payouts
     */
    public function listPayouts(array $query = []): array
    {
        [$url] = $this->resolveUrl('/payouts');
        $response = Http::withHeaders(['Authorization' => 'Bearer '.$this->apiKey])
            ->timeout(15)
            ->get($url, $query);

        return $response->json() ?? [];
    }

    /**
     * Validação segura de Webhook da Pagar em tempo constante
     */
    public function validateWebhook(string $rawBody, ?string $signatureHeader, ?string $eventId = null): bool
    {
        if (empty($signatureHeader) || empty($this->webhookSecret)) {
            return false;
        }

        $parts = [];
        foreach (explode(',', $signatureHeader) as $part) {
            $kv = explode('=', trim($part), 2);
            if (count($kv) === 2) {
                $parts[$kv[0]] = $kv[1];
            }
        }

        $timestamp = $parts['t'] ?? null;
        $received = $parts['v1'] ?? null;

        if (! $timestamp || ! $received || ! ctype_digit($timestamp)) {
            return false;
        }

        // Tolerância de 300 segundos (5 minutos) contra ataques de repetição
        if (abs(time() - (int) $timestamp) > 300) {
            return false;
        }

        $expected = hash_hmac('sha256', $timestamp.'.'.$rawBody, $this->webhookSecret);

        return hash_equals($expected, $received);
    }
}

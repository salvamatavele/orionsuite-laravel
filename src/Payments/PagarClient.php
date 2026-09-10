<?php

namespace OrionSuite\Payments;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use OrionSuite\Enums\PaymentMethod;
use OrionSuite\Enums\PaymentStatus;
use OrionSuite\Support\PhoneNormalizer;

class PagarClient
{
    public function __construct(
        protected string $apiKey,
        protected string $signingSecret,
        protected ?string $webhookSecret = null,
        protected string $baseUrl = 'https://api.pagar.co.mz',
        protected float $minAmount = 20.0,
        protected float $maxAmount = 40000.0,
        protected string $currency = 'MZN',
        protected bool $enabled = true,
        protected string $disabledMessage = 'Os pagamentos via Pagar encontram-se temporariamente suspensos para manutenção.',
    ) {}

    public static function fromConfig(array $config): self
    {
        return new self(
            apiKey: (string) ($config['api_key'] ?? ''),
            signingSecret: (string) ($config['signing_secret'] ?? ''),
            webhookSecret: $config['webhook_secret'] ?? null,
            baseUrl: (string) ($config['base_url'] ?? 'https://api.pagar.co.mz'),
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
    public function isEnabled(): bool { return $this->enabled; }
    public function setEnabled(bool $enabled): self { $this->enabled = $enabled; return $this; }
    public function enable(): self { return $this->setEnabled(true); }
    public function disable(): self { return $this->setEnabled(false); }

    public function getMinAmount(): float { return $this->minAmount; }
    public function setMinAmount(float $min): self { $this->minAmount = $min; return $this; }

    public function getMaxAmount(): float { return $this->maxAmount; }
    public function setMaxAmount(float $max): self { $this->maxAmount = $max; return $this; }

    /**
     * Assinatura Canonical HMAC-SHA256 exigida pela API Pagar
     */
    public function generateSignatureHeaders(string $path, array $body, ?string $idempotencyKey = null): array
    {
        $timestamp = (string) round(microtime(true) * 1000);
        $nonce = Str::random(24);
        $rawBody = json_encode($body, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $bodyHash = hash('sha256', $rawBody);

        $canonical = implode("\n", [
            $timestamp,
            $nonce,
            'POST',
            $path,
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

        $path = '/api/v1/payments';
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

        try {
            $response = Http::withHeaders($headers)
                ->withBody($rawBody, 'application/json')
                ->timeout(30)
                ->post($this->baseUrl.$path);

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
     * Consulta de pagamento por ID ou Reference
     */
    public function getPayment(string $paymentId): ?array
    {
        $response = Http::withHeaders(['Authorization' => 'Bearer '.$this->apiKey])
            ->timeout(15)
            ->get($this->baseUrl.'/payments/'.$paymentId);

        return $response->successful() ? ($response->json()['payment'] ?? null) : null;
    }

    public function getPaymentByReference(string $reference): ?array
    {
        $response = Http::withHeaders(['Authorization' => 'Bearer '.$this->apiKey])
            ->timeout(15)
            ->get($this->baseUrl.'/payments/by-reference/'.$reference);

        return $response->successful() ? ($response->json()['payment'] ?? null) : null;
    }

    /**
     * Consulta saldo da Carteira
     */
    public function getWallet(): ?array
    {
        $response = Http::withHeaders(['Authorization' => 'Bearer '.$this->apiKey])
            ->timeout(15)
            ->get($this->baseUrl.'/wallet');

        return $response->successful() ? ($response->json()['wallet'] ?? null) : null;
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

        $path = '/api/v1/payouts';
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

        $response = Http::withHeaders($headers)
            ->withBody($rawBody, 'application/json')
            ->timeout(30)
            ->post($this->baseUrl.$path);

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

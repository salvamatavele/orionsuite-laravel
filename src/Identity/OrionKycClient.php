<?php

namespace OrionSuite\Identity;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class OrionKycClient
{
    public function __construct(
        protected ?string $token = null,
        protected string $baseUrl = 'https://api.ocr.orioncodetech.com/v1',
        protected string $environment = 'test',
        protected bool $enabled = true,
        protected string $disabledMessage = 'A verificação de identidade encontra-se temporariamente indisponível.',
    ) {}

    public static function fromConfig(array $config): self
    {
        return new self(
            token: $config['token'] ?? null,
            baseUrl: (string) ($config['base_url'] ?? 'https://api.ocr.orioncodetech.com/v1'),
            environment: (string) ($config['environment'] ?? 'test'),
            enabled: (bool) ($config['enabled'] ?? true),
            disabledMessage: (string) ($config['disabled_message'] ?? 'A verificação de identidade encontra-se temporariamente indisponível.'),
        );
    }

    public function isEnabled(): bool { return $this->enabled; }
    public function setEnabled(bool $enabled): self { $this->enabled = $enabled; return $this; }
    public function enable(): self { return $this->setEnabled(true); }
    public function disable(): self { return $this->setEnabled(false); }

    /**
     * Gera token sandbox gratuito para testes
     */
    public function generateTestToken(string $companyName, string $nuit, string $contactEmail, ?string $phone = null): array
    {
        $response = Http::timeout(20)
            ->post($this->baseUrl.'/public/test-token', [
                'company_name' => $companyName,
                'nuit' => $nuit,
                'contact_email' => $contactEmail,
                'contact_phone' => $phone,
            ]);

        $json = $response->json() ?? [];
        if (! empty($json['token'])) {
            $this->token = $json['token'];
        }

        return $json;
    }

    /**
     * Validação do Token e da Empresa
     */
    public function getMe(): ?array
    {
        $response = Http::withHeaders(['Authorization' => 'Bearer '.$this->token])
            ->timeout(15)
            ->get($this->baseUrl.'/me');

        return $response->successful() ? $response->json() : null;
    }

    /**
     * Criação de Verificação KYC Completa (Frente BI, Verso BI, Selfie)
     * $documentFront, $documentBack e $selfie podem ser caminhos de arquivo locais ou strings binárias.
     */
    public function verifyIdentity(
        string $documentFrontPath,
        string $documentBackPath,
        string $selfiePath,
        ?string $externalReference = null
    ): array {
        if (! $this->isEnabled()) {
            Log::info('Orion KYC skipped: service is disabled.', ['external_reference' => $externalReference]);
            return [
                'status' => 'disabled',
                'decision' => 'pending_review',
                'message' => $this->disabledMessage,
                'disabled' => true,
            ];
        }

        $request = Http::withHeaders([
            'Authorization' => 'Bearer '.$this->token,
        ])->timeout(60);

        // Anexar ficheiros multipart
        $request->attach('document_front', file_get_contents($documentFrontPath), basename($documentFrontPath))
            ->attach('document_back', file_get_contents($documentBackPath), basename($documentBackPath))
            ->attach('selfie', file_get_contents($selfiePath), basename($selfiePath));

        $data = [];
        if ($externalReference) {
            $data['external_reference'] = $externalReference;
        }

        $response = $request->post($this->baseUrl.'/kyc/verifications', $data);

        return $response->json() ?? [];
    }

    /**
     * Consulta estado de uma verificação
     */
    public function getVerification(string $verificationId): ?array
    {
        $response = Http::withHeaders(['Authorization' => 'Bearer '.$this->token])
            ->timeout(15)
            ->get($this->baseUrl.'/kyc/verifications/'.$verificationId);

        return $response->successful() ? $response->json() : null;
    }

    /**
     * Relatório com trilha de auditoria
     */
    public function getVerificationReport(string $verificationId): ?array
    {
        $response = Http::withHeaders(['Authorization' => 'Bearer '.$this->token])
            ->timeout(15)
            ->get($this->baseUrl.'/kyc/verifications/'.$verificationId.'/report');

        return $response->successful() ? $response->json() : null;
    }
}

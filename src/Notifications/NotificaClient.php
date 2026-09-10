<?php

namespace OrionSuite\Notifications;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use OrionSuite\Support\PhoneNormalizer;

class NotificaClient
{
    public function __construct(
        protected string $apiToken,
        protected string $baseUrl = 'https://api.notifica.co.mz/api/v1',
        protected string $defaultSender = 'ORIONCODE',
        protected bool $enabled = true,
        protected string $disabledMessage = 'O serviço de notificações encontra-se temporariamente indisponível.',
    ) {}

    public static function fromConfig(array $config): self
    {
        return new self(
            apiToken: (string) ($config['api_token'] ?? ''),
            baseUrl: (string) ($config['base_url'] ?? 'https://api.notifica.co.mz/api/v1'),
            defaultSender: (string) ($config['default_sms_sender'] ?? 'ORIONCODE'),
            enabled: (bool) ($config['enabled'] ?? true),
            disabledMessage: (string) ($config['disabled_message'] ?? 'O serviço de notificações encontra-se temporariamente indisponível.'),
        );
    }

    public function isEnabled(): bool { return $this->enabled; }
    public function setEnabled(bool $enabled): self { $this->enabled = $enabled; return $this; }
    public function enable(): self { return $this->setEnabled(true); }
    public function disable(): self { return $this->setEnabled(false); }

    /**
     * Envio de SMS (Nacional e Internacional)
     */
    public function sendSms(string $to, string $message, ?string $sender = null): array
    {
        if (! $this->isEnabled()) {
            Log::info('Notifica SMS skipped: service is disabled.', ['to' => $to]);
            return ['success' => false, 'message' => $this->disabledMessage, 'disabled' => true];
        }

        $phone = PhoneNormalizer::normalizeInternational($to);

        $response = Http::withHeaders([
            'Authorization' => 'Bearer '.$this->apiToken,
            'Content-Type' => 'application/json',
        ])
            ->timeout(20)
            ->post($this->baseUrl.'/sms/send', [
                'to' => $phone,
                'message' => $message,
                'source_addr' => $sender ?: $this->defaultSender,
            ]);

        return $response->json() ?? [];
    }

    /**
     * Envio de mensagem WhatsApp
     */
    public function sendWhatsApp(string $to, string $message, ?string $instanceId = null): array
    {
        if (! $this->isEnabled()) {
            return ['success' => false, 'message' => $this->disabledMessage, 'disabled' => true];
        }

        $phone = PhoneNormalizer::normalizeInternational($to);

        $response = Http::withHeaders([
            'Authorization' => 'Bearer '.$this->apiToken,
            'Content-Type' => 'application/json',
        ])
            ->timeout(25)
            ->post($this->baseUrl.'/whatsapp/send', [
                'to' => $phone,
                'message' => $message,
                'instance_id' => $instanceId,
            ]);

        return $response->json() ?? [];
    }

    /**
     * Envio de Email transacional
     */
    public function sendEmail(string $to, string $subject, string $htmlContent, ?string $fromName = null): array
    {
        if (! $this->isEnabled()) {
            return ['success' => false, 'message' => $this->disabledMessage, 'disabled' => true];
        }

        $response = Http::withHeaders([
            'Authorization' => 'Bearer '.$this->apiToken,
            'Content-Type' => 'application/json',
        ])
            ->timeout(25)
            ->post($this->baseUrl.'/email/send', [
                'to' => $to,
                'subject' => $subject,
                'html' => $htmlContent,
                'from_name' => $fromName,
            ]);

        return $response->json() ?? [];
    }
}

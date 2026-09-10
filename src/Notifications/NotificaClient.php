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
        protected ?string $defaultWhatsAppInstanceUuid = null,
        protected ?string $defaultEmailFrom = 'noreply@notifica.co.mz',
        protected bool $enabled = true,
        protected string $disabledMessage = 'O serviço de notificações encontra-se temporariamente indisponível.',
    ) {}

    public static function fromConfig(array $config): self
    {
        return new self(
            apiToken: (string) ($config['api_token'] ?? ''),
            baseUrl: (string) ($config['base_url'] ?? 'https://api.notifica.co.mz/api/v1'),
            defaultSender: (string) ($config['default_sms_sender'] ?? 'ORIONCODE'),
            defaultWhatsAppInstanceUuid: $config['default_whatsapp_instance_uuid'] ?? null,
            defaultEmailFrom: $config['default_email_from'] ?? 'noreply@notifica.co.mz',
            enabled: (bool) ($config['enabled'] ?? true),
            disabledMessage: (string) ($config['disabled_message'] ?? 'O serviço de notificações encontra-se temporariamente indisponível.'),
        );
    }

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

    public function getDefaultSmsSender(): string
    {
        return $this->defaultSender;
    }

    public function setDefaultSmsSender(string $sender): self
    {
        $this->defaultSender = $sender;

        return $this;
    }

    public function getDefaultWhatsAppInstanceUuid(): ?string
    {
        return $this->defaultWhatsAppInstanceUuid;
    }

    public function setDefaultWhatsAppInstanceUuid(?string $uuid): self
    {
        $this->defaultWhatsAppInstanceUuid = $uuid;

        return $this;
    }

    public function getDefaultEmailFrom(): ?string
    {
        return $this->defaultEmailFrom;
    }

    public function setDefaultEmailFrom(?string $from): self
    {
        $this->defaultEmailFrom = $from;

        return $this;
    }

    /**
     * Lista todos os remetentes disponíveis (Sender IDs de SMS e instâncias WhatsApp)
     */
    public function listSenders(): array
    {
        if (! $this->isEnabled()) {
            return ['success' => false, 'message' => $this->disabledMessage, 'disabled' => true];
        }

        $response = Http::withHeaders([
            'Authorization' => 'Bearer '.$this->apiToken,
        ])
            ->timeout(15)
            ->get($this->baseUrl.'/senders');

        return $response->json() ?? [];
    }

    /**
     * Lista os remetentes de e-mail disponíveis na plataforma
     */
    public function listEmailSenders(): array
    {
        if (! $this->isEnabled()) {
            return ['success' => false, 'message' => $this->disabledMessage, 'disabled' => true];
        }

        $response = Http::withHeaders([
            'Authorization' => 'Bearer '.$this->apiToken,
        ])
            ->timeout(15)
            ->get($this->baseUrl.'/email/senders');

        return $response->json() ?? [];
    }

    /**
     * Envio de SMS
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
     * Envio de mensagem de texto WhatsApp
     */
    public function sendWhatsAppText(string $to, string $message, ?string $instanceUuid = null): array
    {
        if (! $this->isEnabled()) {
            return ['success' => false, 'message' => $this->disabledMessage, 'disabled' => true];
        }

        $phone = PhoneNormalizer::normalizeInternational($to);
        $uuid = $instanceUuid ?: $this->defaultWhatsAppInstanceUuid;

        $body = [
            'to' => $phone,
            'message' => $message,
        ];

        if (! empty($uuid)) {
            $body['public_instance_uuid'] = $uuid;
        }

        $response = Http::withHeaders([
            'Authorization' => 'Bearer '.$this->apiToken,
            'Content-Type' => 'application/json',
        ])
            ->timeout(25)
            ->post($this->baseUrl.'/whatsapp/send-text', $body);

        return $response->json() ?? [];
    }

    /**
     * Atalho compatível para envio de mensagem WhatsApp
     */
    public function sendWhatsApp(string $to, string $message, ?string $instanceUuid = null): array
    {
        return $this->sendWhatsAppText($to, $message, $instanceUuid);
    }

    /**
     * Envio de multimédia WhatsApp (imagem, vídeo, áudio ou documento)
     */
    public function sendWhatsAppMedia(string $to, string $mediaUrl, string $mediaType, ?string $caption = null, ?string $instanceUuid = null): array
    {
        if (! $this->isEnabled()) {
            return ['success' => false, 'message' => $this->disabledMessage, 'disabled' => true];
        }

        $phone = PhoneNormalizer::normalizeInternational($to);
        $uuid = $instanceUuid ?: $this->defaultWhatsAppInstanceUuid;

        $body = [
            'to' => $phone,
            'media_url' => $mediaUrl,
            'media_type' => $mediaType,
        ];

        if ($caption !== null && $caption !== '') {
            $body['caption'] = $caption;
        }

        if (! empty($uuid)) {
            $body['public_instance_uuid'] = $uuid;
        }

        $response = Http::withHeaders([
            'Authorization' => 'Bearer '.$this->apiToken,
            'Content-Type' => 'application/json',
        ])
            ->timeout(30)
            ->post($this->baseUrl.'/whatsapp/send-media', $body);

        return $response->json() ?? [];
    }

    /**
     * Envio de template pré-aprovado WhatsApp
     */
    public function sendWhatsAppTemplate(string $to, string $templateName, array $params = [], string $language = 'pt', ?string $instanceUuid = null): array
    {
        if (! $this->isEnabled()) {
            return ['success' => false, 'message' => $this->disabledMessage, 'disabled' => true];
        }

        $phone = PhoneNormalizer::normalizeInternational($to);
        $uuid = $instanceUuid ?: $this->defaultWhatsAppInstanceUuid;

        $body = [
            'to' => $phone,
            'template_name' => $templateName,
            'params' => $params,
            'language' => $language,
        ];

        if (! empty($uuid)) {
            $body['public_instance_uuid'] = $uuid;
        }

        $response = Http::withHeaders([
            'Authorization' => 'Bearer '.$this->apiToken,
            'Content-Type' => 'application/json',
        ])
            ->timeout(25)
            ->post($this->baseUrl.'/whatsapp/send-template', $body);

        return $response->json() ?? [];
    }

    /**
     * Envio de Push Notification (Web e Mobile)
     * Conforme documentação oficial preliminar da API Notifica (POST /api/v1/push/send)
     */
    public function sendPush(
        string $title,
        string $body,
        ?string $deviceToken = null,
        ?string $userId = null,
        ?string $imageUrl = null,
        ?array $data = null,
        string $priority = 'normal'
    ): array {
        if (! $this->isEnabled()) {
            return ['success' => false, 'message' => $this->disabledMessage, 'disabled' => true];
        }

        if (empty($deviceToken) && empty($userId)) {
            return [
                'success' => false,
                'message' => 'É obrigatório fornecer o "device_token" ou o "user_id" para envio de push notification.',
            ];
        }

        $payload = [
            'title' => $title,
            'body' => $body,
            'priority' => $priority,
        ];

        if (! empty($deviceToken)) {
            $payload['device_token'] = $deviceToken;
        }

        if (! empty($userId)) {
            $payload['user_id'] = $userId;
        }

        if (! empty($imageUrl)) {
            $payload['image_url'] = $imageUrl;
        }

        if (! empty($data)) {
            $payload['data'] = $data;
        }

        $response = Http::withHeaders([
            'Authorization' => 'Bearer '.$this->apiToken,
            'Content-Type' => 'application/json',
        ])
            ->timeout(20)
            ->post($this->baseUrl.'/push/send', $payload);

        return $response->json() ?? [];
    }

    /**
     * Envio de Email transacional ou de marketing
     */
    public function sendEmail(string $to, string $subject, string $body, ?string $from = null, string $type = 'transactional'): array
    {
        if (! $this->isEnabled()) {
            return ['success' => false, 'message' => $this->disabledMessage, 'disabled' => true];
        }

        $sender = $from ?: $this->defaultEmailFrom;

        $payload = [
            'to' => $to,
            'subject' => $subject,
            'body' => $body,
            'type' => $type,
        ];

        if (! empty($sender)) {
            $payload['from'] = $sender;
        }

        $response = Http::withHeaders([
            'Authorization' => 'Bearer '.$this->apiToken,
            'Content-Type' => 'application/json',
        ])
            ->timeout(25)
            ->post($this->baseUrl.'/email/send', $payload);

        return $response->json() ?? [];
    }
}

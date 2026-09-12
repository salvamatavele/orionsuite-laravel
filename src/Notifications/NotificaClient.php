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
        protected ?string $smsToken = null,
        protected ?string $whatsappToken = null,
        protected ?string $emailToken = null,
        protected ?string $pushToken = null,
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
            smsToken: ! empty($config['sms_token']) ? (string) $config['sms_token'] : null,
            whatsappToken: ! empty($config['whatsapp_token']) ? (string) $config['whatsapp_token'] : null,
            emailToken: ! empty($config['email_token']) ? (string) $config['email_token'] : null,
            pushToken: ! empty($config['push_token']) ? (string) $config['push_token'] : null,
            defaultSender: (string) ($config['default_sms_sender'] ?? 'ORIONCODE'),
            defaultWhatsAppInstanceUuid: $config['default_whatsapp_instance_uuid'] ?? null,
            defaultEmailFrom: $config['default_email_from'] ?? 'noreply@notifica.co.mz',
            enabled: (bool) ($config['enabled'] ?? true),
            disabledMessage: (string) ($config['disabled_message'] ?? 'O serviço de notificações encontra-se temporariamente indisponível.'),
        );
    }

    /**
     * Obtém o token apropriado para o serviço (SMS, WhatsApp, Email, Push) com fallback para apiToken
     */
    public function getTokenFor(string $service = 'general'): string
    {
        return match (strtolower($service)) {
            'sms' => $this->smsToken ?: $this->apiToken,
            'whatsapp' => $this->whatsappToken ?: $this->apiToken,
            'email' => $this->emailToken ?: $this->apiToken,
            'push' => $this->pushToken ?: $this->apiToken,
            default => $this->apiToken,
        };
    }

    public function setSmsToken(?string $token): self
    {
        $this->smsToken = $token;

        return $this;
    }

    public function setWhatsAppToken(?string $token): self
    {
        $this->whatsappToken = $token;

        return $this;
    }

    public function setEmailToken(?string $token): self
    {
        $this->emailToken = $token;

        return $this;
    }

    public function setPushToken(?string $token): self
    {
        $this->pushToken = $token;

        return $this;
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

        $token = $this->getTokenFor('general');

        $response = Http::withHeaders([
            'Authorization' => 'Bearer '.$token,
            'Accept' => 'application/json',
        ])
            ->timeout(15)
            ->get($this->baseUrl.'/senders');

        return $this->formatResponse($response);
    }

    /**
     * Lista os remetentes de e-mail disponíveis na plataforma
     */
    public function listEmailSenders(): array
    {
        if (! $this->isEnabled()) {
            return ['success' => false, 'message' => $this->disabledMessage, 'disabled' => true];
        }

        $token = $this->getTokenFor('email');

        $response = Http::withHeaders([
            'Authorization' => 'Bearer '.$token,
            'Accept' => 'application/json',
        ])
            ->timeout(15)
            ->get($this->baseUrl.'/email/senders');

        return $this->formatResponse($response);
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
        $token = $this->getTokenFor('sms');

        $response = Http::withHeaders([
            'Authorization' => 'Bearer '.$token,
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ])
            ->timeout(20)
            ->post($this->baseUrl.'/sms/send', [
                'to' => $phone,
                'message' => $message,
                'source_addr' => $sender ?: $this->defaultSender,
            ]);

        return $this->formatResponse($response);
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
        $token = $this->getTokenFor('whatsapp');

        $body = [
            'to' => $phone,
            'message' => $message,
        ];

        if (! empty($uuid)) {
            $body['public_instance_uuid'] = $uuid;
        }

        $response = Http::withHeaders([
            'Authorization' => 'Bearer '.$token,
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ])
            ->timeout(25)
            ->post($this->baseUrl.'/whatsapp/send-text', $body);

        return $this->formatResponse($response);
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
        $token = $this->getTokenFor('whatsapp');

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
            'Authorization' => 'Bearer '.$token,
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ])
            ->timeout(30)
            ->post($this->baseUrl.'/whatsapp/send-media', $body);

        return $this->formatResponse($response);
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
        $token = $this->getTokenFor('whatsapp');

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
            'Authorization' => 'Bearer '.$token,
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ])
            ->timeout(25)
            ->post($this->baseUrl.'/whatsapp/send-template', $body);

        return $this->formatResponse($response);
    }

    /**
     * Envio de Push Notification (Web e Mobile)
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

        $token = $this->getTokenFor('push');

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
            'Authorization' => 'Bearer '.$token,
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ])
            ->timeout(20)
            ->post($this->baseUrl.'/push/send', $payload);

        return $this->formatResponse($response);
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
        $token = $this->getTokenFor('email');

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
            'Authorization' => 'Bearer '.$token,
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ])
            ->timeout(25)
            ->post($this->baseUrl.'/email/send', $payload);

        return $this->formatResponse($response);
    }

    /**
     * Formata e padroniza a resposta HTTP retornada pela API Notifica
     */
    protected function formatResponse(\Illuminate\Http\Client\Response $response): array
    {
        $json = $response->json();

        if (is_array($json)) {
            // Se a API retornou erro no payload ou status >= 400
            if (! $response->successful() && ! isset($json['success'])) {
                $json['success'] = false;
            }

            if (! isset($json['message'])) {
                if (isset($json['error'])) {
                    $json['message'] = is_array($json['error']) ? ($json['error']['message'] ?? json_encode($json['error'])) : (string) $json['error'];
                }
            }

            return $json;
        }

        if (! $response->successful()) {
            return [
                'success' => false,
                'status' => $response->status(),
                'message' => $response->body() ?: 'Erro de comunicação com a API Notifica (Status '.$response->status().')',
            ];
        }

        return [
            'success' => true,
            'status' => $response->status(),
            'raw' => $response->body(),
        ];
    }
}

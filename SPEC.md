# Especificação Arquitetural e Técnica: OrionSuite Laravel

**Pacote**: `salvamatavele/orionsuite-laravel`  
**Namespace PHP**: `OrionSuite\`  
**Versão**: `^1.0.2`  
**Licença**: MIT  
**Repositório**: `https://github.com/salvamatavele/orionsuite-laravel`

---

## 1. Visão Geral & Propósito
O **OrionSuite Laravel** é o pacote oficial Laravel para integração dos três serviços essenciais da infraestrutura digital moçambicana do OrionCodeTech:
1. **Pagar.co.mz**: Gateway de pagamentos C2B (M-Pesa e e-Mola), carregamento de carteira (topups) e desembolsos (payouts).
2. **Notifica.co.mz**: Plataforma multicanal de envio de SMS corporativo, WhatsApp Business (texto, ficheiros, templates), Notificações Push e E-mail transacional.
3. **Orion KYC / OCR**: Reconhecimento de caracteres de documentos moçambicanos (BI, DIRE, Passaporte) e biometria/reconhecimento facial.

O objetivo do pacote é fornecer uma API idiomática Laravel com injeção de dependência, Facades, eventos de domínio, validação HMAC-SHA256, kill-switches independentes e comandos de publicação de configuração e componentes UI.

---

## 2. Arquitetura do Pacote

### 2.1 Estrutura de Diretórios
```text
orionsuite-laravel/
├── config/
│   └── orionsuite.php             # Configuração unificada (Pagar, Notifica, KYC)
├── resources/
│   └── js/components/
│       └── PagarModal.tsx          # Componente React exportável para aplicações Inertia/Vite
├── src/
│   ├── Enums/
│   │   └── PaymentStatus.php      # PENDING, PROCESSING, COMPLETED, FAILED, CANCELLED
│   ├── Identity/
│   │   └── OrionKycClient.php     # Cliente HTTP para OCR e Biometria
│   ├── Laravel/
│   │   ├── Events/
│   │   │   ├── PaymentCompleted.php
│   │   │   └── PaymentFailed.php
│   │   ├── Facades/
│   │   │   ├── Notifica.php
│   │   │   ├── OrionKyc.php
│   │   │   └── Pagar.php
│   │   ├── Http/Controllers/
│   │   │   └── PagarWebhookController.php
│   │   └── OrionSuiteServiceProvider.php
│   ├── Notifications/
│   │   └── NotificaClient.php     # Cliente HTTP para SMS, WhatsApp, Push, E-mail
│   ├── Payments/
│   │   └── PagarClient.php        # Cliente HTTP com assinatura HMAC e regras de pagamento
│   ├── Support/
│   │   └── PhoneNormalizer.php    # Normalização de telefones (+258, 84/85, 86/87, 82/83)
│   └── OrionSuiteManager.php      # Agregador central dos 3 clientes
├── tests/
│   ├── Pest.php
│   └── OrionSuiteTest.php
├── composer.json
└── README.md
```

### 2.2 Service Provider & Injeção de Dependências
O `OrionSuiteServiceProvider` registra:
- `PagarClient`: Singleton instanciado a partir de `config('orionsuite.pagar')`.
- `NotificaClient`: Singleton instanciado a partir de `config('orionsuite.notifica')`.
- `OrionKycClient`: Singleton instanciado a partir de `config('orionsuite.kyc')`.
- `OrionSuiteManager`: Agregador contendo as 3 instâncias com métodos auxiliares `enableAll()` e `disableAll()`.
- Rotas de Webhook: Registradas automaticamente sob `config('orionsuite.pagar.webhook.path')` (padrão `/api/webhooks/pagar`).

---

## 3. Especificação do Módulo Pagar.co.mz

### 3.1 Regras de Negócio & Limites Estritos
- **Moeda**: `MZN` (Meticais).
- **Limite Mínimo**: **20 MZN** (imposto rigorosamente pela API Pagar.co.mz; requisições com valor inferior a 20 MZN são rejeitadas com erro de validação).
- **Limite Máximo Padrão**: **40.000 MZN** (configurável via `PAGAR_MAX_AMOUNT`).
- **Operadores Suportados**:
  - Vodacom M-Pesa: prefixos `84` e `85` (método `MPESA`).
  - Movitel e-Mola: prefixos `86` e `87` (método `EMOLA`).
- **Campo `description`**: Obrigatório como string válida (nunca `null`, caso contrário a validação Zod da API Pagar rejeita com `Expected string, received null`).

### 3.2 Assinatura Canonical HMAC-SHA256
Todas as chamadas `POST` que alteram estado exigem assinatura canônica:
```text
Canonical String = [
    timestamp (epoch ms),
    nonce (random 24 bytes),
    method (POST),
    canonicalPath (/api/v1/payments),
    bodyHash (sha256 do JSON sem escape)
].join("\n")

Signature = hmac_sha256(Canonical String, signingSecret)
```
Headers enviados:
- `Authorization: Bearer <API_KEY>`
- `X-Pagar-Timestamp: <TIMESTAMP>`
- `X-Pagar-Nonce: <NONCE>`
- `X-Pagar-Signature: v1=<SIGNATURE>`
- `Idempotency-Key: <IDEMPOTENCY_KEY>`

---

## 4. Especificação do Módulo Notifica.co.mz

### 4.1 Autenticação por Service Token e JWT
A API Notifica.co.mz suporta tokens dedicados permanentes por serviço (gerados em *Serviços → [Serviço] → Tokens*):
- `sms_token` (`NOTIFICA_SMS_TOKEN`): Token dedicado para envio de SMS.
- `whatsapp_token` (`NOTIFICA_WHATSAPP_TOKEN`): Token dedicado para instâncias de WhatsApp.
- `email_token` (`NOTIFICA_EMAIL_TOKEN`): Token dedicado para envio de E-mails.
- `push_token` (`NOTIFICA_PUSH_TOKEN`): Token dedicado para Push Notifications.
- `api_token` (`NOTIFICA_API_TOKEN`): Token global/sessão (JWT) com fallback automático caso um token de serviço não seja especificado.

O método `getTokenFor(string $service)` resolve automaticamente o token mais específico, garantindo que as chamadas a cada canal usem o Service Token correto com permissão ativa.

### 4.2 Métodos Disponíveis
- **SMS Corporativo**: `sendSms(string $to, string $message, ?string $sender = null)`
- **WhatsApp**:
  - Texto: `sendWhatsAppText(string $to, string $message, ?string $instanceUuid = null)`
  - Mídia/Documentos: `sendWhatsAppMedia(string $to, string $mediaUrl, string $type, ?string $caption = null, ?string $instanceUuid = null)`
  - Templates: `sendWhatsAppTemplate(string $to, string $templateName, array $params = [], string $lang = 'pt', ?string $instanceUuid = null)`
- **Push Notifications**: `sendPush(string $title, string $body, ?string $deviceToken = null, ?string $userId = null, ?string $imageUrl = null, ?array $data = null, string $priority = 'normal')`
- **E-mail**: `sendEmail(string $to, string $subject, string $body, ?string $from = null, string $type = 'transactional')`
- **Descoberta de Remetentes**: `listSenders()` e `listEmailSenders()`

---

## 5. Diretrizes para Agentes de IA & Desenvolvedores Futuros

1. **Nunca Enviar `description: null`**:
   Em qualquer nova chamada à API de cobrança, garantir que `description` seja uma string não vazia.
2. **Preservar os Silenciadores (*Kill-Switches*)**:
   Cada módulo possui método `isEnabled()`. Se desativado, deve retornar resposta controlada com código `GATEWAY_DISABLED` sem efetuar chamadas HTTP.
3. **Padrão de Resposta Unificado**:
   Todas as operações retornam arrays associativos contendo `success` (bool), `status` (string), `reference` (nullable string), `message` (nullable string) e `raw` (dados originais).
4. **Testes Obrigatórios com Pest**:
   Sempre rodar `./vendor/bin/pest` após alterações em clientes ou facades.
5. **Estilo de Código**:
   Formatar sempre com `./vendor/bin/pint --format agent`.

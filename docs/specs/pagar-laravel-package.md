# Especificação Técnica: Pacote Reutilizável Pagar.co.mz para Laravel (`salvamatavele/pagar-laravel`)

## 1. Visão Geral
Esta especificação estabelece os requisitos, arquitetura e critérios de aceitação para o desenvolvimento do pacote **`salvamatavele/pagar-laravel`**, uma biblioteca PHP/Laravel moderna, modular e pronta para publicação no **Packagist**. O pacote permitirá integrar a infraestrutura de pagamentos moçambicana da **Pagar.co.mz** ([https://pagar.co.mz/docs](https://pagar.co.mz/docs)) em qualquer projeto Laravel ou ecossistema PHP.

> [!NOTE]
> **Status de Canais & Suporte**: De acordo com a documentação atual da API, os fluxos diretos C2B suportam **M-Pesa** e **e-Mola**. A biblioteca é desenhada com arquitetura extensível para acomodar **Cartão Bancário** e **PayPal** caso o suporte confirme disponibilidade ou venha a disponibilizar endpoints adicionais para a API direta.

---

## 2. Requisitos Técnicos e Arquitetura

### 2.1. Núcleo PHP (Framework-Agnostic)
1. **`PagarClient`**:
   - Gestão de chamadas autenticadas à API (`https://api.pagar.co.mz/api/v1`).
   - Assinatura canónica estrita em requisições `POST`:
     $$\text{canonical} = \text{timestamp} + \text{"\n"} + \text{nonce} + \text{"\n"} + \text{METHOD} + \text{"\n"} + \text{pathname} + \text{"\n"} + \text{sha256(rawBody)}$$
   - Cálculo de HMAC-SHA256 com `PAGAR_SIGNING_SECRET` e envio do cabeçalho `X-Pagar-Signature: v1=<hex>`.
   - Geração automática de `X-Pagar-Nonce` (18 bytes em `base64url`) e `X-Pagar-Timestamp` (Unix epoch em milissegundos com 13 dígitos).
   - Injeção obrigatória do cabeçalho `Idempotency-Key` em operações de escrita/mutação.

2. **C2B Payments (`POST /payments`)**:
   - Cobrança direta via prompt USSD / STK Push para M-Pesa e e-Mola.
   - Suporte a `reference`, `title`, `description`, `amountMzn` (inteiros entre 20 e 40.000 MZN) e `payerPhone`.
   - Recuperação inteligente de resposta e tratamento de retries idempotentes.

3. **Payouts B2C & Top-ups**:
   - `POST /payouts`: Envio de dinheiro para carteiras de clientes/fornecedores a partir do saldo da aplicação (com cálculo da taxa de 8%).
   - `POST /wallet/topups`: Financiamento de saldo próprio sem taxa de entrada.
   - `GET /wallet`: Consulta de saldo disponível (`availableBalanceMzn`) e saldo reservado (`reservedBalanceMzn`).

4. **Validador Criptográfico de Webhook (`PagarWebhookSignature`)**:
   - Parse e extração de `Pagar-Signature: t=<timestamp>,v1=<hex>`.
   - Validação de janela de tempo (rejeita requisições com desvio temporal superior a 300 segundos).
   - Comparação em tempo constante com `hash_equals` / `timingSafeEqual`.
   - Deduplicação obrigatória através do cabeçalho `Pagar-Event-Id`.

5. **Normalizador e Validador de Números (`PagarPhone`)**:
   - Normalização para o formato oficial de Moçambique (`84XXXXXXX`, `86XXXXXXX` -> `258...`).
   - Validação de correspondência entre operadora e método (`MPESA` vs `EMOLA`).

---

### 2.2. Integração Laravel
1. **`PagarServiceProvider`**:
   - Auto-discovery no `composer.json`.
   - Publicação de configurações: `php artisan vendor:publish --tag="pagar-config"`.
   - Injeção de dependência do `PagarClient` no container de serviços.
2. **`Facade`**:
   - Sintaxe limpa:
     ```php
     use Pagar\Laravel\Facades\Pagar;

     // Iniciar cobrança C2B
     $payment = Pagar::createPayment([
         'reference' => 'PROP-2026-001',
         'title' => 'Propina de Março',
         'amountMzn' => 2500,
         'method' => 'MPESA',
         'payerPhone' => '841234567',
     ]);

     // Consultar saldo da carteira
     $wallet = Pagar::getWallet();

     // Enviar pagamento B2C
     $payout = Pagar::createPayout([
         'reference' => 'REM-DOCENTE-01',
         'amountMzn' => 5000,
         'method' => 'EMOLA',
         'recipient' => ['phone' => '861234567', 'name' => 'Dr. João'],
     ]);
     ```
3. **Controller & Eventos de Webhook**:
   - Rota configurável: `/api/webhooks/pagar`.
   - Despacho de eventos tipados:
     - `PagarPaymentSucceeded`
     - `PagarPaymentFailed`
     - `PagarPayoutSucceeded`
     - `PagarPayoutFailed`
     - `PagarTopupSucceeded`

---

### 2.3. Componente Frontend Reutilizável
- Componente React em `resources/js/components/PagarOnlineModal.tsx`:
  - Interface moderna e responsiva com Tailwind CSS.
  - Seleção visual de canal (M-Pesa / e-Mola) com deteção automática de operadora pelo número digitado.
  - Polling seguro com intervalo progressivo (10-15s com jitter) e paragem ao atingir estado terminal (`PAID`, `CANCELLED`, `FAILED`).

---

## 3. Estrutura do Pacote

```
packages/pagar-laravel/
├── composer.json
├── README.md
├── LICENSE
├── phpunit.xml
├── config/
│   └── pagar.php
├── src/
│   ├── PagarClient.php
│   ├── Contracts/
│   │   └── PagarInterface.php
│   ├── DTO/
│   │   ├── PaymentRequest.php
│   │   ├── PaymentResponse.php
│   │   ├── PayoutRequest.php
│   │   ├── PayoutResponse.php
│   │   └── WalletBalance.php
│   ├── Enums/
│   │   ├── PaymentMethod.php
│   │   ├── PaymentStatus.php
│   │   └── Environment.php
│   ├── Exceptions/
│   │   ├── PagarApiException.php
│   │   └── PagarSignatureException.php
│   ├── Support/
│   │   ├── CanonicalSignature.php
│   │   ├── WebhookSignature.php
│   │   └── PhoneNormalizer.php
│   └── Laravel/
│       ├── PagarServiceProvider.php
│       ├── Facades/
│       │   └── Pagar.php
│       ├── Http/
│       │   ├── Controllers/
│       │   │   └── WebhookController.php
│       │   └── Middleware/
│       │       └── VerifyWebhookSignature.php
│       └── Events/
│           ├── PagarPaymentSucceeded.php
│           └── PagarPaymentFailed.php
├── resources/
│   └── js/
│       └── components/
│           └── PagarModal.tsx
└── tests/
    ├── Feature/
    │   ├── WebhookControllerTest.php
    │   └── PagarServiceProviderTest.php
    └── Unit/
        ├── CanonicalSignatureTest.php
        ├── WebhookSignatureTest.php
        ├── PhoneNormalizerTest.php
        └── PagarClientTest.php
```

---

## 4. Plano de Configuração (`config/pagar.php`)

```php
return [
    'enabled' => env('PAGAR_ENABLED', true),
    'environment' => env('PAGAR_ENV', 'TEST'), // TEST ou LIVE
    'base_url' => env('PAGAR_API_BASE_URL', 'https://api.pagar.co.mz/api/v1'),
    'api_key' => env('PAGAR_API_KEY'),
    'signing_secret' => env('PAGAR_SIGNING_SECRET'),
    'webhook_secret' => env('PAGAR_WEBHOOK_SECRET'),
    'webhook_route' => env('PAGAR_WEBHOOK_ROUTE', '/api/webhooks/pagar'),
    'timeout' => (int) env('PAGAR_TIMEOUT', 15),
];
```

---

## 5. Critérios de Aceitação e Testes Automatizados

1. **Testes Unitários de Assinatura (Canonical & HMAC)**:
   - Assinatura canónica gerada bate exatamente com o hash e HMAC esperados na especificação oficial.
   - Rejeição estrita de timestamps desfasados em mais de 300s no webhook.
   - Comparação em tempo constante imune a ataques de temporização (timing attacks).
2. **Testes de Idempotência e Retries**:
   - Reenvio de payload idêntico com a mesma `Idempotency-Key` mantém a integridade da operação.
3. **Validação e Normalização de Telefones**:
   - Prefixos `84`/`85` aceites como `MPESA`.
   - Prefixos `86`/`87` aceites como `EMOLA`.
   - Rejeição de números inválidos antes de submeter à API.
4. **Despacho de Webhooks**:
   - Disparo correto dos eventos Laravel em transações confirmadas.
5. **Packagist Readiness**:
   - Suporte a PHP 8.2+, Laravel 11 e 12+.
   - `composer validate --strict` sem erros.
   - Cobertura de testes automatizados com Pest/PHPUnit.

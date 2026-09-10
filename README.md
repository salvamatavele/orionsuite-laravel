# OrionSuite Laravel (salvamatavele/orionsuite-laravel)

[![Latest Version on Packagist](https://img.shields.io/packagist/v/salvamatavele/orionsuite-laravel.svg?style=flat-square)](https://packagist.org/packages/salvamatavele/orionsuite-laravel)
[![Total Downloads](https://img.shields.io/packagist/dt/salvamatavele/orionsuite-laravel.svg?style=flat-square)](https://packagist.org/packages/salvamatavele/orionsuite-laravel)
[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](https://opensource.org/licenses/MIT)

SDK oficial para o ecossistema tecnológico **[OrionCodeTech](https://orioncodetech.com)** em Moçambique, unificando numa única biblioteca Laravel:

1. 💳 **[Pagar.co.mz](https://pagar.co.mz/docs)**: Cobranças C2B (M-Pesa e e-Mola com limites dinâmicos até 40.000 MZN), Top-ups, Payouts B2C, consulta de carteira, assinaturas Canonical HMAC e Webhooks blindados.
2. 📲 **[Notifica.co.mz](https://notifica.co.mz/docs)**: Mensajaria multicanal de alto desempenho (SMS nacional e internacional com `source_addr`, WhatsApp, Email transacional e Push Notifications).
3. 🪪 **[Orion OCR / KYC](https://ocr.orioncodetech.com/docs)**: Verificação automatizada de identidade moçambicana (Leitura OCR de BI: frente e verso, detecção facial, Face Match e Liveness anti-spoofing).
4. 🎛️ **Kill-Switch Independente por Módulo**: Ativação e silenciamento dinâmico individual de cada serviço via `.env` ou em tempo de execução sem quebrar a aplicação.

---

## 📦 Instalação

```bash
composer require salvamatavele/orionsuite-laravel
```

Publique o arquivo de configuração:
```bash
php artisan vendor:publish --tag=orionsuite-config
```

---

## ⚙️ Variáveis de Ambiente (`.env`)

```env
# -------------------------------------------------------------
# Pagar.co.mz (Gateway de Pagamento)
# -------------------------------------------------------------
PAGAR_ENABLED=true
PAGAR_API_BASE_URL="https://api.pagar.co.mz"
PAGAR_API_KEY="sk_live_sua_chave"
PAGAR_SIGNING_SECRET="sig_live_seu_signing_secret"
PAGAR_WEBHOOK_SECRET="whsec_seu_webhook_secret"

# Limites Dinâmicos da Pagar (em Meticais - MZN)
PAGAR_MIN_AMOUNT=20
PAGAR_MAX_AMOUNT=40000

# -------------------------------------------------------------
# Notifica.co.mz (Comunicação Multicanal)
# -------------------------------------------------------------
NOTIFICA_ENABLED=true
NOTIFICA_API_BASE_URL="https://api.notifica.co.mz/api/v1"
NOTIFICA_API_TOKEN="seu_token_notifica"
NOTIFICA_DEFAULT_SMS_SENDER="ORIONCODE"

# -------------------------------------------------------------
# Orion OCR / Identity KYC
# -------------------------------------------------------------
ORION_KYC_ENABLED=true
ORION_KYC_BASE_URL="https://api.ocr.orioncodetech.com/v1"
ORION_KYC_TOKEN="kyc_live_seu_token"
ORION_KYC_ENVIRONMENT="production" # ou "test"
```

---

## 🚀 Guia de Utilização

### 1. Pagamentos C2B (M-Pesa e e-Mola) via Facade `Pagar`

A operadora móvel é detectada automaticamente pelo número do telemóvel:

```php
use OrionSuite\Laravel\Facades\Pagar;

// Disparo C2B (Gera assinatura Canonical HMAC automaticamente)
$response = Pagar::createPayment([
    'reference' => 'PROPINA-2026-001',
    'title' => 'Mensalidade Escolar',
    'amountMzn' => 2500, // Inteiro ou decimal
    'phone' => '841234567', // Detecta automaticamente Vodacom M-Pesa
]);

if ($response['success']) {
    $paymentId = $response['paymentId'];
    $status = $response['status']; // 'PROCESSING'
}
```

#### Limites Dinâmicos:
```php
// Consultar limites atuais configurados
$min = Pagar::getMinAmount(); // 20.0
$max = Pagar::getMaxAmount(); // 40000.0

// Modificar em tempo de execução se a Pagar atualizar o teto
Pagar::setMaxAmount(60000.0);
```

#### Payouts B2C (Envio de fundos):
```php
$payout = Pagar::createPayout([
    'reference' => 'SAIDA-001',
    'amountMzn' => 1000,
    'method' => 'EMOLA',
    'recipientPhone' => '861234567',
    'recipientName' => 'Nome do Beneficiário',
]);
```

---

### 2. Notificações Multicanal via Facade `Notifica`

```php
use OrionSuite\Laravel\Facades\Notifica;

// Envio de SMS
Notifica::sendSms(
    to: '841234567',
    message: 'Seu pagamento foi confirmado com sucesso!',
    sender: 'DRYACADEMIC'
);

// Envio de WhatsApp
Notifica::sendWhatsApp(
    to: '841234567',
    message: 'Olá! Segue o link de acesso ao seu curso: https://...'
);

// Envio de Email
Notifica::sendEmail(
    to: 'estudante@exemplo.ac.mz',
    subject: 'Confirmação de Matrícula',
    htmlContent: '<h1>Matrícula Confirmada</h1>'
);
```

---

### 3. Validação de Identidade KYC via Facade `OrionKyc`

```php
use OrionSuite\Laravel\Facades\OrionKyc;

// Verificação completa com BI e Selfie
$verification = OrionKyc::verifyIdentity(
    documentFrontPath: storage_path('app/kyc/bi-frente.jpg'),
    documentBackPath: storage_path('app/kyc/bi-verso.jpg'),
    selfiePath: storage_path('app/kyc/selfie.jpg'),
    externalReference: 'ESTUDANTE-123'
);

$decision = $verification['decision']; // 'approved' | 'rejected' | 'pending_review'
$docData = $verification['document'];  // Número do BI, Nome Completo, Validade
$face = $verification['face'];          // face_match_score, liveness_score
```

---

### 4. Silenciador / Kill-Switch (Controle de Manutenção)

```php
use OrionSuite\Laravel\Facades\Pagar;
use OrionSuite\Laravel\Facades\OrionSuite;

// Silenciar apenas pagamentos (ex: instabilidade temporária do M-Pesa)
Pagar::disable();

// Ou silenciar todos os serviços da suite de uma só vez
OrionSuite::disableAll();

// Reativar
OrionSuite::enableAll();
```

---

## 🧪 Testes Automatizados

```bash
php artisan test packages/orionsuite-laravel/tests/OrionSuiteTest.php
```

## 📄 Licença
Distribuído sob a licença MIT. Desenvolvido por **[Salvado Matavele](https://github.com/salvamatavele)**.

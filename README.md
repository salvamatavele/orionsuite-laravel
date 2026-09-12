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

```bash
# Publicar arquivo de configuração
php artisan vendor:publish --tag=orionsuite-config

# Publicar componente React de Pagamento (<PagarModal />)
php artisan vendor:publish --tag=orionsuite-react
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
NOTIFICA_BASE_URL="https://api.notifica.co.mz/api/v1"

# Service Tokens Dedicados por Serviço (Recomendado pela Notifica):
NOTIFICA_SMS_TOKEN="seu_token_permanente_de_sms"            # Em Serviços → SMS → Tokens
NOTIFICA_WHATSAPP_TOKEN="seu_token_permanente_de_whatsapp" # Em Serviços → WhatsApp → Tokens
NOTIFICA_EMAIL_TOKEN="seu_token_permanente_de_email"       # Em Serviços → Email → Tokens
NOTIFICA_PUSH_TOKEN="seu_token_permanente_de_push"         # Em Serviços → Push → Tokens

# Token Global / Sessão (Usado para /senders ou como fallback automático):
NOTIFICA_API_TOKEN="seu_token_geral_ou_jwt"

NOTIFICA_DEFAULT_SMS_SENDER="ORIONCODE"
NOTIFICA_DEFAULT_WHATSAPP_INSTANCE_UUID="uuid-da-instancia-whatsapp"
NOTIFICA_DEFAULT_EMAIL_FROM="notificacoes@suaempresa.co.mz"

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

### 1. Pagamentos C2B, Top-ups e Payouts (Pagar.co.mz)

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

#### Recarga de Carteira (Wallet Top-up):
```php
$topup = Pagar::createTopup([
    'amount' => 1500,
    'payment_method' => 'MPESA',
    'customer_phone' => '841234567',
    'reference' => 'RECARGA-001',
]);
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

### 2. Notificações Multicanal (Notifica.co.mz)

```php
use OrionSuite\Laravel\Facades\Notifica;

// 1. Envio de SMS (com remetente dinâmico ou padrão)
Notifica::sendSms(
    to: '841234567',
    message: 'Seu pagamento foi confirmado com sucesso!',
    sender: 'DRYACADEMIC' // opcional: usa default_sms_sender se omitido
);

// 2. WhatsApp Oficial (Texto, Mídia ou Template)
// Texto:
Notifica::sendWhatsAppText(
    to: '841234567',
    message: 'Olá! Seu recibo digital está disponível.',
    instanceUuid: 'uuid-opcional' // usa padrão se omitido
);

// Mídia (PDF, Imagem, etc):
Notifica::sendWhatsAppMedia(
    to: '841234567',
    mediaUrl: 'https://suaempresa.co.mz/recibos/123.pdf',
    mediaType: 'document', // 'image' | 'document' | 'video' | 'audio'
    caption: 'Segue o seu recibo'
);

// Template aprovado:
Notifica::sendWhatsAppTemplate(
    to: '841234567',
    templateName: 'recibo_pagamento',
    params: ['Salvado', '2.500 MZN']
);

// 3. Push Notifications (Preview Oficial da Notifica)
Notifica::sendPush([
    'user_id' => 'user_98765', // ou 'device_token' => 'fcm_token_...'
    'title' => 'Matrícula Ativa',
    'body' => 'Seu acesso ao portal acadêmico foi desbloqueado.',
    'image_url' => 'https://suaempresa.co.mz/banner.png',
    'data' => ['screen' => 'profile', 'id' => 123],
    'priority' => 'high',
]);

// 4. Descoberta de Remetentes e Instâncias Aprovadas
$senders = Notifica::listSenders(); // Lista remetentes SMS e instâncias WhatsApp
$emailSenders = Notifica::listEmailSenders(); // Lista domínios de email aprovados

// 5. Envio de Email com Layout Notifica
Notifica::sendEmail(
    to: 'estudante@exemplo.ac.mz',
    subject: 'Confirmação de Matrícula',
    body: 'Sua matrícula foi realizada com sucesso.',
    from: 'admissoes@universidade.ac.mz' // opcional
);

// 6. Resolução Automática de Token por Serviço:
// O NotificaClient resolve automaticamente o Service Token correto:
// Notifica::sendSms() usa NOTIFICA_SMS_TOKEN (ou NOTIFICA_API_TOKEN)
// Notifica::sendWhatsAppText() usa NOTIFICA_WHATSAPP_TOKEN (ou NOTIFICA_API_TOKEN)
// Notifica::sendEmail() usa NOTIFICA_EMAIL_TOKEN (ou NOTIFICA_API_TOKEN)
// Notifica::sendPush() usa NOTIFICA_PUSH_TOKEN (ou NOTIFICA_API_TOKEN)
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

### 5. Componente React Interativo (`<PagarModal />`)

Após executar `php artisan vendor:publish --tag=orionsuite-react`, o componente será copiado para `resources/js/components/PagarModal.tsx`. Pode utilizá-lo diretamente nas suas páginas Inertia/React:

```tsx
import React, { useState } from 'react';
import PagarModal from '@/components/PagarModal';

export default function CheckoutPage({ propina }) {
    const [open, setOpen] = useState(false);

    return (
        <div>
            <button
                onClick={() => setOpen(true)}
                className="px-5 py-2.5 bg-amber-400 font-bold rounded-xl shadow hover:bg-amber-500"
            >
                Pagar com M-Pesa / e-Mola
            </button>

            <PagarModal
                open={open}
                onOpenChange={setOpen}
                amount={propina.valor}
                reference={propina.codigo}
                title="Pagamento de Mensalidade"
                endpoints={{
                    paymentUrl: '/api/pagamentos/pagar', // Rota POST da sua aplicação
                    statusUrl: '/api/pagamentos/status', // Opcional: Rota GET para polling
                }}
                onSuccess={(data) => {
                    console.log('Pago com sucesso!', data);
                    // Atualizar UI ou redirecionar
                }}
            />
        </div>
    );
}
```

---

## 🧪 Testes Automatizados

```bash
php artisan test packages/orionsuite-laravel/tests/OrionSuiteTest.php
```

## 📄 Licença
Distribuído sob a licença MIT. Desenvolvido por **[Salvado Matavele](https://github.com/salvamatavele)**.

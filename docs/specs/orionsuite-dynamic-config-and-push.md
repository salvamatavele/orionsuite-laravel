# Especificação Técnica: Configuração Dinâmica, Endpoints Reais e Push Notifications no OrionSuite

## 1. Contexto e Objetivo
Garantir que as bibliotecas `salvamatavele/orionsuite-laravel` e `@dryinov8/orionsuite-ts` reflitam com exatidão a documentação oficial da plataforma **Notifica.co.mz** e **Pagar.co.mz**:
- Permitir configuração flexível e dinâmica de identificadores que podem variar ou ser escolhidos por envio (ex.: `source_addr` de SMS, `public_instance_uuid` de instâncias WhatsApp públicas ou privadas, `from` de e-mail).
- Implementar métodos utilitários para listar os remetentes ativos (`GET /api/v1/senders` e `GET /api/v1/email/senders`).
- Alinhar os endpoints de WhatsApp para as rotas reais da Notifica (`/whatsapp/send-text`, `/whatsapp/send-media`, `/whatsapp/send-template`).
- Implementar o método de **Push Notifications** (`POST /api/v1/push/send`) seguindo estritamente a especificação oficial prévia documentada pela Notifica.
- Assegurar paridade absoluta entre a biblioteca PHP/Laravel e a biblioteca TypeScript/React.

---

## 2. Requisitos e Design da API

### A. Notifica.co.mz - Módulo de Notificações

#### 1. SMS (`POST /api/v1/sms/send`)
- **Parâmetros**: `to` (normalizado sem `+`), `message` (máx 1600 caracteres), `source_addr` (obrigatório na API).
- **Flexibilidade**:
  - `source_addr` padrão configurável via `.env` (`NOTIFICA_DEFAULT_SMS_SENDER`, padrão `ORIONCODE`) ou config `orionsuite.notifica.default_sms_sender`.
  - Mutador em tempo de execução: `$client->setDefaultSmsSender('NOVO_SENDER')` / `client.setDefaultSmsSender('NOVO_SENDER')`.
  - Sobrescrita opcional por envio: `sendSms($to, $message, $sender)`.

#### 2. WhatsApp (`POST /api/v1/whatsapp/...`)
- **Rotas Oficiais**:
  1. `POST /api/v1/whatsapp/send-text`:
     - Payload: `{ to, message, public_instance_uuid? }`
     - Se `public_instance_uuid` for omitido, a API usa o número próprio conectado da conta. Se informado, utiliza o número público especificado.
  2. `POST /api/v1/whatsapp/send-media`:
     - Payload: `{ to, media_url, media_type, caption?, public_instance_uuid? }`
     - `media_type`: `'image' | 'video' | 'audio' | 'document'`.
  3. `POST /api/v1/whatsapp/send-template`:
     - Payload: `{ to, template_name, params?, language?, public_instance_uuid? }`
- **Flexibilidade**:
  - `public_instance_uuid` padrão configurável via `.env` (`NOTIFICA_DEFAULT_WHATSAPP_INSTANCE_UUID`) e mutável via `$client->setDefaultWhatsAppInstanceUuid(...)`.
  - Métodos específicos: `sendWhatsAppText`, `sendWhatsAppMedia`, `sendWhatsAppTemplate`.
  - Método compatível `sendWhatsApp($to, $message, $instanceUuid)` como atalho direto para `sendWhatsAppText`.

#### 3. Push Notifications (`POST /api/v1/push/send`)
- **Prévia Documentada**: Canal em preparação na plataforma, com interface já padronizada.
- **Payload**:
  - `user_id` OU `device_token`: obrigatório fornecer um dos dois.
  - `title`: string obrigatória.
  - `body`: string obrigatória.
  - `image_url`: string opcional.
  - `data`: objeto / dicionário opcional de metadados.
  - `priority`: `'normal' | 'high'` (opcional, padrão `'normal'`).
- **Assinatura**:
  `sendPush(string $title, string $body, ?string $deviceToken = null, ?string $userId = null, ?string $imageUrl = null, ?array $data = null, string $priority = 'normal')`

#### 4. Email (`POST /api/v1/email/send`)
- **Payload**: `{ to, subject, body, from?, type? }`
- `from` padrão configurável via `.env` (`NOTIFICA_DEFAULT_EMAIL_FROM`), com mutador `$client->setDefaultEmailFrom(...)`.
- `type`: `'transactional' | 'marketing'` (padrão `'transactional'`).

#### 5. Descoberta de Remetentes (Senders)
- `listSenders()`: chama `GET /api/v1/senders` e retorna os `sms` sender IDs e instâncias `whatsapp`.
- `listEmailSenders()`: chama `GET /api/v1/email/senders` e retorna remetentes de e-mail disponíveis.

---

### B. Pagar.co.mz - Normalização e Topups
- **Normalização de URL e Caminho Canônico**: Normalizar `base_url` para aceitar `https://api.pagar.co.mz` ou `https://api.pagar.co.mz/api/v1` sem duplicar caminhos, garantindo cálculo exato do `canonicalPath` no HMAC-SHA256 (`/api/v1/payments`, etc.).
- **Topups de Carteira (`POST /api/v1/wallet/topups`)**:
  - Método `createTopup(array $data)` para financiamento de carteira.
- **Listagens Paginadas**:
  - `listPayments(array $query = [])`
  - `listPayouts(array $query = [])`
  - `listTransactions(array $query = [])`

---

## 3. Critérios de Aceitação
1. [x] Testes Pest do `packages/orionsuite-laravel` executam e cobrem os novos métodos (`sendPush`, `listSenders`, `sendWhatsAppText/Media/Template`, `sendEmail`, `createTopup`).
2. [x] Testes Vitest do `packages/orionsuite-ts` executam e passam com 100% de sucesso.
3. [x] Build TypeScript (`pnpm run build` ou `npm run build`) compila sem erros gerando ESM, CJS e `.d.ts`.
4. [x] Código PHP formatado com `vendor/bin/pint --format agent`.

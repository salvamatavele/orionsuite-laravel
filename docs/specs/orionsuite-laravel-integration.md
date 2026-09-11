# Especificação Técnica: Integração do OrionSuite (Pagar.co.mz) no DryAcademic

## 1. Visão Geral e Contexto
O DryAcademic já possui o gateway ZumboPay configurado. O objetivo desta funcionalidade é integrar o **OrionSuite Laravel** (`salvamatavele/orionsuite-laravel`), especificamente o módulo **Pagar.co.mz** (C2B M-Pesa e e-Mola com limites dinâmicos até 40.000 MZN), mantendo ambos os gateways no sistema com controle independente de ativação/silenciamento (*kill-switch*).

Como o utilizador silenciou o ZumboPay (`ZUMBOPAY_ENABLED=false`), o OrionSuite atuará como o gateway ativo para processar pagamentos online em tempo real.

---

## 2. Requisitos e Regras de Negócio

1. **Dual Gateway Coexistence (Sem remoção do ZumboPay)**:
   - O código do ZumboPay permanece intacto e funcional.
   - O sistema detecta o gateway ativo prioritário (se `PAGAR_ENABLED=true`, utiliza o OrionSuite Pagar; se `ZUMBOPAY_ENABLED=true`, utiliza o ZumboPay; se ambos ativos, prioriza o gateway configurado por padrão).
2. **Integração C2B com Pagar.co.mz**:
   - Disparo STK Push para M-Pesa (Vodacom) e e-Mola (Movitel) via Facade `Pagar::createPayment()`.
   - Normalização e validação do telemóvel e valor (piso 20 MZN, teto dinâmico padrão 40.000 MZN).
   - Armazenamento da referência e metadados no modelo `Pagamento` (`gateway = 'pagar'`, `gateway_ref`, etc.).
3. **Verificação e Polling de Status**:
   - Endpoint `GET /pagamentos/{pagamento}/status` consulta o estado na Pagar via `Pagar::getPayment()` ou `Pagar::getPaymentByReference()`.
   - Se confirmado com sucesso (`COMPLETED`), invoca a action `App\Actions\Financas\ConfirmarPagamento` e liquida a factura no sistema.
4. **Webhooks Seguros HMAC**:
   - Recepção do webhook oficial da Pagar em `/api/webhooks/pagar`.
   - Validação da assinatura HMAC do webhook e liquidação automática da factura.
5. **Interface do Utilizador (Frontend)**:
   - Atualização do modal de pagamento online para refletir o gateway ativo e os limites dinâmicos moçambicanos (20 MZN a 40.000 MZN).

---

## 3. Arquitetura e Componentes

### 3.1. Configuração e Variáveis de Ambiente
- Publicar `config/orionsuite.php` via `php artisan vendor:publish --tag=orionsuite-config`.
- Adicionar ao `.env`:
  ```env
  PAGAR_ENABLED=true
  PAGAR_API_BASE_URL="https://api.pagar.co.mz"
  PAGAR_API_KEY="" # Chave de API da Pagar
  PAGAR_SIGNING_SECRET="" # Secret de assinatura HMAC
  PAGAR_WEBHOOK_SECRET="" # Secret de webhook
  PAGAR_MIN_AMOUNT=20
  PAGAR_MAX_AMOUNT=40000
  ```

### 3.2. Serviço Abstrato / Orquestrador (`OrionPagarService`)
- Encapsula a lógica de comunicação com o OrionSuite Pagar.
- Converte os cêntimos do banco de dados (ex: `460000`) para o valor em Meticais (`4600.00 MZN`).
- Fornece métodos:
  - `iniciarStk(Pagamento $pagamento, string $phone, ?string $nome)`
  - `verificarEConfirmar(Pagamento $pagamento)`

### 3.3. `PagamentoOnlineController.php`
- Delegará para o gateway ativo (`OrionPagarService` quando `PAGAR_ENABLED=true` ou `ZumboPayService` quando `ZUMBOPAY_ENABLED=true`).
- Mantém as rotas existentes consumidas pelo frontend:
  - `POST /pagamentos/{pagamento}/iniciar-stk`
  - `GET /pagamentos/{pagamento}/status`

---

## 4. Critérios de Aceitação

- [ ] O ficheiro de configuração `config/orionsuite.php` está publicado e devidamente mapeado.
- [ ] O envio de STK Push via M-Pesa / e-Mola utiliza a Facade `Pagar` quando o ZumboPay está silenciado.
- [ ] A consulta de status atualiza o modelo `Pagamento` para `pago` e registra a liquidação via `ConfirmarPagamento`.
- [ ] Se o ZumboPay for reativado no `.env`, o sistema continua a conseguir utilizá-lo sem conflito.
- [ ] Testes de integração passam a verde.

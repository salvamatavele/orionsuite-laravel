<?php

namespace OrionSuite\Tests;

use Illuminate\Support\Facades\Http;
use OrionSuite\Identity\OrionKycClient;
use OrionSuite\Notifications\NotificaClient;
use OrionSuite\OrionSuiteManager;
use OrionSuite\Payments\PagarClient;
use OrionSuite\Support\PhoneNormalizer;
use Tests\TestCase;

class OrionSuiteTest extends TestCase
{
    public function test_phone_normalizer_detects_mozambican_operators(): void
    {
        $this->assertEquals('841234567', PhoneNormalizer::normalize('+258 84 123 4567'));
        $this->assertEquals('861234567', PhoneNormalizer::normalize('258861234567'));
        $this->assertEquals('821234567', PhoneNormalizer::normalize('0821234567'));

        $this->assertEquals('MPESA', PhoneNormalizer::detectPaymentMethod('841234567'));
        $this->assertEquals('MPESA', PhoneNormalizer::detectPaymentMethod('851234567'));
        $this->assertEquals('EMOLA', PhoneNormalizer::detectPaymentMethod('861234567'));
        $this->assertEquals('EMOLA', PhoneNormalizer::detectPaymentMethod('871234567'));
        $this->assertEquals('MKESH', PhoneNormalizer::detectPaymentMethod('821234567'));

        $this->assertEquals('258841234567', PhoneNormalizer::normalizeInternational('841234567'));
    }

    public function test_pagar_dynamic_amount_boundaries(): void
    {
        $pagar = new PagarClient(
            apiKey: 'key',
            signingSecret: 'secret',
            minAmount: 20.0,
            maxAmount: 40000.0
        );

        $this->assertEquals(20.0, $pagar->getMinAmount());
        $this->assertEquals(40000.0, $pagar->getMaxAmount());

        // Testar valor abaixo do piso
        $tooLow = $pagar->createPayment([
            'amountMzn' => 10,
            'phone' => '841234567',
        ]);
        $this->assertFalse($tooLow['success']);
        $this->assertEquals('AMOUNT_OUT_OF_BOUNDS', $tooLow['code']);

        // Testar valor acima do teto dinâmico
        $tooHigh = $pagar->createPayment([
            'amountMzn' => 45000,
            'phone' => '841234567',
        ]);
        $this->assertFalse($tooHigh['success']);
        $this->assertEquals('AMOUNT_OUT_OF_BOUNDS', $tooHigh['code']);

        // Atualizar teto dinamicamente em tempo de execução
        $pagar->setMaxAmount(100000.0);
        $this->assertEquals(100000.0, $pagar->getMaxAmount());
    }

    public function test_pagar_c2b_successful_payment(): void
    {
        Http::fake([
            'https://api.pagar.co.mz/api/v1/payments' => Http::response([
                'payment' => [
                    'id' => 'pay_123',
                    'status' => 'PROCESSING',
                    'reference' => 'PEDIDO-99',
                ],
            ], 202),
        ]);

        $pagar = new PagarClient(apiKey: 'key', signingSecret: 'secret');

        $result = $pagar->createPayment([
            'reference' => 'PEDIDO-99',
            'amountMzn' => 1500,
            'phone' => '841234567',
        ]);

        $this->assertTrue($result['success']);
        $this->assertEquals('pay_123', $result['paymentId']);
        $this->assertEquals('PROCESSING', $result['status']);
    }

    public function test_pagar_kill_switch_silences_requests(): void
    {
        Http::fake();

        $pagar = new PagarClient(
            apiKey: 'key',
            signingSecret: 'secret',
            enabled: false
        );

        $result = $pagar->createPayment([
            'reference' => 'PEDIDO-OFFLINE',
            'amountMzn' => 500,
            'phone' => '841234567',
        ]);

        $this->assertFalse($result['success']);
        $this->assertEquals('GATEWAY_DISABLED', $result['code']);
        Http::assertNothingSent();
    }

    public function test_notifica_sms_and_kill_switch(): void
    {
        Http::fake([
            'https://api.notifica.co.mz/api/v1/sms/send' => Http::response([
                'success' => true,
                'message_id' => 'msg_abc',
            ], 200),
        ]);

        $notifica = new NotificaClient('token');
        $res = $notifica->sendSms('841234567', 'Olá Moçambique!');

        $this->assertTrue($res['success']);
        $this->assertEquals('msg_abc', $res['message_id']);

        // Testar desativação
        $notifica->disable();
        $resDisabled = $notifica->sendSms('841234567', 'Outra mensagem');
        $this->assertFalse($resDisabled['success']);
        $this->assertTrue($resDisabled['disabled']);
    }

    public function test_orion_suite_manager_disable_all(): void
    {
        $pagar = new PagarClient('key', 'secret');
        $notifica = new NotificaClient('token');
        $kyc = new OrionKycClient('token');

        $manager = new OrionSuiteManager($pagar, $notifica, $kyc);

        $this->assertTrue($pagar->isEnabled());
        $this->assertTrue($notifica->isEnabled());
        $this->assertTrue($kyc->isEnabled());

        $manager->disableAll();

        $this->assertFalse($pagar->isEnabled());
        $this->assertFalse($notifica->isEnabled());
        $this->assertFalse($kyc->isEnabled());
    }

    public function test_notifica_whatsapp_endpoints_and_custom_instance_uuid(): void
    {
        Http::fake([
            'https://api.notifica.co.mz/api/v1/whatsapp/send-text' => Http::response([
                'success' => true,
                'data' => ['id' => 'wa_1', 'status' => 'pending'],
            ], 200),
            'https://api.notifica.co.mz/api/v1/whatsapp/send-media' => Http::response([
                'success' => true,
                'data' => ['id' => 'wa_2', 'status' => 'pending'],
            ], 200),
            'https://api.notifica.co.mz/api/v1/whatsapp/send-template' => Http::response([
                'success' => true,
                'data' => ['id' => 'wa_3', 'status' => 'pending'],
            ], 200),
        ]);

        $notifica = new NotificaClient(
            apiToken: 'token',
            defaultWhatsAppInstanceUuid: 'default-uuid-123'
        );

        $textRes = $notifica->sendWhatsAppText('841234567', 'Olá via WhatsApp!');
        $this->assertTrue($textRes['success']);

        // Custom instance UUID override
        $customRes = $notifica->sendWhatsAppText('841234567', 'Mensagem', 'custom-uuid-456');
        $this->assertTrue($customRes['success']);

        // Media
        $mediaRes = $notifica->sendWhatsAppMedia('841234567', 'https://exemplo.com/fatura.pdf', 'document', 'Factura de Propinas');
        $this->assertTrue($mediaRes['success']);

        // Template
        $tplRes = $notifica->sendWhatsAppTemplate('841234567', 'confirmacao_pagamento', ['Estudante 1', '1000 MZN']);
        $this->assertTrue($tplRes['success']);
    }

    public function test_notifica_push_notification_send(): void
    {
        Http::fake([
            'https://api.notifica.co.mz/api/v1/push/send' => Http::response([
                'success' => true,
                'data' => ['id' => 'push_99', 'delivered' => true],
            ], 200),
        ]);

        $notifica = new NotificaClient('token');

        // Testar validação: erro se token e user_id estiverem vazios
        $valErr = $notifica->sendPush('Alerta', 'Mensagem sem destinatário');
        $this->assertFalse($valErr['success']);

        // Envio com device_token
        $res = $notifica->sendPush(
            title: 'Nova Nota Lançada',
            body: 'A sua nota de Matemática foi publicada.',
            deviceToken: 'fcm-token-xyz',
            data: ['route' => '/estudante/notas'],
            priority: 'high'
        );

        $this->assertTrue($res['success']);
        $this->assertEquals('push_99', $res['data']['id']);
    }

    public function test_notifica_senders_and_email_senders_listing(): void
    {
        Http::fake([
            'https://api.notifica.co.mz/api/v1/senders' => Http::response([
                'success' => true,
                'data' => [
                    'sms' => [['sender_id' => 'DRYACADEMIC']],
                    'whatsapp' => [['instance_uuid' => 'wa-inst-uuid-1']],
                ],
            ], 200),
            'https://api.notifica.co.mz/api/v1/email/senders' => Http::response([
                'success' => true,
                'data' => [
                    ['email' => 'finance@universidade.ac.mz'],
                ],
            ], 200),
        ]);

        $notifica = new NotificaClient('token');

        $senders = $notifica->listSenders();
        $this->assertTrue($senders['success']);
        $this->assertEquals('DRYACADEMIC', $senders['data']['sms'][0]['sender_id']);

        $emailSenders = $notifica->listEmailSenders();
        $this->assertTrue($emailSenders['success']);
        $this->assertEquals('finance@universidade.ac.mz', $emailSenders['data'][0]['email']);
    }

    public function test_pagar_wallet_topup(): void
    {
        Http::fake([
            'https://api.pagar.co.mz/api/v1/wallet/topups' => Http::response([
                'topup' => [
                    'id' => 'top_123',
                    'status' => 'PENDING',
                    'reference' => 'TOPUP-001',
                    'amountMzn' => 5000,
                ],
            ], 202),
        ]);

        $pagar = new PagarClient(apiKey: 'key', signingSecret: 'secret');

        $result = $pagar->createTopup([
            'reference' => 'TOPUP-001',
            'amountMzn' => 5000,
            'phone' => '841234567',
            'method' => 'MPESA',
        ]);

        $this->assertEquals('top_123', $result['topup']['id']);
        $this->assertEquals('PENDING', $result['topup']['status']);
    }

    public function test_service_provider_publishes_react_component(): void
    {
        $componentPath = __DIR__.'/../resources/js/components/PagarModal.tsx';
        $this->assertFileExists($componentPath);
        $content = file_get_contents($componentPath);
        $this->assertStringContainsString('PagarModalProps', $content);
        $this->assertStringContainsString('Vodacom M-Pesa', $content);
        $this->assertStringContainsString('Movitel e-Mola', $content);
    }
}

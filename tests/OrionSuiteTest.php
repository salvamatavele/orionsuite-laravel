<?php

namespace OrionSuite\Tests;

use Illuminate\Support\Facades\Http;
use Tests\TestCase;
use OrionSuite\Identity\OrionKycClient;
use OrionSuite\Notifications\NotificaClient;
use OrionSuite\OrionSuiteManager;
use OrionSuite\Payments\PagarClient;
use OrionSuite\Support\PhoneNormalizer;

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
}

<?php

namespace OrionSuite\Laravel\Facades;

use Illuminate\Support\Facades\Facade;
use OrionSuite\Payments\PagarClient;

/**
 * @method static array createPayment(array $data)
 * @method static ?array getPayment(string $paymentId)
 * @method static ?array getPaymentByReference(string $reference)
 * @method static ?array getWallet()
 * @method static array createPayout(array $data)
 * @method static bool validateWebhook(string $rawBody, ?string $signatureHeader, ?string $eventId = null)
 * @method static bool isEnabled()
 * @method static PagarClient setEnabled(bool $enabled)
 * @method static PagarClient enable()
 * @method static PagarClient disable()
 * @method static float getMinAmount()
 * @method static PagarClient setMinAmount(float $min)
 * @method static float getMaxAmount()
 * @method static PagarClient setMaxAmount(float $max)
 *
 * @see PagarClient
 */
class Pagar extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return PagarClient::class;
    }
}

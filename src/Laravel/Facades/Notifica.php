<?php

namespace OrionSuite\Laravel\Facades;

use Illuminate\Support\Facades\Facade;
use OrionSuite\Notifications\NotificaClient;

/**
 * @method static array sendSms(string $to, string $message, ?string $sender = null)
 * @method static array sendWhatsApp(string $to, string $message, ?string $instanceId = null)
 * @method static array sendEmail(string $to, string $subject, string $htmlContent, ?string $fromName = null)
 * @method static bool isEnabled()
 * @method static NotificaClient enable()
 * @method static NotificaClient disable()
 *
 * @see NotificaClient
 */
class Notifica extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return NotificaClient::class;
    }
}

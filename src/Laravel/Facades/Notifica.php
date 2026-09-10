<?php

namespace OrionSuite\Laravel\Facades;

use Illuminate\Support\Facades\Facade;
use OrionSuite\Notifications\NotificaClient;

/**
 * @method static array sendSms(string $to, string $message, ?string $sender = null)
 * @method static array sendWhatsAppText(string $to, string $message, ?string $instanceUuid = null)
 * @method static array sendWhatsApp(string $to, string $message, ?string $instanceUuid = null)
 * @method static array sendWhatsAppMedia(string $to, string $mediaUrl, string $mediaType, ?string $caption = null, ?string $instanceUuid = null)
 * @method static array sendWhatsAppTemplate(string $to, string $templateName, array $params = [], string $language = 'pt', ?string $instanceUuid = null)
 * @method static array sendPush(string $title, string $body, ?string $deviceToken = null, ?string $userId = null, ?string $imageUrl = null, ?array $data = null, string $priority = 'normal')
 * @method static array sendEmail(string $to, string $subject, string $body, ?string $from = null, string $type = 'transactional')
 * @method static array listSenders()
 * @method static array listEmailSenders()
 * @method static string getDefaultSmsSender()
 * @method static NotificaClient setDefaultSmsSender(string $sender)
 * @method static ?string getDefaultWhatsAppInstanceUuid()
 * @method static NotificaClient setDefaultWhatsAppInstanceUuid(?string $uuid)
 * @method static ?string getDefaultEmailFrom()
 * @method static NotificaClient setDefaultEmailFrom(?string $from)
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

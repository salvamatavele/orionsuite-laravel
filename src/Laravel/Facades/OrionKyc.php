<?php

namespace OrionSuite\Laravel\Facades;

use Illuminate\Support\Facades\Facade;
use OrionSuite\Identity\OrionKycClient;

/**
 * @method static array generateTestToken(string $companyName, string $nuit, string $contactEmail, ?string $phone = null)
 * @method static ?array getMe()
 * @method static array verifyIdentity(string $documentFrontPath, string $documentBackPath, string $selfiePath, ?string $externalReference = null)
 * @method static ?array getVerification(string $verificationId)
 * @method static ?array getVerificationReport(string $verificationId)
 * @method static bool isEnabled()
 * @method static OrionKycClient enable()
 * @method static OrionKycClient disable()
 *
 * @see OrionKycClient
 */
class OrionKyc extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return OrionKycClient::class;
    }
}

<?php

namespace OrionSuite\Laravel\Facades;

use Illuminate\Support\Facades\Facade;
use OrionSuite\Identity\OrionKycClient;
use OrionSuite\Notifications\NotificaClient;
use OrionSuite\OrionSuiteManager;
use OrionSuite\Payments\PagarClient;

/**
 * @method static PagarClient pagar()
 * @method static NotificaClient notifica()
 * @method static OrionKycClient kyc()
 * @method static OrionSuiteManager disableAll()
 * @method static OrionSuiteManager enableAll()
 *
 * @see OrionSuiteManager
 */
class OrionSuite extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return OrionSuiteManager::class;
    }
}

<?php

namespace OrionSuite;

use OrionSuite\Identity\OrionKycClient;
use OrionSuite\Notifications\NotificaClient;
use OrionSuite\Payments\PagarClient;

class OrionSuiteManager
{
    public function __construct(
        protected PagarClient $pagar,
        protected NotificaClient $notifica,
        protected OrionKycClient $kyc,
    ) {}

    public function pagar(): PagarClient
    {
        return $this->pagar;
    }

    public function notifica(): NotificaClient
    {
        return $this->notifica;
    }

    public function kyc(): OrionKycClient
    {
        return $this->kyc;
    }

    public function disableAll(): self
    {
        $this->pagar->disable();
        $this->notifica->disable();
        $this->kyc->disable();

        return $this;
    }

    public function enableAll(): self
    {
        $this->pagar->enable();
        $this->notifica->enable();
        $this->kyc->enable();

        return $this;
    }
}

<?php

namespace OrionSuite\Enums;

enum PaymentMethod: string
{
    case Mpesa = 'MPESA';
    case Emola = 'EMOLA';
    case Mkesh = 'MKESH';
    case Card = 'CARD';
    case Paypal = 'PAYPAL';

    public function isSupportedDirectly(): bool
    {
        return in_array($this, [self::Mpesa, self::Emola], true);
    }
}

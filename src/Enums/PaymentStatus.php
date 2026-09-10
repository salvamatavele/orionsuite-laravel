<?php

namespace OrionSuite\Enums;

enum PaymentStatus: string
{
    case Pending = 'PENDING';
    case Processing = 'PROCESSING';
    case Paid = 'PAID';
    case Cancelled = 'CANCELLED';
    case Failed = 'FAILED';
    case ReconciliationRequired = 'RECONCILIATION_REQUIRED';

    public function isPaid(): bool
    {
        return $this === self::Paid;
    }

    public function isPending(): bool
    {
        return in_array($this, [self::Pending, self::Processing], true);
    }

    public function isTerminal(): bool
    {
        return in_array($this, [self::Paid, self::Cancelled, self::Failed], true);
    }
}

<?php

namespace OrionSuite\Enums;

enum KycDecision: string
{
    case Approved = 'approved';
    case Rejected = 'rejected';
    case PendingReview = 'pending_review';

    public function isApproved(): bool
    {
        return $this === self::Approved;
    }
}

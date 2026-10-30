<?php

namespace App;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum InvoiceStatus: string implements HasLabel, HasColor
{
    case ReadyForReview = 'READY_FOR_REVIEW';
    case NeedsAttention = 'NEEDS_ATTENTION';
    case Approved = 'APPROVED';

    public function getLabel(): string
    {
        return match ($this) {
            self::ReadyForReview => 'Ready for review',
            self::NeedsAttention => 'Needs attention',
            self::Approved => 'Approved',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::ReadyForReview => 'info',
            self::NeedsAttention => 'warning',
            self::Approved => 'success',
        };
    }
}

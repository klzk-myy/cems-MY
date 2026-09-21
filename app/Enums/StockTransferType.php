<?php

namespace App\Enums;

/**
 * Backing values keep their historical TitleCase form — that is what the
 * stock_transfers.type column stores.
 */
enum StockTransferType: string
{
    case Standard = 'Standard';
    case Emergency = 'Emergency';
    case Scheduled = 'Scheduled';
    case Return = 'Return';

    public function label(): string
    {
        return $this->value;
    }
}

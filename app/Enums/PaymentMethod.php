<?php

namespace App\Enums;

enum PaymentMethod: string
{
    case CashOnHandover = 'cash_on_handover';
    case OnlineTransfer = 'online_transfer';

    public function label(): string
    {
        return match ($this) {
            self::CashOnHandover => 'Cash on handover',
            self::OnlineTransfer => "Online payment (seller's QR)",
        };
    }

    public function labelFil(): string
    {
        return match ($this) {
            self::CashOnHandover => 'Cash sa pag-abot',
            self::OnlineTransfer => 'Online na bayad (QR ng nagbebenta)',
        };
    }
}

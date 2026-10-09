<?php

namespace App\Enums;

enum PaymentWallet: string
{
    case Gcash = 'gcash';
    case Maya = 'maya';
    case BankQrph = 'bank_qrph';

    public function label(): string
    {
        return match ($this) {
            self::Gcash => 'GCash',
            self::Maya => 'Maya',
            self::BankQrph => 'Bank QR Ph',
        };
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $wallet): array => [$wallet->value => $wallet->label()])
            ->all();
    }
}

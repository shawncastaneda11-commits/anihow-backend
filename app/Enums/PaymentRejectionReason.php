<?php

namespace App\Enums;

enum PaymentRejectionReason: string
{
    case NotReceived = 'not_received';
    case WrongAmount = 'wrong_amount';
    case WrongReference = 'wrong_reference';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::NotReceived => 'Payment not received',
            self::WrongAmount => 'Wrong amount',
            self::WrongReference => 'Wrong reference number',
            self::Other => 'Other',
        };
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $reason): array => [$reason->value => $reason->label()])
            ->all();
    }
}

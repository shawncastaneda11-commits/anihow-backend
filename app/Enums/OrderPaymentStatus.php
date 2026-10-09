<?php

namespace App\Enums;

enum OrderPaymentStatus: string
{
    case AwaitingPayment = 'awaiting_payment';
    case PaymentSent = 'payment_sent';
    case Paid = 'paid';
    case RefundDue = 'refund_due';
    case Refunded = 'refunded';
    case NotTracked = 'not_tracked';

    public function label(): string
    {
        return match ($this) {
            self::AwaitingPayment => 'Awaiting payment',
            self::PaymentSent => 'Payment sent',
            self::Paid => 'Paid',
            self::RefundDue => 'Refund due',
            self::Refunded => 'Refunded',
            self::NotTracked => 'Not tracked',
        };
    }

    /**
     * Cash and pre-tracking online orders stay on the old path.
     */
    public function isTracked(): bool
    {
        return $this !== self::NotTracked;
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $status): array => [$status->value => $status->label()])
            ->all();
    }
}

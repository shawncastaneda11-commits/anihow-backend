<?php

namespace App\Enums;

enum ExportType: string
{
    case OrderLedger = 'order_ledger';
    case Analytics = 'analytics';
    case AnalyticsYearly = 'analytics_yearly';

    public function label(): string
    {
        return match ($this) {
            self::OrderLedger => 'Order ledger',
            self::Analytics => 'Analytics',
            self::AnalyticsYearly => 'Yearly analytics',
        };
    }
}

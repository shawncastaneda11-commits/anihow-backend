<?php

namespace App\Support;

class PaymentReference
{
    /**
     * Uppercase, with spaces and dashes removed, so "abc 12-3" and "ABC123" match.
     */
    public static function normalize(string $value): string
    {
        return strtoupper((string) preg_replace('/[\s\-]+/', '', trim($value)));
    }
}

<?php

namespace App\Support;

class FarmPin
{
    public const HELPER = 'Pin the place buyers should go to (the pickup point), not a private home. In Google Maps, long-press that spot, then copy the two numbers.';

    /**
     * Both coordinates, or neither. The range is the Philippines.
     *
     * @return array<string, list<string>>
     */
    public static function rules(): array
    {
        return [
            'latitude' => ['nullable', 'numeric', 'between:4,22', 'required_with:longitude'],
            'longitude' => ['nullable', 'numeric', 'between:116,127', 'required_with:latitude'],
        ];
    }

    /**
     * Query parameters only. Never persisted.
     *
     * @return array<string, list<string>>
     */
    public static function nearRules(): array
    {
        return [
            'near_lat' => ['nullable', 'numeric', 'between:4,22', 'required_with:near_lng'],
            'near_lng' => ['nullable', 'numeric', 'between:116,127', 'required_with:near_lat'],
        ];
    }
}

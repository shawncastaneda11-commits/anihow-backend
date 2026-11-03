<?php

namespace App\Support;

/**
 * A multipart form can only carry text, so the app sends its switches as the
 * words "true" and "false" when a photo is attached. Laravel's boolean rule
 * takes 1 and 0 but not those words, so they are turned into real booleans
 * before validation. Anything else is left alone for the rule to refuse.
 */
class FormBooleans
{
    /**
     * @param  array<string, mixed>  $input
     * @param  list<string>  $keys
     * @return array<string, bool>
     */
    public static function fromWords(array $input, array $keys): array
    {
        $booleans = [];

        foreach ($keys as $key) {
            $value = $input[$key] ?? null;

            if (! is_string($value)) {
                continue;
            }

            $word = strtolower(trim($value));

            if ($word === 'true' || $word === 'false') {
                $booleans[$key] = $word === 'true';
            }
        }

        return $booleans;
    }
}

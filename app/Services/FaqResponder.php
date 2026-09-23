<?php

namespace App\Services;

use App\Enums\Role;
use App\Models\User;
use App\Support\FaqCatalog;

class FaqResponder
{
    /**
     * @return array{answer: string, matched_id: ?string, suggestions: list<array{id: string, label: string}>}
     */
    public function ask(User $user, string $question): array
    {
        $role = $this->primaryAppRole($user);
        $suggestions = FaqCatalog::chipsForRole($role);
        $normalized = $this->normalize($question);

        if ($normalized === '') {
            return [
                'answer' => $this->fallback(),
                'matched_id' => null,
                'suggestions' => $suggestions,
            ];
        }

        $bestId = null;
        $bestScore = 0;
        $bestAnswer = null;

        foreach (FaqCatalog::intents() as $intent) {
            if (! in_array($role, $intent['roles'], true)) {
                continue;
            }

            $score = $this->score($normalized, $intent['keywords'], $intent['label']);

            if ($score > $bestScore) {
                $bestScore = $score;
                $bestId = $intent['id'];
                $bestAnswer = $intent['answer'];
            }
        }

        if ($bestScore < 1 || $bestAnswer === null) {
            return [
                'answer' => $this->fallback(),
                'matched_id' => null,
                'suggestions' => $suggestions,
            ];
        }

        return [
            'answer' => $bestAnswer,
            'matched_id' => $bestId,
            'suggestions' => $suggestions,
        ];
    }

    /**
     * @return list<array{id: string, label: string}>
     */
    public function chips(User $user): array
    {
        return FaqCatalog::chipsForRole($this->primaryAppRole($user));
    }

    private function primaryAppRole(User $user): string
    {
        if ($user->hasRole(Role::FarmerSeller->value)) {
            return Role::FarmerSeller->value;
        }

        if ($user->hasRole(Role::Buyer->value)) {
            return Role::Buyer->value;
        }

        // Panel roles can open Help in the app only if they somehow reach it;
        // default chips stay buyer-safe and never expose crop-care prose.
        return Role::Buyer->value;
    }

    private function normalize(string $question): string
    {
        $question = mb_strtolower(trim($question));
        $question = preg_replace('/[^\p{L}\p{N}\s]+/u', ' ', $question) ?? '';
        $question = preg_replace('/\s+/u', ' ', $question) ?? '';

        return trim($question);
    }

    /**
     * @param  list<string>  $keywords
     */
    private function score(string $normalized, array $keywords, string $label): int
    {
        $score = 0;
        $labelNormalized = $this->normalize($label);

        if ($labelNormalized !== '' && str_contains($normalized, $labelNormalized)) {
            $score += 5;
        }

        foreach ($keywords as $keyword) {
            $needle = $this->normalize($keyword);

            if ($needle === '') {
                continue;
            }

            if ($normalized === $needle) {
                $score += 4;
            } elseif (str_contains($normalized, $needle)) {
                $score += 3;
            } else {
                foreach (explode(' ', $needle) as $token) {
                    if (mb_strlen($token) < 3) {
                        continue;
                    }

                    if (str_contains($normalized, $token)) {
                        $score += 1;
                    }
                }
            }
        }

        return $score;
    }

    private function fallback(): string
    {
        return 'I can answer AniHow how-to questions. For this order, use Chat with the stall.';
    }
}

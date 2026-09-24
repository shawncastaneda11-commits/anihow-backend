<?php

namespace App\Services;

use App\Enums\Role;
use App\Models\FaqEntry;
use App\Models\User;
use Illuminate\Support\Collection;

class FaqResponder
{
    /**
     * @return array{answer: string, matched_id: ?string, suggestions: list<array{id: string, label: string}>}
     */
    public function ask(User $user, string $question, string $locale = 'en'): array
    {
        $intents = $this->resolvedIntents($user);
        $suggestions = $this->chipsFrom($intents, $locale);
        $normalized = $this->normalize($question);

        if ($normalized === '') {
            return [
                'answer' => $this->fallback($this->primaryAppRole($user), $locale),
                'matched_id' => null,
                'suggestions' => $suggestions,
            ];
        }

        $bestId = null;
        $bestScore = 0;
        $bestAnswer = null;

        foreach ($intents as $intent) {
            $score = $this->score(
                $normalized,
                $intent->keywords ?? [],
                $intent->localized($locale, 'label'),
            );

            if ($score > $bestScore) {
                $bestScore = $score;
                $bestId = $intent->intent_key;
                $bestAnswer = $intent->localized($locale, 'answer');
            }
        }

        if ($bestScore < 1 || $bestAnswer === null) {
            return [
                'answer' => $this->fallback($this->primaryAppRole($user), $locale),
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
    public function chips(User $user, string $locale = 'en'): array
    {
        return $this->chipsFrom($this->resolvedIntents($user), $locale);
    }

    /**
     * Active system rows for the user's role, then farm overrides (same
     * intent_key wins) and farm-only intents. Buyers have no farm, so they
     * receive system rows only.
     *
     * @return Collection<int, FaqEntry>
     */
    public function resolvedIntents(User $user): Collection
    {
        $role = $this->primaryAppRole($user);

        $resolved = FaqEntry::query()
            ->active()
            ->systemWide()
            ->forRole($role)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->keyBy('intent_key');

        if ($user->farm_id === null) {
            return $resolved->values();
        }

        $farmEntries = FaqEntry::query()
            ->active()
            ->forFarm((int) $user->farm_id)
            ->forRole($role)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        foreach ($farmEntries as $entry) {
            $resolved->put($entry->intent_key, $entry);
        }

        return $resolved->values();
    }

    /**
     * @param  Collection<int, FaqEntry>  $intents
     * @return list<array{id: string, label: string}>
     */
    private function chipsFrom(Collection $intents, string $locale): array
    {
        return $intents
            ->map(fn (FaqEntry $intent): array => [
                'id' => $intent->intent_key,
                'label' => $intent->localized($locale, 'label'),
            ])
            ->values()
            ->all();
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

    private function fallback(string $role, string $locale = 'en'): string
    {
        if ($role === Role::FarmerSeller->value) {
            return $locale === 'fil'
                ? 'Pumili ng tanong sa ibaba. Para sa isang order, buksan ang Orders at i-tap ang Chat with buyer.'
                : 'Tap a question below. For one order, open Orders and tap Chat with buyer.';
        }

        return $locale === 'fil'
            ? 'Pumili ng tanong sa ibaba. Para sa isang order, buksan ang order at i-tap ang Chat with stall.'
            : 'Tap a question below. For one order, open the order and tap Chat with stall.';
    }
}

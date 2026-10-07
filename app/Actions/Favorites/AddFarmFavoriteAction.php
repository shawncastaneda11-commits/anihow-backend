<?php

namespace App\Actions\Favorites;

use App\Models\Farm;
use App\Models\FarmFavorite;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class AddFarmFavoriteAction
{
    public function handle(User $buyer, Farm $farm): FarmFavorite
    {
        if (! $farm->is_active) {
            throw ValidationException::withMessages([
                'farm_id' => 'That farm is not available.',
            ]);
        }

        return FarmFavorite::query()->firstOrCreate([
            'buyer_id' => $buyer->id,
            'farm_id' => $farm->id,
        ]);
    }
}

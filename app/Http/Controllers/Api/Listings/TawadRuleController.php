<?php

namespace App\Http\Controllers\Api\Listings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Listings\StoreTawadRuleRequest;
use App\Http\Resources\Api\TawadRuleResource;
use App\Models\Listing;
use App\Models\TawadRule;
use Illuminate\Http\JsonResponse;

/**
 * One active rule per listing. Creating a new rule ends the previous one
 * rather than stacking, because two active rules on one listing have no
 * defined interaction and the schema cannot forbid it.
 */
class TawadRuleController extends Controller
{
    public function store(StoreTawadRuleRequest $request, Listing $listing): JsonResponse
    {
        $this->authorize('create', TawadRule::class);
        $this->authorize('update', $listing);

        $listing->tawadRules()
            ->where('is_active', true)
            ->update(['is_active' => false, 'ended_at' => now()]);

        $rule = $listing->tawadRules()->create($request->ruleAttributes());

        return (new TawadRuleResource($rule))
            ->additional(['message' => 'Tawad rule saved.'])
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Ending a rule does not change any order already confirmed. Those keep
     * the price and tawad amount they were confirmed at.
     */
    public function destroy(Listing $listing, TawadRule $tawadRule): JsonResponse
    {
        $this->authorize('delete', $tawadRule);

        abort_unless($tawadRule->listing_id === $listing->id, 404);

        $tawadRule->update(['is_active' => false, 'ended_at' => now()]);

        return response()->json(['message' => 'Tawad rule ended.']);
    }
}

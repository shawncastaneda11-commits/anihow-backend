<?php

namespace App\Filament\Resources\Listings\Pages;

use App\Filament\Resources\Listings\ListingResource;
use Filament\Resources\Pages\CreateRecord;

class CreateListing extends CreateRecord
{
    protected static string $resource = ListingResource::class;
}

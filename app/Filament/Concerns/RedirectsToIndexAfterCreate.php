<?php

namespace App\Filament\Concerns;

/**
 * After a record is created, go back to the resource's list instead of
 * opening the new record, so the create form closes once it is saved.
 */
trait RedirectsToIndexAfterCreate
{
    protected function getRedirectUrl(): string
    {
        return $this->getResourceUrl('index');
    }
}

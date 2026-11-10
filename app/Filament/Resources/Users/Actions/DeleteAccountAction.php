<?php

namespace App\Filament\Resources\Users\Actions;

use App\Models\User;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Illuminate\Auth\Access\Response;

/**
 * Delete an account, but not while it has orders in progress: the other
 * person in the order would be left without anyone to finish it. Used in the
 * row menu, the edit page and, through recordResponse(), the bulk delete.
 */
class DeleteAccountAction extends DeleteAction
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->before(function (DeleteAction $action, User $record): void {
            $reason = $record->deletionBlocker();

            if ($reason === null) {
                return;
            }

            Notification::make()->title('Not deleted.')->body($reason)->danger()->send();
            $action->halt();
        });
    }

    /**
     * Whether the signed-in office may delete this one of several selected accounts.
     */
    public static function recordResponse(User $record): Response
    {
        if (! (auth()->user()?->can('delete', $record) ?? false)) {
            return Response::deny();
        }

        return $record->hasOpenMarketplaceOrders()
            ? Response::deny('Accounts with orders in progress or a refund due were not deleted.')
            : Response::allow();
    }
}

<?php

namespace App\Http\Controllers\Api\Auth;

use App\Actions\Privacy\ExportOwnDataAction;
use App\Enums\Permission;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExportOwnDataController extends Controller
{
    public function __invoke(Request $request, ExportOwnDataAction $export): StreamedResponse
    {
        abort_unless($request->user()?->can(Permission::ExportOwnData->value), 403);

        $payload = $export->handle($request->user());
        $filename = 'anihow-my-data-'.now()->toDateString().'.json';

        return response()->streamDownload(
            function () use ($payload): void {
                echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            },
            $filename,
            ['Content-Type' => 'application/json'],
        );
    }
}

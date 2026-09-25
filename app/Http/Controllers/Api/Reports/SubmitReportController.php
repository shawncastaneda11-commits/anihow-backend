<?php

namespace App\Http\Controllers\Api\Reports;

use App\Actions\Reports\SubmitReportAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Reports\SubmitReportRequest;
use App\Http\Resources\Api\ReportResource;
use Illuminate\Http\JsonResponse;

class SubmitReportController extends Controller
{
    public function __invoke(SubmitReportRequest $request, SubmitReportAction $submit): JsonResponse
    {
        $report = $submit->handle(
            $request->user(),
            $request->targetType(),
            $request->targetId(),
            $request->reason(),
            $request->validated('details'),
        );

        return (new ReportResource($report))
            ->response()
            ->setStatusCode(201);
    }
}

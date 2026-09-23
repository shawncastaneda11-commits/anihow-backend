<?php

namespace App\Http\Controllers\Api\Faq;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Faq\AskFaqRequest;
use App\Services\FaqResponder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FaqController extends Controller
{
    public function index(Request $request, FaqResponder $responder): JsonResponse
    {
        return response()->json([
            'data' => [
                'suggestions' => $responder->chips($request->user()),
            ],
        ]);
    }

    public function ask(AskFaqRequest $request, FaqResponder $responder): JsonResponse
    {
        $result = $responder->ask($request->user(), $request->validated('question'));

        return response()->json([
            'data' => $result,
        ]);
    }
}

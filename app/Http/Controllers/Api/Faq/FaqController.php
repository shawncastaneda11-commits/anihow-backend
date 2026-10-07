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
        $locale = $this->locale($request);

        return response()->json([
            'data' => [
                'suggestions' => $responder->chips($request->user(), $locale),
            ],
        ]);
    }

    public function ask(AskFaqRequest $request, FaqResponder $responder): JsonResponse
    {
        $result = $responder->ask($request->user(), $request->validated('question'), $this->locale($request));

        return response()->json([
            'data' => $result,
        ]);
    }

    private function locale(Request $request): string
    {
        $header = strtolower((string) $request->header('Accept-Language', 'en'));

        if (str_starts_with($header, 'fil') || str_starts_with($header, 'tl')) {
            return 'fil';
        }

        return 'en';
    }
}

<?php

namespace App\Exceptions;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class Handler
{
    /**
     * Render an exception into an HTTP response.
     */
    public function render(Request $request, \Throwable $exception): JsonResponse
    {
        Log::error('Unhandled exception', [
            'message' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString(),
            'request' => $request->all(),
        ]);

        return response()->json([
            'status' => 'error',
            'message' => $exception->getMessage(),
        ], 500);
    }
}

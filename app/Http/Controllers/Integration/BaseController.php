<?php

namespace App\Http\Controllers\Integration;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

abstract class BaseController extends Controller
{
    protected function ok($data = null, ?string $message = null, array $extra = []): JsonResponse
    {
        $response = ['success' => true];
        if ($message) {
            $response['message'] = $message;
        }
        if (! is_null($data)) {
            $response['data'] = $data;
        }

        return response()->json(array_merge($response, $extra));
    }

    protected function created($data = null, string $message = 'Created successfully.'): JsonResponse
    {
        return response()->json(['success' => true, 'message' => $message, 'data' => $data], 201);
    }

    protected function noContent(): JsonResponse
    {
        return response()->json(['success' => true], 204);
    }

    protected function error(string $message, int $code = 400, array $extra = []): JsonResponse
    {
        return response()->json(array_merge(['success' => false, 'message' => $message], $extra), $code);
    }

    protected function notFound(string $message = 'Resource not found.'): JsonResponse
    {
        return $this->error($message, 404);
    }
}

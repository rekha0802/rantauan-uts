<?php

namespace App\Http;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class Problem
{
    public static function response(
        Request $request,
        int $status,
        string $slug,
        string $title,
        string $detail,
        array $tambahan = [],
    ): JsonResponse {
        return response()->json(array_merge([
            'type' => "https://rantauan.test/problem/{$slug}",
            'title' => $title,
            'status' => $status,
            'detail' => $detail,
            'instance' => '/'.$request->path(),
        ], $tambahan), $status, [
            'Content-Type' => 'application/problem+json',
        ]);
    }
}

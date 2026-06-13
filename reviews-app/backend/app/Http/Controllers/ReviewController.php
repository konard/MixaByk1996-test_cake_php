<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReviewController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $organization = $request->user()->organization;

        if (!$organization) {
            return response()->json(['message' => 'No organization configured'], 404);
        }

        $perPage = 50;
        $page = max(1, (int) $request->query('page', 1));

        $paginator = $organization->reviews()
            ->orderByDesc('published_at')
            ->paginate($perPage, ['external_id', 'author', 'rating', 'text', 'published_at'], 'page', $page);

        return response()->json([
            'data' => $paginator->items(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'total' => $paginator->total(),
                'per_page' => $paginator->perPage(),
            ],
        ]);
    }
}

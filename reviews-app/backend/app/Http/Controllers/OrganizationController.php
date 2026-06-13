<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreOrganizationRequest;
use App\Models\Organization;
use App\Services\YandexMapsParser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class OrganizationController extends Controller
{
    public function __construct(private readonly YandexMapsParser $parser)
    {
    }

    public function show(Request $request): JsonResponse
    {
        $organization = $request->user()->organization;

        if (!$organization) {
            return response()->json(null);
        }

        return response()->json($organization->only([
            'id', 'yandex_url', 'name', 'average_rating',
            'rating_count', 'review_count', 'last_parsed_at',
        ]));
    }

    public function store(StoreOrganizationRequest $request): JsonResponse
    {
        $url = $request->validated('url');
        $user = $request->user();

        try {
            $data = $this->parser->fetchOrganizationData($url);
        } catch (\Exception $e) {
            Log::error('Yandex Maps parsing failed', ['url' => $url, 'error' => $e->getMessage()]);
            return response()->json(['message' => 'Failed to fetch organization data: ' . $e->getMessage()], 422);
        }

        $organization = Organization::updateOrCreate(
            ['user_id' => $user->id],
            [
                'yandex_url' => $url,
                'name' => $data['name'],
                'average_rating' => $data['average_rating'],
                'rating_count' => $data['rating_count'],
                'review_count' => $data['review_count'],
                'last_parsed_at' => now(),
            ]
        );

        $organization->reviews()->delete();

        $reviews = array_map(fn($r) => [
            'organization_id' => $organization->id,
            'external_id' => $r['id'],
            'author' => $r['author'],
            'rating' => $r['rating'],
            'text' => $r['text'],
            'published_at' => $r['date'],
            'created_at' => now(),
            'updated_at' => now(),
        ], $data['reviews']);

        foreach (array_chunk($reviews, 100) as $chunk) {
            \App\Models\Review::insert($chunk);
        }

        return response()->json($organization->only([
            'id', 'yandex_url', 'name', 'average_rating',
            'rating_count', 'review_count', 'last_parsed_at',
        ]), 201);
    }
}

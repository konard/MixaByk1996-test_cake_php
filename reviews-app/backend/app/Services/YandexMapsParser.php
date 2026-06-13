<?php

namespace App\Services;

use GuzzleHttp\Client;
use GuzzleHttp\Cookie\CookieJar;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Facades\Log;

class YandexMapsParser
{
    private Client $client;
    private CookieJar $cookieJar;

    private const PAGE_SIZE = 30;
    private const MAX_REVIEWS = 600;
    private const REQUEST_DELAY_US = 400000;

    private const BASE_URL = 'https://yandex.ru/maps/';
    private const API_URL = 'https://yandex.ru/maps/api/business/fetchReviews';

    private const HEADERS = [
        'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
        'Accept' => 'application/json, text/javascript, */*; q=0.01',
        'Accept-Language' => 'ru-RU,ru;q=0.9',
        'X-Requested-With' => 'XMLHttpRequest',
    ];

    public function __construct()
    {
        $this->cookieJar = new CookieJar();
        $this->client = new Client([
            'timeout' => 30,
            'verify' => false,
            'cookies' => $this->cookieJar,
            'headers' => self::HEADERS,
        ]);
    }

    public function fetchOrganizationData(string $url): array
    {
        $orgId = $this->extractOrgId($url);
        $csrfToken = $this->obtainCsrfToken($url);

        $firstPage = $this->fetchReviewsPage($orgId, $csrfToken, 0);
        $orgInfo = $this->parseOrganizationInfo($firstPage);
        $reviews = $this->parseReviews($firstPage);

        $total = min($orgInfo['review_count'], self::MAX_REVIEWS);
        $fetched = count($reviews);

        while ($fetched < $total) {
            usleep(self::REQUEST_DELAY_US);

            $page = $this->fetchReviewsPage($orgId, $csrfToken, $fetched);
            $batch = $this->parseReviews($page);

            if (empty($batch)) {
                break;
            }

            $reviews = array_merge($reviews, $batch);
            $fetched = count($reviews);
        }

        return array_merge($orgInfo, ['reviews' => array_values($reviews)]);
    }

    private function extractOrgId(string $url): string
    {
        if (preg_match('#/org/[^/?]+/(\d+)#', $url, $matches)) {
            return $matches[1];
        }

        if (preg_match('#/org/(\d+)#', $url, $matches)) {
            return $matches[1];
        }

        throw new \InvalidArgumentException('Cannot extract organization ID from URL: ' . $url);
    }

    private function obtainCsrfToken(string $orgUrl): string
    {
        try {
            $response = $this->client->get($orgUrl, [
                'headers' => array_merge(self::HEADERS, [
                    'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,*/*;q=0.8',
                    'Referer' => self::BASE_URL,
                ]),
            ]);

            $html = (string) $response->getBody();

            if (preg_match('/"csrfToken":"([^"]+)"/', $html, $matches)) {
                return $matches[1];
            }

            if (preg_match('/csrfToken["\s]*:["\s]*"([^"]+)"/', $html, $matches)) {
                return $matches[1];
            }

            Log::warning('CSRF token not found in page, using empty token');
            return '';
        } catch (GuzzleException $e) {
            throw new \RuntimeException('Failed to load organization page: ' . $e->getMessage());
        }
    }

    private function fetchReviewsPage(string $orgId, string $csrfToken, int $skip): array
    {
        $params = [
            'businessId' => $orgId,
            'csrfToken' => $csrfToken,
            'ajax' => '1',
            'skip' => $skip,
            'limit' => self::PAGE_SIZE,
            'ranking' => 'by_time',
            'lang' => 'ru_RU',
        ];

        try {
            $response = $this->client->get(self::API_URL, [
                'query' => $params,
                'headers' => array_merge(self::HEADERS, [
                    'Referer' => self::BASE_URL,
                ]),
            ]);

            $body = (string) $response->getBody();
            $data = json_decode($body, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new \RuntimeException('Invalid JSON response from Yandex Maps API');
            }

            if (isset($data['error'])) {
                throw new \RuntimeException('Yandex API error: ' . ($data['error']['message'] ?? 'Unknown error'));
            }

            return $data;
        } catch (GuzzleException $e) {
            throw new \RuntimeException('HTTP request to Yandex Maps failed: ' . $e->getMessage());
        }
    }

    private function parseOrganizationInfo(array $data): array
    {
        $business = $data['properties']['CompanyMetaData'] ?? $data['businessData'] ?? [];
        $rating = $business['rating'] ?? $business['Rating'] ?? [];

        $name = $business['name'] ?? $data['name'] ?? 'Unknown';
        $avgRating = (float) ($rating['value'] ?? $rating['Value'] ?? 0);
        $ratingCount = (int) ($rating['ratingCount'] ?? $rating['RatingCount'] ?? 0);
        $reviewCount = (int) ($rating['reviewCount'] ?? $rating['ReviewCount'] ?? 0);

        if ($reviewCount === 0) {
            $reviewCount = (int) ($data['totalReviewCount'] ?? $data['total'] ?? 0);
        }

        return [
            'name' => $name,
            'average_rating' => $avgRating,
            'rating_count' => $ratingCount,
            'review_count' => $reviewCount,
        ];
    }

    private function parseReviews(array $data): array
    {
        $rawReviews = $data['properties']['CompanyMetaData']['reviews']
            ?? $data['reviews']
            ?? $data['data']
            ?? [];

        $reviews = [];
        foreach ($rawReviews as $review) {
            $id = $review['reviewId'] ?? $review['id'] ?? uniqid('r_', true);

            $authorName = $review['authorName']
                ?? $review['author']['name']
                ?? ($review['isAnonymous'] ?? false ? 'Anonymous' : 'Unknown');

            $date = null;
            if (!empty($review['date'])) {
                $date = date('Y-m-d H:i:s', is_numeric($review['date'])
                    ? (int) $review['date']
                    : strtotime($review['date']));
            } elseif (!empty($review['updatedTime'])) {
                $date = date('Y-m-d H:i:s', strtotime($review['updatedTime']));
            }

            $reviews[] = [
                'id' => (string) $id,
                'author' => $authorName,
                'rating' => (int) ($review['rating'] ?? 0),
                'text' => $review['text'] ?? '',
                'date' => $date,
            ];
        }

        return $reviews;
    }
}

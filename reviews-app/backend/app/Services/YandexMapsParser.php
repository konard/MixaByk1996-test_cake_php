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
    private const REQUEST_DELAY_US = 500000;

    private const USER_AGENTS = [
        'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36',
        'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36',
        'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36',
    ];

    private const BASE_URL = 'https://yandex.ru/maps/';

    private string $userAgent;

    public function __construct()
    {
        $this->userAgent = self::USER_AGENTS[array_rand(self::USER_AGENTS)];
        $this->cookieJar = new CookieJar();
        $this->client = new Client([
            'timeout' => 30,
            'connect_timeout' => 10,
            'verify' => false,
            'cookies' => $this->cookieJar,
            'allow_redirects' => ['max' => 5, 'track_redirects' => true],
            'headers' => $this->buildBrowserHeaders(),
        ]);
    }

    public function fetchOrganizationData(string $url): array
    {
        $orgId = $this->extractOrgId($url);

        Log::info('YandexMapsParser: starting fetch', ['orgId' => $orgId, 'url' => $url]);

        $csrfToken = $this->obtainCsrfToken($url);

        $firstPage = $this->fetchReviewsPage($orgId, $csrfToken, 0);
        $orgInfo = $this->parseOrganizationInfo($firstPage);
        $reviews = $this->parseReviews($firstPage);

        $total = min($orgInfo['review_count'] ?: count($reviews), self::MAX_REVIEWS);
        $fetched = count($reviews);

        Log::info('YandexMapsParser: first page fetched', [
            'orgInfo' => $orgInfo,
            'firstPageReviews' => $fetched,
            'total' => $total,
        ]);

        while ($fetched < $total) {
            usleep(self::REQUEST_DELAY_US);

            try {
                $page = $this->fetchReviewsPage($orgId, $csrfToken, $fetched);
                $batch = $this->parseReviews($page);
            } catch (\Exception $e) {
                Log::warning('YandexMapsParser: page fetch failed, stopping', [
                    'skip' => $fetched,
                    'error' => $e->getMessage(),
                ]);
                break;
            }

            if (empty($batch)) {
                break;
            }

            $reviews = array_merge($reviews, $batch);
            $fetched = count($reviews);
        }

        Log::info('YandexMapsParser: completed', ['totalReviewsFetched' => $fetched]);

        return array_merge($orgInfo, ['reviews' => array_values($reviews)]);
    }

    private function extractOrgId(string $url): string
    {
        // https://yandex.ru/maps/org/name/123456789/
        if (preg_match('#/org/[^/?#]+/(\d+)#', $url, $m)) {
            return $m[1];
        }
        // https://yandex.ru/maps/org/123456789/
        if (preg_match('#/org/(\d+)#', $url, $m)) {
            return $m[1];
        }
        // ?businessId=123456789
        if (preg_match('#[?&]businessId=(\d+)#', $url, $m)) {
            return $m[1];
        }

        throw new \InvalidArgumentException(
            'Cannot extract organization ID from URL: ' . $url
            . '. Expected a URL like https://yandex.ru/maps/org/name/123456789/'
        );
    }

    private function buildBrowserHeaders(array $extra = []): array
    {
        return array_merge([
            'User-Agent'      => $this->userAgent,
            'Accept-Language' => 'ru-RU,ru;q=0.9,en-US;q=0.8,en;q=0.7',
            'Accept-Encoding' => 'gzip, deflate, br',
            'Connection'      => 'keep-alive',
            'DNT'             => '1',
            'Sec-Fetch-Site'  => 'same-origin',
            'Sec-Fetch-Mode'  => 'cors',
            'Sec-Fetch-Dest'  => 'empty',
        ], $extra);
    }

    private function obtainCsrfToken(string $orgUrl): string
    {
        try {
            $response = $this->client->get($orgUrl, [
                'headers' => $this->buildBrowserHeaders([
                    'Accept'          => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,*/*;q=0.8',
                    'Sec-Fetch-Mode'  => 'navigate',
                    'Sec-Fetch-Site'  => 'none',
                    'Sec-Fetch-Dest'  => 'document',
                    'Upgrade-Insecure-Requests' => '1',
                ]),
            ]);

            $html = (string) $response->getBody();

            // Multiple CSRF token extraction patterns
            $patterns = [
                '/"csrfToken"\s*:\s*"([^"]+)"/',
                '/csrfToken["\s]*:["\s]*"([^"]+)"/',
                '/"csrf_token"\s*:\s*"([^"]+)"/',
                '/data-csrf["\s]*=\s*["\']([^"\']+)["\']/',
                '/name=["\']_csrf["\'][^>]+value=["\']([^"\']+)["\']/',
            ];

            foreach ($patterns as $pattern) {
                if (preg_match($pattern, $html, $m)) {
                    Log::info('YandexMapsParser: CSRF token found', ['pattern' => $pattern]);
                    return $m[1];
                }
            }

            Log::warning('YandexMapsParser: CSRF token not found in page HTML, proceeding without it');
            return '';
        } catch (GuzzleException $e) {
            throw new \RuntimeException(
                'Failed to load organization page: ' . $e->getMessage()
                . '. Check that the URL is accessible and correct.'
            );
        }
    }

    private function fetchReviewsPage(string $orgId, string $csrfToken, int $skip): array
    {
        // Try both known API endpoint variants
        $endpoints = [
            'https://yandex.ru/maps/api/business/fetchReviews',
            'https://yandex.ru/maps/-/api/business/fetchReviews',
        ];

        $params = [
            'businessId' => $orgId,
            'ajax'       => '1',
            'skip'       => $skip,
            'limit'      => self::PAGE_SIZE,
            'ranking'    => 'by_time',
            'lang'       => 'ru_RU',
        ];

        if ($csrfToken !== '') {
            $params['csrfToken'] = $csrfToken;
        }

        $lastError = null;

        foreach ($endpoints as $endpoint) {
            try {
                $response = $this->client->get($endpoint, [
                    'query'   => $params,
                    'headers' => $this->buildBrowserHeaders([
                        'Accept'             => 'application/json, text/javascript, */*; q=0.01',
                        'X-Requested-With'   => 'XMLHttpRequest',
                        'Referer'            => self::BASE_URL,
                        'Sec-Fetch-Site'     => 'same-origin',
                        'Sec-Fetch-Mode'     => 'cors',
                        'Sec-Fetch-Dest'     => 'empty',
                    ]),
                ]);

                $body = (string) $response->getBody();

                if (empty($body)) {
                    Log::warning('YandexMapsParser: empty response from ' . $endpoint);
                    continue;
                }

                $data = json_decode($body, true);

                if (json_last_error() !== JSON_ERROR_NONE) {
                    Log::warning('YandexMapsParser: non-JSON response', [
                        'endpoint' => $endpoint,
                        'preview'  => substr($body, 0, 200),
                    ]);
                    continue;
                }

                if (isset($data['error'])) {
                    $errMsg = $data['error']['message'] ?? ($data['error'] ?? 'Unknown error');
                    Log::warning('YandexMapsParser: API error', ['error' => $errMsg, 'endpoint' => $endpoint]);
                    $lastError = new \RuntimeException('Yandex API error: ' . $errMsg);
                    continue;
                }

                return $data;
            } catch (GuzzleException $e) {
                Log::warning('YandexMapsParser: HTTP error', ['endpoint' => $endpoint, 'error' => $e->getMessage()]);
                $lastError = new \RuntimeException('HTTP request to Yandex Maps failed: ' . $e->getMessage());
            }
        }

        throw $lastError ?? new \RuntimeException('All Yandex Maps API endpoints failed for skip=' . $skip);
    }

    private function parseOrganizationInfo(array $data): array
    {
        // Try multiple known response structures
        $business = $data['properties']['CompanyMetaData']
            ?? $data['businessData']
            ?? $data['data']['features'][0]['properties']['CompanyMetaData']
            ?? [];

        $rating = $business['rating']
            ?? $business['Rating']
            ?? $data['rating']
            ?? [];

        $name = $business['name']
            ?? $data['name']
            ?? $data['data']['features'][0]['properties']['name']
            ?? 'Unknown';

        $avgRating   = (float) ($rating['value']       ?? $rating['Value']       ?? $data['averageRating']  ?? 0);
        $ratingCount = (int)   ($rating['ratingCount']  ?? $rating['RatingCount'] ?? $data['ratingCount']   ?? 0);
        $reviewCount = (int)   ($rating['reviewCount']  ?? $rating['ReviewCount'] ?? $data['totalReviewCount'] ?? $data['total'] ?? 0);

        return [
            'name'           => $name,
            'average_rating' => $avgRating,
            'rating_count'   => $ratingCount,
            'review_count'   => $reviewCount,
        ];
    }

    private function parseReviews(array $data): array
    {
        $rawReviews = $data['properties']['CompanyMetaData']['reviews']
            ?? $data['reviews']
            ?? $data['data']
            ?? $data['items']
            ?? [];

        $reviews = [];
        foreach ($rawReviews as $review) {
            if (!is_array($review)) {
                continue;
            }

            $id = $review['reviewId'] ?? $review['id'] ?? uniqid('r_', true);

            $authorName = $review['authorName']
                ?? $review['author']['name']
                ?? ($review['author'] ?? null)
                ?? (($review['isAnonymous'] ?? false) ? 'Anonymous' : 'Unknown');

            $date = null;
            if (!empty($review['date'])) {
                $ts = is_numeric($review['date']) ? (int) $review['date'] : strtotime($review['date']);
                if ($ts !== false && $ts > 0) {
                    $date = date('Y-m-d H:i:s', $ts);
                }
            } elseif (!empty($review['updatedTime'])) {
                $ts = strtotime($review['updatedTime']);
                if ($ts !== false) {
                    $date = date('Y-m-d H:i:s', $ts);
                }
            } elseif (!empty($review['createdTime'])) {
                $ts = strtotime($review['createdTime']);
                if ($ts !== false) {
                    $date = date('Y-m-d H:i:s', $ts);
                }
            }

            $reviews[] = [
                'id'     => (string) $id,
                'author' => is_string($authorName) ? $authorName : 'Unknown',
                'rating' => (int) ($review['rating'] ?? $review['stars'] ?? 0),
                'text'   => $review['text'] ?? $review['snippet'] ?? '',
                'date'   => $date,
            ];
        }

        return $reviews;
    }
}

<?php

namespace Streekomroep;

use Exception;
use WP_Error;

class BunnyClient
{
    private BunnyCredentials $credentials;

    private static array $credentialsCache = [];

    private const LIBRARY_FIELDS = [
        'tv' => ['bunny_cdn_library_id', 'bunny_cdn_hostname', 'bunny_cdn_api_key'],
        'fragmenten' => ['bunny_cdn_library_id_fragmenten', 'bunny_cdn_hostname_fragmenten', 'bunny_cdn_api_key_fragmenten'],
    ];

    public function __construct(BunnyCredentials $credentials)
    {
        $this->credentials = $credentials;
    }

    public static function getCredentials(int $libraryId): ?BunnyCredentials
    {
        if (isset(self::$credentialsCache[$libraryId])) {
            return self::$credentialsCache[$libraryId];
        }

        $fields = null;
        if ($libraryId === ZW_BUNNY_LIBRARY_TV || $libraryId == get_field(self::LIBRARY_FIELDS['tv'][0], 'option')) {
            $fields = self::LIBRARY_FIELDS['tv'];
        } elseif ($libraryId === ZW_BUNNY_LIBRARY_FRAGMENTEN || $libraryId == get_field(self::LIBRARY_FIELDS['fragmenten'][0], 'option')) {
            $fields = self::LIBRARY_FIELDS['fragmenten'];
        }

        if (!$fields) {
            return null;
        }

        $credentials = new BunnyCredentials(
            get_field($fields[0], 'option'),
            get_field($fields[1], 'option'),
            get_field($fields[2], 'option')
        );

        self::$credentialsCache[$libraryId] = $credentials;

        return $credentials;
    }

    public static function parseUrl(string $url): ?BunnyVideoId
    {
        if (preg_match('#^https://(?:iframe|player)\.mediadelivery\.net/play/(\d+)/([a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12})$#i', $url, $m)) {
            return new BunnyVideoId((int)$m[1], $m[2]);
        }

        return null;
    }

    public function fetchVideo(BunnyVideoId $id): ?object
    {
        $response = $this->apiGet('https://video.bunnycdn.com/library/' . $id->libraryId . '/videos/' . $id->videoId);
        if ($response instanceof WP_Error) {
            return null;
        }

        if ($response['response']['code'] !== 200) {
            return null;
        }

        return json_decode($response['body']);
    }

    public function fetchCollection(string $collectionId): array
    {
        $url = 'https://video.bunnycdn.com/library/' . $this->credentials->libraryId . '/videos';
        $query = [
            'itemsPerPage' => 100,
            'collection' => $collectionId,
        ];

        $page = 1;
        $data = [];
        while (true) {
            $query['page'] = $page;
            $response = $this->apiGet($url . '?' . build_query($query));
            if ($response instanceof WP_Error) {
                throw new Exception($response->get_error_message());
            }

            if ($response['response']['code'] !== 200) {
                throw new Exception('Error while fetching bunny data: ' . $response['body']);
            }

            $body = json_decode($response['body']);

            array_push($data, ...$body->items);
            if (count($data) >= $body->totalItems) {
                break;
            }

            $page++;
        }

        return $data;
    }

    /**
     * @return array|WP_Error
     */
    private function apiGet(string $url)
    {
        return wp_remote_get($url, [
            'timeout' => 30,
            'headers' => [
                'AccessKey' => $this->credentials->apiKey,
                'accept' => 'application/json',
            ]
        ]);
    }
}

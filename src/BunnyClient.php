<?php

namespace Streekomroep;

use Exception;
use WP_Error;

class BunnyClient
{
    private BunnyCredentials $credentials;

    public function __construct(BunnyCredentials $credentials)
    {
        $this->credentials = $credentials;
    }

    public static function getCredentials(int $libraryId): ?BunnyCredentials
    {
        if ($libraryId === ZW_BUNNY_LIBRARY_TV || $libraryId == get_field('bunny_cdn_library_id', 'option')) {
            $libraryId = get_field('bunny_cdn_library_id', 'option');
            $hostname = get_field('bunny_cdn_hostname', 'option');
            $apiKey = get_field('bunny_cdn_api_key', 'option');
            return new BunnyCredentials($libraryId, $hostname, $apiKey);
        } elseif ($libraryId === ZW_BUNNY_LIBRARY_FRAGMENTEN || $libraryId == get_field('bunny_cdn_library_id_fragmenten', 'option')) {
            $libraryId = get_field('bunny_cdn_library_id_fragmenten', 'option');
            $hostname = get_field('bunny_cdn_hostname_fragmenten', 'option');
            $apiKey = get_field('bunny_cdn_api_key_fragmenten', 'option');
            return new BunnyCredentials($libraryId, $hostname, $apiKey);
        }

        return null;
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

        $video = json_decode($response['body']);
        VideoCollection::preprocessOne($video);

        return $video;
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

            $data = array_merge($data, $body->items);
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

<?php

/**
 * Adds the media file of fragments to feeds as an RSS enclosure.
 */

use Streekomroep\Fragment;
use Streekomroep\VideoRenderer;

const ZW_FRAGMENT_ENCLOSURE_META = '_zw_fragment_enclosure';

/**
 * Returns the cached enclosure of a fragment: the Bunny MP4 for video, the
 * linked MP3 for audio.
 *
 * Resolving hits the Bunny API and the media host, so results are stored in
 * post meta per source URL. Failures (e.g. a video that is still encoding) are
 * retried after a short delay.
 *
 * @return array{source: string, url: string, length: int, type: string}|null
 */
function zw_fragment_enclosure(int $post_id): ?array
{
    $fragment_type = get_field('fragment_type', $post_id);
    if (!in_array($fragment_type, [Fragment::TYPE_VIDEO, Fragment::TYPE_AUDIO], true)) {
        return null;
    }

    $source = trim((string) get_field('fragment_url', $post_id, false));
    if ($source === '') {
        return null;
    }

    $cached = get_post_meta($post_id, ZW_FRAGMENT_ENCLOSURE_META, true);
    if (is_array($cached) && ($cached['source'] ?? null) === $source) {
        return $cached;
    }

    $retry_key = 'zw_fragment_enclosure_retry_' . md5($source);
    if (get_transient($retry_key)) {
        return null;
    }

    $url = null;
    $mime_type = 'audio/mpeg';
    if ($fragment_type === Fragment::TYPE_VIDEO) {
        $video = VideoRenderer::resolveVideo($source);
        if ($video && $video->isAvailable()) {
            $url = $video->getMP4Url();
            $mime_type = 'video/mp4';
        }
    } elseif (wp_http_validate_url($source)) {
        $url = $source;
    }

    $enclosure = null;
    if ($url) {
        $response = wp_remote_head($url, ['timeout' => 10, 'redirection' => 5]);
        $length = (int) wp_remote_retrieve_header($response, 'content-length');
        if (wp_remote_retrieve_response_code($response) === 200 && $length > 0) {
            $enclosure = ['source' => $source, 'url' => $url, 'length' => $length, 'type' => $mime_type];
        }
    }

    if (!$enclosure) {
        set_transient($retry_key, 1, 10 * MINUTE_IN_SECONDS);
        return null;
    }

    update_post_meta($post_id, ZW_FRAGMENT_ENCLOSURE_META, $enclosure);

    return $enclosure;
}

function zw_fragment_feed_enclosure(): void
{
    if (get_post_type() !== 'fragment') {
        return;
    }

    $enclosure = zw_fragment_enclosure(get_the_ID());
    if (!$enclosure) {
        return;
    }

    printf(
        "<enclosure url=\"%s\" length=\"%d\" type=\"%s\" />\n",
        esc_url($enclosure['url']),
        $enclosure['length'],
        esc_attr($enclosure['type'])
    );
}

add_action('rss2_item', 'zw_fragment_feed_enclosure');

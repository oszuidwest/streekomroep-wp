// hls.js gives a live playlist 2 retries before a fatal error, and Video.js 10 does
// not recover from one (videojs/video.js#9296), so a few seconds of failed or hanging
// playlist requests end playback until the page is reloaded. Retrying both forever,
// backing off from 1 s to at most 4 s, lets the stream resume after an outage during
// playback, as Video.js 8 did. hls.js replaces the whole policy instead of merging it,
// so the load timeouts repeat its defaults.
const playlistLoadPolicy = {
    default: {
        maxTimeToFirstByteMs: 10000,
        maxLoadTimeMs: 20000,
        timeoutRetry: {maxNumRetry: Infinity, retryDelayMs: 1000, maxRetryDelayMs: 4000},
        errorRetry: {maxNumRetry: Infinity, retryDelayMs: 1000, maxRetryDelayMs: 4000},
    },
};

await customElements.whenDefined('hlsjs-video');

// The stream URL arrives as data-live-src rather than src, so the engine starts
// once, with the policy, instead of being rebuilt when the policy is added.
document.querySelectorAll('hlsjs-video[data-live-src]').forEach((media) => {
    media.source = {
        src: media.dataset.liveSrc,
        type: 'application/vnd.apple.mpegurl',
        engine: {hlsJs: {playlistLoadPolicy}},
    };
});

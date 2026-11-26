// hls.js allows a live playlist only 2 retries, so a few seconds without network
// stops playback for good (videojs/video.js#9296). 30 retries, backing off from 1 s
// to at most 4 s, give each outage a budget of about two minutes. hls.js replaces
// the whole policy instead of merging it, so the other values repeat its defaults.
const playlistLoadPolicy = {
    default: {
        maxTimeToFirstByteMs: 10000,
        maxLoadTimeMs: 20000,
        timeoutRetry: {maxNumRetry: 2, retryDelayMs: 0, maxRetryDelayMs: 0},
        errorRetry: {maxNumRetry: 30, retryDelayMs: 1000, maxRetryDelayMs: 4000},
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

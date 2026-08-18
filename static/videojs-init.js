/**
 * VideoJS initialization with Chrome HLS fix
 *
 * Chrome has issues with native HLS playback. This script configures VideoJS
 * to use VHS (Video.js HTTP Streaming) with the correct settings.
 */
window.zwVideoJsHtml5 = function () {
    return {
        vhs: {
            overrideNative: !videojs.browser.IS_SAFARI
        },
        nativeAudioTracks: false,
        nativeVideoTracks: false
    };
};

document.addEventListener('DOMContentLoaded', function () {
    if (typeof videojs === 'undefined') {
        return;
    }

    var players = document.querySelectorAll('.video-js[data-vjs-src]');

    players.forEach(function (element) {
        var src = element.getAttribute('data-vjs-src');
        var type = element.getAttribute('data-vjs-type') || 'application/x-mpegURL';
        var isLive = element.hasAttribute('data-vjs-live');

        var options = {
            html5: window.zwVideoJsHtml5()
        };

        if (isLive) {
            options.liveui = true;
        }

        var player = videojs(element, options);

        player.src({
            src: src,
            type: type
        });
    });
});

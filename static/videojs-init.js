/**
 * VideoJS initialization with Chrome HLS fix
 *
 * Chrome has issues with native HLS playback. This script configures VideoJS
 * to use VHS (Video.js HTTP Streaming) with the correct settings.
 */
function initVideoPlayers() {
    if (typeof videojs === 'undefined') {
        return;
    }

    // A soft navigation removes the elements these players were built on.
    videojs.getAllPlayers().forEach(function (player) {
        const element = player.el();
        if (element && !document.body.contains(element)) {
            player.dispose();
        }
    });

    var players = document.querySelectorAll('.video-js[data-vjs-src]');

    players.forEach(function (element) {
        if (element.player) {
            return;
        }

        var src = element.getAttribute('data-vjs-src');
        var type = element.getAttribute('data-vjs-type') || 'application/x-mpegURL';
        var isLive = element.hasAttribute('data-vjs-live');

        var options = {
            html5: {
                vhs: {
                    overrideNative: !videojs.browser.IS_SAFARI
                },
                nativeAudioTracks: false,
                nativeVideoTracks: false
            }
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
}

document.addEventListener('DOMContentLoaded', initVideoPlayers);
document.addEventListener('zw:page', initVideoPlayers);

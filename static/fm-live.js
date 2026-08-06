/**
 * Live radio page
 *
 * Keeps the now playing card in step with zwfm-metadata and drives the VideoJS stream from the
 * button in the page. Everything here is an enhancement: without it the page still shows the
 * programme, the schedule and the frequencies.
 */
(function () {
    const config = window.zwFmLive || {};

    const RECONNECT_START = 1000;
    const RECONNECT_MAX = 30000;
    const PROGRESS_INTERVAL = 30000;

    const trackKey = (track) => (track ? `${track.artist}|${track.title}` : '');

    function readTrack(payload) {
        const title = String(payload.title || '').trim();
        if (!title) {
            return null;
        }

        const artist = String(payload.artist || '').trim();
        return {title: title, artist: artist};
    }

    let current = null;
    function renderNow(track) {
        const details = document.querySelector('[data-now-details]');
        // Station idents and programme names travel through the same feed but are not records.
        const title = track.artist ? track.title : (details?.dataset.fallbackTitle || '');
        const artist = track.artist || details?.dataset.fallbackArtist || '';

        document.querySelectorAll('[data-now-title]').forEach((node) => {
            node.textContent = title;
        });
        document.querySelectorAll('[data-now-artist]').forEach((node) => {
            node.textContent = artist;
        });
    }

    let reconnectDelay = RECONNECT_START;
    let reconnectTimer = null;

    function scheduleReconnect() {
        clearTimeout(reconnectTimer);
        reconnectTimer = setTimeout(connectMetadata, reconnectDelay);
        reconnectDelay = Math.min(reconnectDelay * 2, RECONNECT_MAX);
    }

    function connectMetadata() {
        if (!config.metadataUrl || !('WebSocket' in window)) {
            return;
        }

        clearTimeout(reconnectTimer);

        let socket;
        try {
            socket = new WebSocket(config.metadataUrl);
        } catch (error) {
            scheduleReconnect();
            return;
        }

        socket.addEventListener('open', function () {
            reconnectDelay = RECONNECT_START;
        });

        socket.addEventListener('message', function (event) {
            let payload;
            try {
                payload = JSON.parse(event.data);
            } catch (error) {
                return;
            }

            if (!payload || typeof payload !== 'object') {
                return;
            }

            const track = readTrack(payload);
            if (!track) {
                return;
            }

            const changed = trackKey(track) !== trackKey(current);
            if (!changed) {
                return;
            }

            current = track;
            renderNow(track);
        });

        // An error is always followed by a close, which is where reconnecting is handled.
        socket.addEventListener('close', scheduleReconnect);
    }

    function startProgress() {
        const wrap = document.querySelector('[data-progress]');
        if (!wrap) {
            return;
        }

        const bar = wrap.querySelector('[data-progress-bar]');
        const label = wrap.querySelector('[data-progress-label]');
        const start = Number(wrap.dataset.start) * 1000;
        const end = Number(wrap.dataset.end) * 1000;
        const span = end - start;
        if (!bar || !label || !(span > 0)) {
            return;
        }

        const tick = function () {
            const now = Date.now();
            const done = Math.min(Math.max(now - start, 0), span);
            bar.style.width = `${Math.round((done / span) * 100)}%`;

            const minutes = Math.ceil((end - now) / 60000);
            if (minutes > 0) {
                label.textContent = `nog ${minutes} min`;
                return;
            }

            // The slot ran out while the page stayed open; stop claiming this show is on air.
            label.textContent = 'afgelopen';
            clearInterval(timer);
        };

        const timer = setInterval(tick, PROGRESS_INTERVAL);
        tick();
    }

    function setupPlayer() {
        const media = document.getElementById('zw-fm-stream');
        const buttons = document.querySelectorAll('[data-play]');
        if (!media || !buttons.length || typeof videojs === 'undefined') {
            return;
        }

        const player = videojs(media, {controls: false, liveui: true, preload: 'none'});

        const setPlaying = function (playing) {
            buttons.forEach((button) => {
                button.setAttribute('aria-label', playing ? 'Pauzeer' : 'Luister live');
                button.querySelector('[data-play-icon="play"]').hidden = playing;
                button.querySelector('[data-play-icon="pause"]').hidden = !playing;
            });
        };

        player.on('playing', function () {
            setPlaying(true);
        });
        player.on('pause', () => setPlaying(false));
        player.on('error', () => setPlaying(false));

        buttons.forEach((button) => button.addEventListener('click', function () {
            if (!player.paused()) {
                player.pause();
                return;
            }

            // Reload before playing: this is a live stream, so resuming must jump to the edge.
            player.load();
            const started = player.play();
            if (started && started.catch) {
                started.catch(() => setPlaying(false));
            }
            setPlaying(true);
        }));
    }

    startProgress();
    setupPlayer();
    connectMetadata();

})();

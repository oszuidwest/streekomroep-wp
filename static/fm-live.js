/**
 * Live radio page
 *
 * Keeps the now playing card in step with zwfm-metadata and drives the VideoJS stream from the
 * button in the page. Everything here is an enhancement: without it the page still shows the
 * programme, the schedule and the frequencies.
 */
(function () {
    const RECONNECT_START = 1000;
    const RECONNECT_MAX = 30000;
    const PROGRESS_INTERVAL = 30000;
    const FRESH_PERIOD_MAX = 15 * 60 * 1000;

    const details = document.querySelector('[data-now-details]');
    const titleNode = document.querySelector('[data-now-title]');
    const artistNode = document.querySelector('[data-now-artist]');
    // The server renders the no-track state, so that text doubles as the fallback.
    const fallbackTitle = titleNode ? titleNode.textContent : '';
    const fallbackArtist = artistNode ? artistNode.textContent : '';

    function readTrack(payload) {
        const title = String(payload.title || '').trim();
        if (!title) {
            return null;
        }

        const artist = String(payload.artist || '').trim();
        return {title: title, artist: artist};
    }

    let expiryTimer = null;
    function renderNow(track) {
        // Station idents and programme names travel through the same feed but are not records.
        const isTrack = Boolean(track && track.artist);
        const title = isTrack ? track.title : fallbackTitle;
        const artist = isTrack ? track.artist : fallbackArtist;

        // The container is aria-live, so rewriting identical text would re-announce it.
        if (titleNode.textContent === title && artistNode.textContent === artist) {
            return;
        }

        titleNode.textContent = title;
        artistNode.textContent = artist;
    }

    function renderFallback() {
        clearTimeout(expiryTimer);
        expiryTimer = null;
        renderNow(null);
    }

    function trackIsCurrent(expiresAt) {
        clearTimeout(expiryTimer);
        const remaining = Date.parse(expiresAt) - Date.now();
        if (!Number.isFinite(remaining)) {
            return true;
        }

        if (remaining <= 0) {
            renderFallback();
            return false;
        }

        expiryTimer = setTimeout(renderFallback, remaining);
        return true;
    }

    let reconnectDelay = RECONNECT_START;
    let reconnectTimer = null;
    let socket = null;

    function scheduleReconnect() {
        clearTimeout(reconnectTimer);
        // Hidden tabs cannot show the metadata anyway; reconnecting resumes on return.
        if (document.hidden) {
            return;
        }

        reconnectTimer = setTimeout(connectMetadata, reconnectDelay);
        reconnectDelay = Math.min(reconnectDelay * 2, RECONNECT_MAX);
    }

    function connectMetadata() {
        if (!details || !details.dataset.metadataUrl || !('WebSocket' in window)) {
            return;
        }

        clearTimeout(reconnectTimer);

        try {
            socket = new WebSocket(details.dataset.metadataUrl);
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
                // An empty metadata update explicitly means that nothing is playing.
                if (!payload.type || payload.type === 'metadata_update') {
                    renderFallback();
                }
                return;
            }

            if (!trackIsCurrent(payload.expires_at)) {
                return;
            }

            renderNow(track);
        });

        // An error is always followed by a close, which is where reconnecting is handled.
        socket.addEventListener('close', scheduleReconnect);
    }

    document.addEventListener('visibilitychange', function () {
        if (document.hidden) {
            clearTimeout(reconnectTimer);
        } else if (!socket || socket.readyState >= WebSocket.CLOSING) {
            connectMetadata();
        }
    });

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

        const formatRemaining = function (minutes) {
            if (minutes < 60) {
                return `${minutes} min te gaan`;
            }

            const hours = Math.floor(minutes / 60);
            const rest = minutes % 60;
            return rest > 0 ? `${hours} uur ${rest} min te gaan` : `${hours} uur te gaan`;
        };

        const tick = function () {
            const now = Date.now();
            const done = Math.min(Math.max(now - start, 0), span);
            bar.style.width = `${Math.round((done / span) * 100)}%`;

            const minutes = Math.ceil((end - now) / 60000);
            if (minutes > 0) {
                const freshPeriod = Math.min(FRESH_PERIOD_MAX, span * 0.15);
                label.textContent = done < freshPeriod ? 'net gestart' : formatRemaining(minutes);
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
        const button = document.querySelector('[data-play]');
        if (!media || !button || typeof videojs === 'undefined' || !window.zwVideoJsHtml5) {
            return;
        }

        const player = videojs(media, {
            controls: false,
            liveui: true,
            preload: 'none',
            html5: window.zwVideoJsHtml5()
        });

        const playIcon = button.querySelector('[data-play-icon="play"]');
        const pauseIcon = button.querySelector('[data-play-icon="pause"]');
        const setPlaying = function (playing) {
            button.setAttribute('aria-label', playing ? 'Pauzeer' : 'Luister live');
            playIcon.hidden = playing;
            pauseIcon.hidden = !playing;
        };

        player.on('playing', () => setPlaying(true));
        player.on('pause', () => setPlaying(false));
        player.on('error', () => setPlaying(false));

        button.addEventListener('click', function () {
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
        });
    }

    startProgress();
    setupPlayer();
    connectMetadata();

})();

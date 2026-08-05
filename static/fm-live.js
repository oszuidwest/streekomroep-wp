/**
 * Live radio page
 *
 * Keeps the now playing card, the docked player and the recently played list in step with
 * zwfm-metadata, and drives the VideoJS stream from the buttons in the page. Everything here is
 * an enhancement: without it the page still shows the programme, the schedule and the frequencies.
 */
(function () {
    const config = window.zwFmLive || {};

    const RECONNECT_START = 1000;
    const RECONNECT_MAX = 30000;
    // Aeron logs a track a moment after it starts, so give the playlist time to catch up.
    const RECENT_SETTLE = 5000;
    const RECENT_INTERVAL = 60000;
    const PROGRESS_INTERVAL = 30000;
    const UUID = /^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/;

    const all = (selector, scope) => Array.from((scope || document).querySelectorAll(selector));

    // The triangle from the FM header, drawn as a placeholder for records without album art.
    const TRIANGLE = 'M28 -98 L28 98 L-56 0 Z';
    const COVER_COLORS = [['#e6007e', 0.16], ['#8f004e', 0.13]];

    // Seeded so one record keeps the same drawing every time it comes back around.
    function seededRandom(seed) {
        let state = 2166136261 >>> 0;
        for (let i = 0; i < seed.length; i++) {
            state ^= seed.charCodeAt(i);
            state = Math.imul(state, 16777619) >>> 0;
        }
        return () => {
            state = (Math.imul(state, 1664525) + 1013904223) >>> 0;
            return state / 4294967296;
        };
    }

    function coverPattern(seed) {
        const random = seededRandom(seed);
        let paths = '';
        for (let row = 0; row < 2; row++) {
            const x = ((0.12 + random() * 0.76) * 60).toFixed(1);
            const y = ((row + 0.12 + random() * 0.76) * 30).toFixed(1);
            const scale = (0.3 + random() * 0.32).toFixed(3);
            const rotation = Math.floor(random() * 360);
            const color = COVER_COLORS[Math.floor(random() * COVER_COLORS.length)];
            const opacity = (color[1] * (0.62 + random() * 0.76)).toFixed(3);
            paths += `<path d="${TRIANGLE}" fill="${color[0]}" stroke="${color[0]}" opacity="${opacity}"`
                + ` transform="translate(${x} ${y}) rotate(${rotation}) scale(${scale})"/>`;
        }

        return '<svg class="absolute inset-0 h-full w-full" viewBox="0 0 60 60"'
            + ' preserveAspectRatio="xMidYMid slice" aria-hidden="true" focusable="false">'
            + `<g stroke-width="20" stroke-linejoin="round">${paths}</g></svg>`;
    }

    function paintCover(node, seed, id) {
        const key = `${seed}|${id || ''}`;
        if (node.dataset.coverPainted === key) {
            return;
        }

        node.dataset.coverPainted = key;
        node.innerHTML = coverPattern(seed);
        if (!config.imageUrl || !id) {
            return;
        }

        // Loaded before it is in the document, so the cover only replaces the pattern once it is
        // there. That rules out lazy loading, which never starts for a detached image.
        const image = new Image();
        image.alt = '';
        image.decoding = 'async';
        image.className = 'absolute inset-0 h-full w-full object-cover';
        image.addEventListener('load', function () {
            // The next record may already have arrived while this cover was loading.
            if (node.dataset.coverPainted === key) {
                node.appendChild(image);
            }
        });
        image.src = config.imageUrl.replace('TRACKID', encodeURIComponent(id));
    }

    const paintFromMarkup = (node) => paintCover(node, node.dataset.coverSeed || '', node.dataset.coverId || '');

    /** zwfm-metadata reports the Aeron songID as a braced GUID; Aeron Toolbox wants it bare. */
    function normalizeId(value) {
        const id = String(value || '').trim().replace(/^\{|\}$/g, '').toLowerCase();
        return UUID.test(id) ? id : '';
    }

    const trackKey = (track) => (track ? `${track.artist}|${track.title}` : '');

    function readTrack(payload) {
        const title = String(payload.title || '').trim();
        if (!title) {
            return null;
        }

        const artist = String(payload.artist || '').trim();
        const id = normalizeId(payload.songID);

        return {
            id: id,
            title: title,
            artist: artist,
            // Station idents and programme names travel through the same feed but are not records.
            isSong: Boolean(artist && (id || payload.source_type === 'dynamic')),
        };
    }

    let current = null;
    let recentTimer = null;

    function renderNow(track) {
        all('[data-now-title]').forEach((node) => {
            node.textContent = track.title;
        });
        all('[data-now-artist]').forEach((node) => {
            node.textContent = track.artist;
        });

        const seed = track.isSong ? trackKey(track) : `nu:${track.title}`;
        all('[data-cover-live]').forEach((node) => paintCover(node, seed, track.isSong ? track.id : ''));
    }

    function renderRecent(tracks) {
        const list = document.querySelector('[data-recent]');
        const template = document.querySelector('[data-recent-row]');
        if (!list || !template || !tracks.length) {
            return;
        }

        const rows = document.createDocumentFragment();
        tracks.forEach((track, index) => {
            const row = template.content.firstElementChild.cloneNode(true);
            const onAir = index === 0
                && Boolean(track.current || (current && current.isSong && trackKey(current) === trackKey(track)));

            const time = row.querySelector('[data-track-time]');
            time.textContent = onAir ? 'nu' : (track.time || '');
            time.classList.toggle('text-roze', onAir);
            time.classList.toggle('text-gray-400', !onAir);

            row.querySelector('[data-track-title]').textContent = track.title || '';
            row.querySelector('[data-track-artist]').textContent = track.artist || '';

            const cover = row.querySelector('[data-cover-seed]');
            if (cover) {
                paintCover(cover, trackKey(track), track.has_image ? track.id : '');
            }

            rows.appendChild(row);
        });

        list.replaceChildren(rows);
    }

    function fetchRecent() {
        if (!config.recentUrl) {
            return;
        }

        fetch(config.recentUrl, {credentials: 'omit'})
            .then((response) => (response.ok ? response.json() : Promise.reject(response.status)))
            .then((data) => renderRecent(Array.isArray(data.tracks) ? data.tracks : []))
            .catch(() => {
                // Leave whatever is on screen; the next interval tries again.
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
            current = track;
            renderNow(track);

            if (changed) {
                clearTimeout(recentTimer);
                recentTimer = setTimeout(fetchRecent, RECENT_SETTLE);
            }
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
            const dot = document.querySelector('[data-live-dot]');
            if (dot) {
                dot.classList.remove('zw-pulse');
                dot.classList.add('opacity-40');
            }
            clearInterval(timer);
        };

        const timer = setInterval(tick, PROGRESS_INTERVAL);
        tick();
    }

    function setupPlayer() {
        const media = document.getElementById('zw-fm-stream');
        const buttons = all('[data-play]');
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

        player.on('playing', () => setPlaying(true));
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

    function bootPlayer() {
        if (typeof videojs !== 'undefined') {
            setupPlayer();
            return;
        }

        // VideoJS is deferred, so it may not have run yet when this script executes.
        window.addEventListener('DOMContentLoaded', setupPlayer, {once: true});
    }

    function setupDock() {
        const dock = document.querySelector('[data-dock]');
        const card = document.querySelector('[data-now-card]');
        if (!dock || !card || !('IntersectionObserver' in window)) {
            return;
        }

        // Show the dock once the card with the same controls has scrolled out of view. The page
        // ends with a spacer the height of the dock, so it never covers anything.
        new IntersectionObserver(function (entries) {
            dock.toggleAttribute('data-open', !entries[entries.length - 1].isIntersecting);
        }, {rootMargin: '-8px 0px 0px 0px'}).observe(card);
    }

    all('[data-cover-seed]').forEach(paintFromMarkup);
    startProgress();
    bootPlayer();
    setupDock();
    connectMetadata();

    if (config.recentUrl) {
        setInterval(fetchRecent, RECENT_INTERVAL);
    }
})();

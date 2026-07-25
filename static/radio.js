/**
 * The radio player that follows the visitor across the site.
 *
 * Lives outside #zw-page, so static/soft-nav.js can replace the page around it without the
 * stream dropping. Hidden until someone presses play; from that moment the bar stays in view
 * and this script keeps every play button, title and cover on the page in step with it.
 */
(function () {
    const config = window.zwRadioConfig || {};
    const audio = document.getElementById('zw-radio-stream');
    const bar = document.querySelector('[data-radio-bar]');

    // Remembers across a full page load that someone was listening.
    const SESSION_KEY = 'zw-radio';
    const SESSION_PLAYING = 'playing';
    const SESSION_OPEN = 'open';

    const RECONNECT_START = 1000;
    const RECONNECT_MAX = 30000;
    // The grid turns on the hour and the half hour, so this never lags by more than one slot.
    const SHOW_REFRESH = 1800000;
    const UUID = /^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/;

    const all = (selector) => Array.from(document.querySelectorAll(selector));

    /* ---------------------------------------------------------------------------
       Cover art. The theme's triangle, drawn as a placeholder for records without
       album art, seeded so one record keeps the same drawing. Shared with the live
       page through the small API at the bottom of this file.
    --------------------------------------------------------------------------- */

    const TRIANGLE = 'M28 -98 L28 98 L-56 0 Z';
    const COVER_COLORS = [['#e6007e', 0.16], ['#8f004e', 0.13]];

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

    function coverUrl(id) {
        return config.imageUrl && id ? config.imageUrl.replace('TRACKID', encodeURIComponent(id)) : '';
    }

    function paintCover(node, seed, id) {
        const key = `${seed}|${id || ''}`;
        if (node.dataset.coverPainted === key) {
            return;
        }

        node.dataset.coverPainted = key;
        node.innerHTML = coverPattern(seed);

        const url = coverUrl(id);
        if (!url) {
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
        image.src = url;
    }

    /* ---------------------------------------------------------------------------
       What is playing
    --------------------------------------------------------------------------- */

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
            duration: String(payload.duration || '').trim(),
            // Station idents and programme names travel through the same feed but are not records.
            isSong: Boolean(artist && (id || payload.source_type === 'dynamic')),
        };
    }

    let current = null;
    const trackListeners = [];

    function renderTrack() {
        if (!current) {
            return;
        }

        all('[data-now-title]').forEach((node) => {
            node.textContent = current.title;
        });
        all('[data-now-artist]').forEach((node) => {
            node.textContent = current.artist;
        });
        all('[data-now-duration]').forEach((node) => {
            node.textContent = current.isSong && current.duration ? ` · ${current.duration}` : '';
        });

        const seed = current.isSong ? trackKey(current) : `nu:${current.title}`;
        all('[data-cover-live]').forEach((node) => paintCover(node, seed, current.isSong ? current.id : ''));

        updateMediaSession();
    }

    /** Puts the record on the lock screen and makes the hardware media keys work. */
    function updateMediaSession() {
        if (!('mediaSession' in navigator) || !window.MediaMetadata) {
            return;
        }

        const artwork = coverUrl(current.isSong ? current.id : '');
        navigator.mediaSession.metadata = new MediaMetadata({
            title: current.title,
            artist: current.artist || config.stationName || '',
            album: config.stationName || '',
            artwork: artwork ? [{src: artwork, sizes: '512x512'}] : [],
        });
    }

    let socket = null;
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
            renderTrack();
            trackListeners.forEach((listener) => listener(track, changed));
        });

        // An error is always followed by a close, which is where reconnecting is handled.
        socket.addEventListener('close', scheduleReconnect);
    }

    /* ---------------------------------------------------------------------------
       The bar and the stream
    --------------------------------------------------------------------------- */

    let active = false;
    let showTimer = null;
    let showName = '';

    const isPlaying = () => Boolean(audio) && !audio.paused;

    function remember(state) {
        try {
            sessionStorage.setItem(SESSION_KEY, state);
        } catch (error) {
            // Private mode; the bar simply starts closed on the next page.
        }
    }

    function renderPlayState() {
        const playing = isPlaying();
        all('[data-radio-play]').forEach((button) => {
            button.setAttribute('aria-label', playing ? 'Pauzeer' : 'Luister live');
            const play = button.querySelector('[data-play-icon="play"]');
            const pause = button.querySelector('[data-play-icon="pause"]');
            if (play) {
                play.hidden = playing;
            }
            if (pause) {
                pause.hidden = !playing;
            }
        });
    }

    function renderSpacer() {
        const spacer = document.querySelector('[data-radio-spacer]');
        if (spacer) {
            spacer.classList.toggle('hidden', !active);
        }
    }

    function renderShow() {
        const holder = document.querySelector('[data-radio-show]');
        const name = document.querySelector('[data-radio-show-name]');
        if (!holder || !name) {
            return;
        }

        name.textContent = showName;
        holder.toggleAttribute('data-show', Boolean(showName));
    }

    function loadShow() {
        if (!config.broadcastUrl) {
            return;
        }

        fetch(config.broadcastUrl, {credentials: 'omit'})
            .then((response) => (response.ok ? response.json() : Promise.reject(response.status)))
            .then((data) => {
                showName = (data.fm && data.fm.now) || '';
                renderShow();
            })
            .catch(() => {
                // Secondary information; the bar reads fine without it.
            });
    }

    function activate() {
        if (active || !bar) {
            return;
        }

        active = true;
        bar.toggleAttribute('data-open', true);
        bar.removeAttribute('inert');
        renderSpacer();
        loadShow();
        showTimer = setInterval(loadShow, SHOW_REFRESH);
    }

    function play() {
        if (!audio) {
            return;
        }

        // A live stream has no position worth keeping: always reconnect at the edge.
        audio.load();
        const started = audio.play();
        if (started && started.catch) {
            started.catch(renderPlayState);
        }
    }

    if (audio) {
        audio.addEventListener('playing', function () {
            activate();
            remember(SESSION_PLAYING);
            renderPlayState();
        });
        audio.addEventListener('pause', function () {
            remember(SESSION_OPEN);
            renderPlayState();
        });
        audio.addEventListener('error', renderPlayState);

        // Buttons live inside the page, which is replaced on every soft navigation.
        document.addEventListener('click', function (event) {
            const button = event.target.closest('[data-radio-play]');
            if (!button) {
                return;
            }

            event.preventDefault();
            if (isPlaying()) {
                audio.pause();
            } else {
                activate();
                play();
            }
        });

        const remembered = (function () {
            try {
                return sessionStorage.getItem(SESSION_KEY);
            } catch (error) {
                return null;
            }
        })();

        if (remembered) {
            activate();
            renderPlayState();
            if (remembered === SESSION_PLAYING) {
                // A full page load ended the stream. Picking it up again only works when the
                // browser allows it; when it does not, the bar simply shows the play button.
                play();
            }
        }
    }

    // A soft navigation hands us a fresh page: the bar survived, everything in it did not.
    document.addEventListener('zw:page', function () {
        renderPlayState();
        renderTrack();
        renderSpacer();
    });

    connectMetadata();

    window.zwRadio = {
        isPlaying: isPlaying,
        paintCover: paintCover,
        trackKey: trackKey,
        /** Calls back on every metadata update, and right away when a record is already known. */
        onTrack: function (listener) {
            trackListeners.push(listener);
            if (current) {
                listener(current, false);
            }
        },
    };
})();

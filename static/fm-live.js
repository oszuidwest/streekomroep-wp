/**
 * The live radio page
 *
 * Fills in the parts of the page that keep moving: the covers, the list of records that were just
 * played, and the progress through the current programme. The player itself belongs to
 * static/radio.js, which follows the visitor across the whole site.
 *
 * Everything here is an enhancement: without it the page still shows the programme, what is on
 * next, what was just played and the frequencies.
 */
(function () {
    const config = window.zwFmLive || {};
    const radio = window.zwRadio;

    // Aeron logs a track a moment after it starts, so give the playlist time to catch up.
    const RECENT_SETTLE = 5000;
    const RECENT_INTERVAL = 60000;
    const PROGRESS_INTERVAL = 30000;

    const all = (selector) => Array.from(document.querySelectorAll(selector));

    let timers = [];
    let settleTimer = null;
    let current = null;

    function renderRecent(tracks) {
        const list = document.querySelector('[data-recent]');
        const template = document.querySelector('[data-recent-row]');
        if (!list || !template || !tracks.length) {
            return;
        }

        const rows = document.createDocumentFragment();
        tracks.forEach((track, index) => {
            const row = template.content.firstElementChild.cloneNode(true);
            const onAir = index === 0 && Boolean(
                track.current
                || (current && current.isSong && radio.trackKey(current) === radio.trackKey(track))
            );

            const time = row.querySelector('[data-track-time]');
            time.textContent = onAir ? 'nu' : (track.time || '');
            time.classList.toggle('text-roze', onAir);
            time.classList.toggle('text-gray-400', !onAir);

            row.querySelector('[data-track-title]').textContent = track.title || '';
            row.querySelector('[data-track-artist]').textContent = track.artist || '';

            const cover = row.querySelector('[data-cover-seed]');
            if (cover) {
                radio.paintCover(cover, radio.trackKey(track), track.has_image ? track.id : '');
            }

            rows.appendChild(row);
        });

        list.replaceChildren(rows);
    }

    function fetchRecent() {
        if (!config.recentUrl || !document.querySelector('[data-recent]')) {
            return;
        }

        fetch(config.recentUrl, {credentials: 'omit'})
            .then((response) => (response.ok ? response.json() : Promise.reject(response.status)))
            .then((data) => renderRecent(Array.isArray(data.tracks) ? data.tracks : []))
            .catch(() => {
                // Leave whatever is on screen; the next interval tries again.
            });
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
        timers.push(timer);
        tick();
    }

    function init() {
        timers.forEach(clearInterval);
        timers = [];

        // A soft navigation may well have taken the visitor somewhere else entirely.
        if (!document.querySelector('[data-recent], [data-progress], [data-cover-seed]')) {
            return;
        }

        all('[data-cover-seed]').forEach((node) => {
            radio.paintCover(node, node.dataset.coverSeed || '', node.dataset.coverId || '');
        });

        startProgress();

        if (config.recentUrl) {
            timers.push(setInterval(fetchRecent, RECENT_INTERVAL));
        }
    }

    if (radio) {
        radio.onTrack(function (track, changed) {
            current = track;
            if (changed) {
                clearTimeout(settleTimer);
                settleTimer = setTimeout(fetchRecent, RECENT_SETTLE);
            }
        });

        init();
        document.addEventListener('zw:page', init);
    }
})();

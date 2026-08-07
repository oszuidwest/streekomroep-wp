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
    const SCHEDULE_RETRY = 30000;
    const SCHEDULE_TIMEOUT = 10000;

    const radioPage = document.querySelector('[data-radio-live]');
    const details = document.querySelector('[data-now-details]');
    const titleNode = document.querySelector('[data-now-title]');
    const artistNode = document.querySelector('[data-now-artist]');
    // The server renders the no-track state, so that text doubles as the fallback.
    let fallbackTitle = titleNode ? titleNode.textContent : '';
    let fallbackArtist = artistNode ? artistNode.textContent : '';

    function readTrack(payload) {
        const title = String(payload.title || '').trim();
        if (!title) {
            return null;
        }

        const artist = String(payload.artist || '').trim();
        return {title: title, artist: artist};
    }

    let expiryTimer = null;
    let showingTrack = false;
    function renderNow(track) {
        // Station idents and programme names travel through the same feed but are not records.
        const isTrack = Boolean(track && track.artist);
        const title = isTrack ? track.title : fallbackTitle;
        const artist = isTrack ? track.artist : fallbackArtist;
        showingTrack = isTrack;

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

    let progressTimer = null;
    function startProgress() {
        clearInterval(progressTimer);
        progressTimer = null;

        const wrap = document.querySelector('[data-progress]');
        if (!wrap || wrap.hidden) {
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
            clearInterval(progressTimer);
            progressTimer = null;
        };

        progressTimer = setInterval(tick, PROGRESS_INTERVAL);
        tick();
    }

    function formatNames(makers) {
        const names = makers.map((maker) => String(maker.name || '').trim()).filter(Boolean);
        if (names.length < 2) {
            return names.join('');
        }

        return `${names.slice(0, -1).join(', ')} en ${names[names.length - 1]}`;
    }

    function renderCurrentMakers(makers) {
        const wrap = document.querySelector('[data-current-makers]');
        const portraits = document.querySelector('[data-current-portraits]');
        const names = document.querySelector('[data-current-maker-names]');
        if (!wrap || !portraits || !names) {
            return;
        }

        const makerNames = formatNames(makers);
        names.textContent = makerNames;
        wrap.hidden = !makerNames;

        portraits.replaceChildren();
        makers.filter((maker) => maker.photo).slice(0, 2).forEach((maker) => {
            const image = document.createElement('img');
            image.src = maker.photo.src;
            image.srcset = maker.photo.srcset;
            image.alt = maker.name;
            image.width = 40;
            image.height = 40;
            image.loading = 'lazy';
            image.className = 'size-8 rounded-sm object-cover object-[50%_16%] md:size-10';
            portraits.append(image);
        });
        portraits.hidden = !portraits.childElementCount;
    }

    function renderProgress(current) {
        const wrap = document.querySelector('[data-progress]');
        if (!wrap) {
            return;
        }

        if (!current.show) {
            wrap.hidden = true;
            clearInterval(progressTimer);
            progressTimer = null;
            return;
        }

        wrap.hidden = false;
        wrap.dataset.start = current.start;
        wrap.dataset.end = current.end;
        wrap.querySelector('[data-progress-start]').textContent = current.start_time;
        wrap.querySelector('[data-progress-end]').textContent = current.end_time;
        startProgress();
    }

    function renderUpcoming(items) {
        const section = document.querySelector('[data-upcoming]');
        const list = document.querySelector('[data-upcoming-list]');
        const template = document.querySelector('[data-upcoming-template]');
        if (!section || !list || !template) {
            return;
        }

        list.replaceChildren();
        items.forEach((item) => {
            if (!item.show) {
                return;
            }

            const card = template.content.firstElementChild.cloneNode(true);
            const makers = Array.isArray(item.show.makers) ? item.show.makers : [];
            const label = card.querySelector('[data-upcoming-label]');
            const title = card.querySelector('[data-upcoming-title]');
            const makerNames = card.querySelector('[data-upcoming-makers]');
            const photo = card.querySelector('[data-upcoming-photo]');
            const photoMaker = makers.find((maker) => maker.photo);

            card.href = item.show.link;
            card.querySelector('[data-upcoming-time]').textContent = item.start_time;
            label.textContent = item.label || '';
            label.hidden = !item.label;
            title.textContent = item.show.title;
            title.classList.toggle('mt-0.5', Boolean(item.label));
            makerNames.textContent = formatNames(makers);
            makerNames.hidden = !makerNames.textContent;

            if (photoMaker) {
                photo.src = photoMaker.photo.src;
                photo.srcset = photoMaker.photo.srcset;
                photo.hidden = false;
            }

            list.append(card);
        });
        section.hidden = !list.childElementCount;
    }

    let scheduleTimer = null;
    let schedulePending = false;

    function queueScheduleRefresh(seconds) {
        clearTimeout(scheduleTimer);
        const delay = Math.max(1, Number(seconds) || SCHEDULE_RETRY / 1000) * 1000;
        // Broadcast boundaries are inclusive server-side, so fetch just after the old slot ends.
        scheduleTimer = setTimeout(refreshSchedule, delay + 1000);
    }

    function applySchedule(schedule) {
        const current = schedule.current;
        if (!current || !radioPage) {
            queueScheduleRefresh(schedule.refresh_after);
            return;
        }

        const makers = current.show && Array.isArray(current.show.makers) ? current.show.makers : [];
        const status = document.querySelector('[data-current-status]');
        const title = document.querySelector('[data-current-title]');
        status.textContent = current.show
            ? `Nu live · ${current.start_time} – ${current.end_time}`
            : `Nu op ${radioPage.dataset.radioTitle}`;
        title.textContent = current.name;

        renderCurrentMakers(makers);
        renderProgress(current);
        renderUpcoming(Array.isArray(schedule.upcoming) ? schedule.upcoming : []);

        if (!document.getElementById('zw-fm-stream')) {
            fallbackTitle = current.name;
            fallbackArtist = formatNames(makers);
            if (!showingTrack) {
                renderFallback();
            }
        }

        radioPage.dataset.scheduleRefreshAfter = schedule.refresh_after;
        queueScheduleRefresh(schedule.refresh_after);
    }

    async function refreshSchedule() {
        if (!radioPage || !radioPage.dataset.scheduleUrl || schedulePending) {
            return;
        }

        schedulePending = true;
        const controller = new AbortController();
        const timeout = setTimeout(() => controller.abort(), SCHEDULE_TIMEOUT);
        try {
            const response = await fetch(radioPage.dataset.scheduleUrl, {
                cache: 'no-store',
                headers: {Accept: 'application/json'},
                signal: controller.signal
            });
            if (!response.ok) {
                throw new Error(`Schedule request failed with ${response.status}`);
            }

            const payload = await response.json();
            if (!payload || !payload.fm || !payload.fm.schedule) {
                throw new Error('Schedule response is incomplete');
            }

            applySchedule(payload.fm.schedule);
        } catch (error) {
            queueScheduleRefresh(SCHEDULE_RETRY / 1000);
        } finally {
            clearTimeout(timeout);
            schedulePending = false;
        }
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
    if (radioPage) {
        queueScheduleRefresh(radioPage.dataset.scheduleRefreshAfter);
    }

})();

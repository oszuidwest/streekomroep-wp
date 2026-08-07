(function () {
    const RECONNECT_START = 1000;
    const RECONNECT_MAX = 30000;
    const PROGRESS_INTERVAL = 30000;
    const FRESH_PERIOD_MAX = 15 * 60 * 1000;
    const SCHEDULE_RETRY_SECONDS = 30;
    const SCHEDULE_RETRY_MAX_SECONDS = 300;
    // Spread boundary refreshes across ten seconds to reduce synchronized load.
    const SCHEDULE_JITTER = 10000;
    const SCHEDULE_TIMEOUT = 10000;

    const radioPage = document.querySelector('[data-radio-live]');
    const streamElement = document.getElementById('zw-fm-stream');
    const titleNode = document.querySelector('[data-now-title]');
    const artistNode = document.querySelector('[data-now-artist]');
    let fallbackTitle = titleNode ? titleNode.textContent : '';
    let fallbackArtist = artistNode ? artistNode.textContent : '';

    const stationTitle = radioPage ? radioPage.dataset.radioTitle : '';
    const stationTagline = radioPage ? radioPage.dataset.radioTagline : '';
    const sessionArtwork = JSON.parse(radioPage?.dataset.artwork || '[]');

    const currentTitleNode = document.querySelector('[data-current-title]');
    let programName = currentTitleNode ? currentTitleNode.textContent.trim() : '';
    let currentTrack = null;
    let isPlaying = false;

    function updateMediaSession() {
        if (!('mediaSession' in navigator)) {
            return;
        }

        // Do not repeat the station name when the programme label already contains it.
        const album = programName && !programName.includes(stationTitle)
            ? `${programName} · ${stationTitle}`
            : (programName || stationTitle);

        navigator.mediaSession.metadata = new MediaMetadata(currentTrack ? {
            title: currentTrack.title,
            artist: currentTrack.artist,
            album: album,
            artwork: sessionArtwork
        } : {
            title: stationTitle,
            artist: stationTagline,
            artwork: sessionArtwork
        });
    }

    function readTrack(payload) {
        // The feed includes non-track events; tracks require both a title and an artist.
        const title = String(payload.title || '').trim();
        const artist = String(payload.artist || '').trim();
        return title && artist ? {title: title, artist: artist} : null;
    }

    let expiryTimer = null;
    function renderNow(track) {
        const changed = track?.title !== currentTrack?.title || track?.artist !== currentTrack?.artist;
        currentTrack = track;
        // Avoid redundant Media Session assignments and system UI redraws.
        if (changed) {
            updateMediaSession();
        }

        const title = track ? track.title : fallbackTitle;
        const artist = track ? track.artist : fallbackArtist;

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
        // A hidden tab cannot show the metadata, but the lock screen still can while playing.
        if (document.hidden && !isPlaying) {
            return;
        }

        reconnectTimer = setTimeout(connectMetadata, reconnectDelay);
        reconnectDelay = Math.min(reconnectDelay * 2, RECONNECT_MAX);
    }

    function connectMetadata() {
        if (!radioPage || !radioPage.dataset.metadataUrl || !('WebSocket' in window)) {
            return;
        }

        clearTimeout(reconnectTimer);

        try {
            socket = new WebSocket(radioPage.dataset.metadataUrl);
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
                // An empty metadata update ends the current track.
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

        // Reconnect from `close` so failed and clean disconnects share one path.
        socket.addEventListener('close', scheduleReconnect);
    }

    function resumeFeeds() {
        if (!socket || socket.readyState >= WebSocket.CLOSING) {
            connectMetadata();
        }

        if (scheduleStale) {
            scheduleStale = false;
            refreshSchedule();
        }
    }

    document.addEventListener('visibilitychange', function () {
        if (document.hidden) {
            if (!isPlaying) {
                // Suspend the idle metadata socket while the page is hidden.
                clearTimeout(reconnectTimer);
                socket?.close();
            }
            return;
        }

        resumeFeeds();
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

            // Mark the timer as ended while the boundary refresh catches up.
            label.textContent = 'afgelopen';
            clearInterval(progressTimer);
            progressTimer = null;
        };

        progressTimer = setInterval(tick, PROGRESS_INTERVAL);
        tick();
    }

    function renderCurrentMakers(show) {
        const wrap = document.querySelector('[data-current-makers]');
        const portraits = document.querySelector('[data-current-portraits]');
        const names = document.querySelector('[data-current-maker-names]');
        const template = document.querySelector('[data-portrait-template]');
        if (!wrap || !portraits || !names || !template) {
            return;
        }

        const label = show ? show.makers_label || '' : '';
        names.textContent = label;
        wrap.hidden = !label;

        const makers = show && Array.isArray(show.makers) ? show.makers : [];
        portraits.replaceChildren();
        makers.filter((maker) => maker.photo).slice(0, 2).forEach((maker) => {
            const image = template.content.firstElementChild.cloneNode(true);
            image.src = maker.photo.src;
            image.srcset = maker.photo.srcset;
            image.alt = maker.name;
            portraits.append(image);
        });
        portraits.hidden = !portraits.childElementCount;
    }

    function renderProgress(current) {
        const wrap = document.querySelector('[data-progress]');
        if (!wrap) {
            return;
        }

        wrap.hidden = !current.show;
        if (current.show) {
            wrap.dataset.start = current.start;
            wrap.dataset.end = current.end;
            wrap.querySelector('[data-progress-start]').textContent = current.start_time;
            wrap.querySelector('[data-progress-end]').textContent = current.end_time;
        }

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
            makerNames.textContent = item.show.makers_label || '';
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
    let scheduleStale = false;
    let scheduleRetrySeconds = SCHEDULE_RETRY_SECONDS;

    function queueScheduleRefresh(seconds) {
        clearTimeout(scheduleTimer);
        const delay = Math.max(1, Number(seconds) || SCHEDULE_RETRY_SECONDS) * 1000;
        // Broadcast boundaries are inclusive server-side, so fetch just after the old slot ends.
        scheduleTimer = setTimeout(refreshSchedule, delay + 1000 + Math.random() * SCHEDULE_JITTER);
    }

    function applySchedule(schedule) {
        const current = schedule.current;
        if (!current) {
            queueScheduleRefresh(schedule.refresh_after);
            return;
        }

        const status = document.querySelector('[data-current-status]');
        const title = document.querySelector('[data-current-title]');
        status.textContent = current.show
            ? `Nu live · ${current.start_time} – ${current.end_time}`
            : `Nu op ${stationTitle}`;
        title.textContent = current.name;
        programName = current.name;
        updateMediaSession();

        renderCurrentMakers(current.show);
        renderProgress(current);
        renderUpcoming(Array.isArray(schedule.upcoming) ? schedule.upcoming : []);

        if (!streamElement) {
            // Preserve active track metadata while updating its fallback.
            const showingFallback = titleNode.textContent === fallbackTitle;
            fallbackTitle = current.name;
            fallbackArtist = current.show ? current.show.makers_label || '' : '';
            if (showingFallback) {
                renderFallback();
            }
        }

        queueScheduleRefresh(schedule.refresh_after);
    }

    async function refreshSchedule() {
        if (!radioPage || !radioPage.dataset.scheduleUrl || schedulePending) {
            return;
        }

        // Defer hidden-tab refreshes unless playback needs current Media Session metadata.
        if (document.hidden && !isPlaying) {
            scheduleStale = true;
            return;
        }

        schedulePending = true;
        try {
            const response = await fetch(radioPage.dataset.scheduleUrl, {
                cache: 'no-store',
                headers: {Accept: 'application/json'},
                signal: AbortSignal.timeout(SCHEDULE_TIMEOUT)
            });
            if (!response.ok) {
                throw new Error(`Schedule request failed with ${response.status}`);
            }

            const payload = await response.json();
            if (!payload || !payload.fm || !payload.fm.schedule) {
                throw new Error('Schedule response is incomplete');
            }

            scheduleRetrySeconds = SCHEDULE_RETRY_SECONDS;
            applySchedule(payload.fm.schedule);
        } catch (error) {
            // Exponential backoff plus jitter limits retries during endpoint failures.
            queueScheduleRefresh(scheduleRetrySeconds);
            scheduleRetrySeconds = Math.min(scheduleRetrySeconds * 2, SCHEDULE_RETRY_MAX_SECONDS);
        } finally {
            schedulePending = false;
        }
    }

    function setupPlayer() {
        const button = document.querySelector('[data-play]');
        if (!streamElement || !button || typeof videojs === 'undefined' || !window.zwVideoJsHtml5) {
            return;
        }

        const player = videojs(streamElement, {
            controls: false,
            liveui: true,
            preload: 'none',
            html5: window.zwVideoJsHtml5()
        });

        const playIcon = button.querySelector('[data-play-icon="play"]');
        const pauseIcon = button.querySelector('[data-play-icon="pause"]');
        const setPlaying = function (playing) {
            isPlaying = playing;
            button.setAttribute('aria-label', playing ? 'Pauzeer' : 'Luister live');
            playIcon.hidden = playing;
            pauseIcon.hidden = !playing;

            if ('mediaSession' in navigator) {
                navigator.mediaSession.playbackState = playing ? 'playing' : 'paused';
            }
        };

        const startPlayback = function () {
            // Reload before playing: this is a live stream, so resuming must jump to the edge.
            player.load();
            player.play()?.catch(() => setPlaying(false));
            setPlaying(true);
            updateMediaSession();

            // Media Session can start playback while hidden; reconnect its feeds too.
            resumeFeeds();
        };

        player.on('playing', () => setPlaying(true));
        player.on('pause', () => setPlaying(false));
        player.on('error', () => setPlaying(false));

        button.addEventListener('click', function () {
            if (!player.paused()) {
                player.pause();
                return;
            }

            startPlayback();
        });

        if ('mediaSession' in navigator) {
            navigator.mediaSession.setActionHandler('play', startPlayback);
            navigator.mediaSession.setActionHandler('pause', () => player.pause());
            try {
                navigator.mediaSession.setActionHandler('stop', () => player.pause());
            } catch (error) {
                // Some browsers expose Media Session without the `stop` action.
            }
        }
    }

    startProgress();
    setupPlayer();
    connectMetadata();
    if (radioPage) {
        queueScheduleRefresh(radioPage.dataset.scheduleRefreshAfter);
    }

})();

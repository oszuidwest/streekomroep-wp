// Kept out of fm-live.js so a fault in the diagnostics path can never take the player down.
(function () {
    const PANEL = document.querySelector('[data-stream-diagnostics]');
    if (!PANEL) {
        return;
    }

    const PROBE_TIMEOUT = 10000;
    const LIVE_INTERVAL = 250;
    // Live streams push a timeupdate several times a second, so a second of silence is a stall.
    const STALL_AFTER = 1500;

    const PROBES = [
        ['MP3', 'audio/mpeg'],
        ['AAC container (kaal)', 'audio/mp4'],
        ['AAC-LC', 'audio/mp4; codecs="mp4a.40.2"'],
        ['HE-AAC v1 (AAC+)', 'audio/mp4; codecs="mp4a.40.5"'],
        ['HE-AAC v2 (eAAC+)', 'audio/mp4; codecs="mp4a.40.29"'],
        ['Rauwe AAC (ADTS)', 'audio/aac'],
        ['Icecast AAC+', 'audio/aacp'],
        ['Ogg Vorbis', 'audio/ogg; codecs="vorbis"'],
        ['Ogg Opus', 'audio/ogg; codecs="opus"'],
        ['HLS (native)', 'application/vnd.apple.mpegurl'],
        ['HLS (legacy MIME)', 'application/x-mpegURL']
    ];

    const MEDIA_ERRORS = {
        1: 'MEDIA_ERR_ABORTED (afgebroken)',
        2: 'MEDIA_ERR_NETWORK (netwerk of CORS)',
        3: 'MEDIA_ERR_DECODE (bytes komen binnen, decoder weigert)',
        4: 'MEDIA_ERR_SRC_NOT_SUPPORTED (bron of codec niet ondersteund)'
    };

    const NETWORK_STATES = ['EMPTY', 'IDLE', 'LOADING', 'NO_SOURCE'];
    const READY_STATES = ['HAVE_NOTHING', 'HAVE_METADATA', 'HAVE_CURRENT_DATA', 'HAVE_FUTURE_DATA', 'HAVE_ENOUGH_DATA'];

    const OK = 'font-bold text-green-700 dark:text-green-400';
    const BAD = 'font-bold text-red-700 dark:text-red-400';
    const MEH = 'font-bold text-amber-700 dark:text-amber-400';

    const answers = {};
    const tester = document.createElement('audio');
    const stream = document.getElementById('zw-fm-stream');

    function canPlay(mime) {
        try {
            return tester.canPlayType(mime) || '';
        } catch (error) {
            return '';
        }
    }

    function supportsViaMse(mime) {
        if (!window.MediaSource || typeof MediaSource.isTypeSupported !== 'function') {
            return null;
        }

        try {
            return MediaSource.isTypeSupported(mime);
        } catch (error) {
            return false;
        }
    }

    function addCell(row, text, className) {
        const cell = document.createElement('td');
        cell.className = `py-2 pr-3 align-top ${className || ''}`.trim();
        cell.textContent = text;
        row.appendChild(cell);
        return cell;
    }

    function addCodeCell(row, text) {
        const cell = document.createElement('td');
        cell.className = 'py-2 pr-3 align-top';
        const code = document.createElement('code');
        code.className = 'text-xs break-all';
        code.textContent = text;
        cell.appendChild(code);
        row.appendChild(cell);
        return cell;
    }

    function shortSrc(url) {
        if (!url) {
            return 'geen';
        }

        // The interesting part of a stream URL is the mount point at the end, not the host.
        return url.length > 48 ? `…${url.slice(-48)}` : url;
    }

    function setupLive() {
        const body = PANEL.querySelector('[data-diagnostics-live]');
        const verdict = PANEL.querySelector('[data-diagnostics-live-verdict]');
        if (!stream) {
            verdict.textContent = 'Geen audio-element op de pagina: alle streamvelden in ACF zijn leeg.';
            return;
        }

        const fields = ['speelt', 'tijd', 'gekozen bron', 'readyState', 'networkState', 'fout', 'volume', 'gebufferd'];
        const cells = {};
        fields.forEach(function (label) {
            const row = document.createElement('tr');
            row.className = 'border-b border-gray-800/5 dark:border-gray-200/5';
            addCell(row, label, 'font-bold');
            cells[label] = addCell(row, '-');
            body.appendChild(row);
        });

        // Seed from the current value so the first reading is not mistaken for movement.
        let lastTime = stream.currentTime;
        let lastMoved = 0;

        const tick = function () {
            const now = Date.now();
            if (stream.currentTime !== lastTime) {
                lastTime = stream.currentTime;
                lastMoved = now;
            }

            const advancing = !stream.paused && lastMoved !== 0 && now - lastMoved < STALL_AFTER;
            let buffered = 'geen';
            try {
                if (stream.buffered.length) {
                    buffered = `${(stream.buffered.end(stream.buffered.length - 1) - stream.currentTime).toFixed(1)}s vooruit`;
                }
            } catch (error) {
                buffered = 'onleesbaar';
            }

            cells['speelt'].textContent = stream.paused ? 'nee' : 'ja';
            cells['speelt'].className = `py-2 pr-3 align-top ${stream.paused ? '' : OK}`;
            cells['tijd'].textContent = `${stream.currentTime.toFixed(1)}s ${advancing ? '(loopt op)' : '(staat stil)'}`;
            cells['tijd'].className = `py-2 pr-3 align-top ${advancing ? OK : MEH}`;
            cells['gekozen bron'].textContent = shortSrc(stream.currentSrc);
            cells['readyState'].textContent = `${stream.readyState} (${READY_STATES[stream.readyState] || '?'})`;
            cells['networkState'].textContent = `${stream.networkState} (${NETWORK_STATES[stream.networkState] || '?'})`;
            cells['fout'].textContent = stream.error ? (MEDIA_ERRORS[stream.error.code] || stream.error.code) : 'geen';
            cells['fout'].className = `py-2 pr-3 align-top ${stream.error ? BAD : ''}`;
            cells['volume'].textContent = `${Math.round(stream.volume * 100)}%${stream.muted ? ' (GEDEMPT)' : ''}`;
            cells['volume'].className = `py-2 pr-3 align-top ${stream.muted || stream.volume === 0 ? BAD : ''}`;
            cells['gebufferd'].textContent = buffered;

            // The whole point of this panel: separate "no sound because playback failed" from
            // "no sound while playback is demonstrably running", which are different bugs.
            if (stream.error) {
                verdict.textContent = `Fout: ${MEDIA_ERRORS[stream.error.code] || stream.error.code}. De speler kwam niet aan de praat.`;
            } else if (stream.networkState === 3) {
                verdict.textContent = 'Geen bruikbare bron: elke kandidaat is afgewezen. Zie de tabel onderaan welke.';
            } else if (stream.paused) {
                verdict.textContent = 'Staat stil. Druk hierboven op de playknop.';
            } else if (!advancing) {
                verdict.textContent = 'Zegt te spelen maar de tijd loopt niet op: de stream stalt of buffert eindeloos. Dit is een netwerk- of serverprobleem, geen codecprobleem.';
            } else if (stream.muted || stream.volume === 0) {
                verdict.textContent = 'Speelt af, maar het element staat gedempt of op volume nul. Daar zit de stilte.';
            } else {
                verdict.textContent = 'Het element speelt aantoonbaar af: de tijd loopt op, er is geen fout en het volume staat open. Hoor je dan nog niets, dan ligt het niet aan de bronkeuze maar aan de uitvoer: mediavolume van het toestel, een bluetooth- of headsetuitgang, of een decoder die wel doorloopt maar stilte produceert. Kijk welke bron gekozen is.';
            }
        };

        tick();
        setInterval(tick, LIVE_INTERVAL);
    }

    function renderCodecs() {
        const body = PANEL.querySelector('[data-diagnostics-codecs]');
        PROBES.forEach(function ([label, mime]) {
            const answer = canPlay(mime);
            answers[mime] = answer;

            const mse = supportsViaMse(mime);
            const row = document.createElement('tr');
            row.className = 'border-b border-gray-800/5 dark:border-gray-200/5';

            addCell(row, label);
            addCodeCell(row, mime);
            addCell(row, answer || 'nee', answer === 'probably' ? OK : (answer === 'maybe' ? MEH : BAD));
            addCell(row, mse === null ? '-' : (mse ? 'ja' : 'nee'), mse ? OK : '');
            body.appendChild(row);
        });
    }

    function renderVerdict() {
        const verdict = PANEL.querySelector('[data-diagnostics-verdict]');
        const container = answers['audio/mp4'];
        const heAac = answers['audio/mp4; codecs="mp4a.40.5"'] || answers['audio/mp4; codecs="mp4a.40.29"'];
        const lines = [];

        if (!container) {
            lines.push('Deze browser wijst audio/mp4 af, dus de AAC-bron wordt overgeslagen en MP3 gekozen.');
        } else if (!heAac) {
            lines.push(`Let op: audio/mp4 wordt geaccepteerd (${container}) maar HE-AAC wordt afgewezen. Is de stream AAC+, dan claimt dit toestel hem te kunnen en kan hij hem niet decoderen.`);
        } else {
            lines.push('Dit toestel claimt AAC-LC en HE-AAC aan te kunnen.');
        }

        if (!answers['audio/mpeg']) {
            lines.push('Waarschuwing: zelfs MP3 wordt afgewezen, wat op een uitgeklede WebView wijst.');
        }

        verdict.textContent = lines.join(' ');
    }

    function readSources() {
        return Array.from(document.querySelectorAll('#zw-fm-stream source'), function (element) {
            return {src: element.getAttribute('src') || '', type: element.getAttribute('type') || ''};
        });
    }

    function renderSources(sources) {
        const body = PANEL.querySelector('[data-diagnostics-sources]');
        body.textContent = '';

        if (!sources.length) {
            const row = document.createElement('tr');
            const cell = addCell(row, 'Geen bronnen in de pagina. Alle stream-velden in ACF zijn leeg.', MEH);
            cell.colSpan = 4;
            body.appendChild(row);
            return [];
        }

        let chosen = -1;
        return sources.map(function (source, index) {
            const answer = canPlay(source.type);
            const picked = chosen === -1 && answer !== '';
            if (picked) {
                chosen = index;
            }

            const row = document.createElement('tr');
            row.className = 'border-b border-gray-800/5 dark:border-gray-200/5';
            addCell(row, String(index + 1));

            const srcCell = document.createElement('td');
            srcCell.className = 'py-2 pr-3 align-top';
            const code = document.createElement('code');
            code.className = 'text-xs break-all';
            code.textContent = source.src;
            srcCell.appendChild(code);
            srcCell.appendChild(document.createElement('br'));
            const type = document.createElement('span');
            type.className = 'text-xs text-gray-500 dark:text-gray-400';
            type.textContent = `${source.type} → canPlayType: ${answer || 'nee'}`;
            srcCell.appendChild(type);

            if (location.protocol === 'https:' && source.src.indexOf('http://') === 0) {
                srcCell.appendChild(document.createElement('br'));
                const warning = document.createElement('strong');
                warning.className = BAD;
                warning.textContent = 'mixed content: https-pagina met http-stream';
                srcCell.appendChild(warning);
            }

            row.appendChild(srcCell);
            addCell(row, picked ? 'browser begint hier' : (answer ? 'kan, alleen als reserve' : 'overgeslagen'), picked ? OK : '');
            const result = addCell(row, 'nog niet getest', MEH);
            body.appendChild(row);

            return {source: source, result: result};
        });
    }

    function testSource(source, cell) {
        return new Promise(function (resolve) {
            const element = new Audio();
            element.muted = true;
            element.preload = 'auto';

            let settled = false;
            const finish = function (text, className) {
                if (settled) {
                    return;
                }

                settled = true;
                clearTimeout(timer);
                cell.textContent = text;
                cell.className = `py-2 pr-3 align-top ${className}`;
                try {
                    element.pause();
                    element.removeAttribute('src');
                    element.load();
                } catch (error) {
                    // The element is already torn down.
                }

                resolve();
            };

            const timer = setTimeout(function () {
                finish('timeout, geen data en geen fout', BAD);
            }, PROBE_TIMEOUT);

            element.addEventListener('playing', () => finish('speelt af', OK));
            element.addEventListener('canplay', () => finish('genoeg gebufferd', OK));
            element.addEventListener('error', function () {
                const code = element.error ? element.error.code : 0;
                finish(MEDIA_ERRORS[code] || `onbekende fout ${code}`, BAD);
            });

            element.src = source.src;
            element.load();
            // A rejected play() says nothing about the codec, so keep waiting for the media events.
            element.play()?.catch(() => {});
        });
    }

    function renderEnv() {
        PANEL.querySelector('[data-diagnostics-env]').textContent = [
            `userAgent: ${navigator.userAgent}`,
            `secure context: ${window.isSecureContext ? 'ja' : 'nee'}`,
            `MediaSource: ${window.MediaSource ? 'aanwezig' : 'afwezig'}`,
            `Video.js: ${typeof videojs === 'undefined' ? 'niet geladen' : videojs.VERSION}`
        ].join(' | ');
    }

    setupLive();
    renderCodecs();
    renderVerdict();
    renderEnv();
    renderSources(readSources());

    PANEL.querySelector('[data-diagnostics-run]').addEventListener('click', async function (event) {
        const button = event.currentTarget;
        button.disabled = true;
        button.textContent = 'Bezig…';

        const rows = renderSources(readSources());
        for (const row of rows) {
            await testSource(row.source, row.result);
        }

        button.disabled = false;
        button.textContent = 'Test opnieuw';
    });
})();

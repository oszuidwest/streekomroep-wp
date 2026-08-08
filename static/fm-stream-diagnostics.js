// Kept out of fm-live.js so a fault in the diagnostics path can never take the player down.
(function () {
    const PANEL = document.querySelector('[data-stream-diagnostics]');
    if (!PANEL) {
        return;
    }

    const PROBE_TIMEOUT = 10000;

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

    const OK = 'font-bold text-green-700 dark:text-green-400';
    const BAD = 'font-bold text-red-700 dark:text-red-400';
    const MEH = 'font-bold text-amber-700 dark:text-amber-400';

    const answers = {};
    const tester = document.createElement('audio');

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

        // The theme advertises its AAC stream as bare `audio/mp4`, so that is the answer the player
        // acts on. A device that accepts the container but rejects HE-AAC will pick a stream it
        // cannot decode, and Video.js commits to that one source instead of trying the MP3 behind it.
        if (!container) {
            lines.push('Deze browser wijst audio/mp4 af, dus de speler slaat de AAC-bron over en pakt MP3. Dit is niet de oorzaak.');
        } else if (!heAac) {
            lines.push(`Verdacht: audio/mp4 wordt geaccepteerd (${container}) maar HE-AAC wordt afgewezen. Is de stream AAC+, dan kiest de speler hem wel en kan dit toestel hem niet decoderen.`);
        } else {
            lines.push('Dit toestel claimt AAC-LC en HE-AAC aan te kunnen, dus de stilte komt waarschijnlijk niet door de codec.');
        }

        if (!answers['audio/mpeg']) {
            lines.push('Waarschuwing: zelfs MP3 wordt afgewezen, wat op een uitgeklede WebView wijst.');
        }

        verdict.textContent = lines.join(' ');
    }

    function readSources() {
        // Read the server-rendered <source> children rather than a separate data attribute, so the
        // panel can never disagree with what the player is actually offered.
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
            addCell(row, picked ? 'speler kiest deze' : (answer ? 'kan, wordt niet gekozen' : 'overgeslagen'), picked ? OK : '');
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

    renderCodecs();
    renderVerdict();
    renderEnv();

    let rows = renderSources(readSources());

    PANEL.querySelector('[data-diagnostics-run]').addEventListener('click', async function (event) {
        const button = event.currentTarget;
        button.disabled = true;
        button.textContent = 'Bezig…';

        rows = renderSources(readSources());
        for (const row of rows) {
            await testSource(row.source, row.result);
        }

        button.disabled = false;
        button.textContent = 'Test opnieuw';
    });
})();

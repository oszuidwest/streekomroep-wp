import assert from 'node:assert/strict';
import {readFileSync} from 'node:fs';
import test from 'node:test';
import vm from 'node:vm';

const playerSource = readFileSync(new URL('../static/fm-live.js', import.meta.url), 'utf8');

class FakeEventTarget {
    constructor() {
        this.listeners = new Map();
    }

    addEventListener(type, listener) {
        const listeners = this.listeners.get(type) || [];
        listeners.push(listener);
        this.listeners.set(type, listeners);
    }

    removeEventListener(type, listener) {
        const listeners = this.listeners.get(type) || [];
        this.listeners.set(type, listeners.filter((candidate) => candidate !== listener));
    }

    dispatch(type, detail = {}) {
        const event = {target: this, ...detail};
        for (const listener of [...(this.listeners.get(type) || [])]) {
            listener(event);
        }
    }
}

class FakeSource extends FakeEventTarget {
    constructor(src, type) {
        super();
        this.src = src;
        this.type = type;
    }

    cloneNode() {
        return new FakeSource(this.src, this.type);
    }
}

class FakeTextTracks extends FakeEventTarget {
    constructor() {
        super();
        this.tracks = [];
    }

    [Symbol.iterator]() {
        return this.tracks[Symbol.iterator]();
    }

    add(track) {
        this.tracks.push(track);
        this.dispatch('addtrack', {track});
    }
}

class FakeAudio extends FakeEventTarget {
    constructor(sources, {deferFirstPlay = false} = {}) {
        super();
        this.sources = sources;
        this.attributes = new Map();
        this.currentSrc = '';
        this.currentTime = 0;
        this.networkState = 1;
        this.paused = true;
        this.playCalls = 0;
        this.loadCalls = 0;
        this.textTracks = new FakeTextTracks();
        this.deferFirstPlay = deferFirstPlay;
        this.rejectFirstPlay = null;
    }

    querySelector(selector) {
        if (selector === 'source[type="application/x-mpegURL"]') {
            return this.sources.find((source) => source.type === 'application/x-mpegURL') || null;
        }
        if (selector === 'source:not([type="application/x-mpegURL"])') {
            return this.sources.find((source) => source.type !== 'application/x-mpegURL') || null;
        }
        return null;
    }

    querySelectorAll(selector) {
        if (selector === 'source:not([type="application/x-mpegURL"])') {
            return this.sources.filter((source) => source.type !== 'application/x-mpegURL');
        }
        return [];
    }

    setAttribute(name, value) {
        this.attributes.set(name, String(value));
    }

    getAttribute(name) {
        return this.attributes.get(name) ?? null;
    }

    removeAttribute(name) {
        this.attributes.delete(name);
    }

    replaceChildren(...sources) {
        this.sources = sources;
    }

    canPlayType() {
        return 'maybe';
    }

    load() {
        this.loadCalls++;
    }

    play() {
        this.playCalls++;
        this.paused = false;
        if (this.deferFirstPlay && this.playCalls === 1) {
            return new Promise((_resolve, reject) => {
                this.rejectFirstPlay = reject;
            });
        }
        return Promise.resolve();
    }

    pause() {
        this.paused = true;
        this.dispatch('pause');
    }
}

function createHarness({deferFirstPlay = false, pauseDuringRecovery = false, withHls = true, withHlsSource = true} = {}) {
    const sources = [];
    if (withHlsSource) {
        sources.push(new FakeSource('https://radio.example/live.m3u8', 'application/x-mpegURL'));
    }
    sources.push(
        new FakeSource('https://radio.example/live.aac', 'audio/aac'),
        new FakeSource('https://radio.example/live.mp3', 'audio/mpeg')
    );

    const audio = new FakeAudio(sources, {deferFirstPlay});
    const title = {textContent: 'ZuidWest FM'};
    const artist = {textContent: 'In heel West-Brabant'};
    const playIcon = {hidden: false};
    const pauseIcon = {hidden: true};
    const button = new FakeEventTarget();
    button.attributes = new Map();
    button.querySelector = (selector) => selector.includes('="play"') ? playIcon : pauseIcon;
    button.setAttribute = (name, value) => button.attributes.set(name, String(value));
    button.click = () => button.dispatch('click');

    const radioPage = {
        dataset: {
            artwork: '[]',
            metadataUrl: 'wss://metadata.example',
            radioTagline: 'In heel West-Brabant',
            radioTitle: 'ZuidWest FM',
            scheduleRefreshAfter: '60',
            scheduleUrl: 'https://radio.example/schedule'
        }
    };

    const selectorMap = new Map([
        ['[data-radio-live]', radioPage],
        ['[data-now-title]', title],
        ['[data-now-artist]', artist],
        ['[data-current-title]', {textContent: 'Testprogramma'}],
        ['[data-play]', button]
    ]);
    const document = new FakeEventTarget();
    document.hidden = false;
    document.querySelector = (selector) => selectorMap.get(selector) || null;
    document.getElementById = (id) => id === 'zw-fm-stream' ? audio : null;

    class FakeWebSocket extends FakeEventTarget {
        static CLOSING = 2;
        static instances = [];

        constructor(url) {
            super();
            this.url = url;
            this.readyState = 1;
            this.closed = false;
            FakeWebSocket.instances.push(this);
        }

        close() {
            this.closed = true;
            this.readyState = 3;
            this.dispatch('close');
        }

        message(payload) {
            this.dispatch('message', {data: JSON.stringify(payload)});
        }
    }

    class FakeHls {
        static Events = {ERROR: 'error', LEVEL_UPDATED: 'level-updated'};
        static ErrorTypes = {MEDIA_ERROR: 'mediaError'};
        static instances = [];
        static isSupported() {
            return true;
        }

        constructor() {
            this.listeners = new Map();
            this.onceListeners = new Map();
            this.startLoadCalls = 0;
            this.stopLoadCalls = 0;
            this.recoverMediaErrorCalls = 0;
            this.destroyCalls = 0;
            this.liveSyncPosition = 0;
            FakeHls.instances.push(this);
        }

        on(type, listener) {
            this.listeners.set(type, listener);
        }

        once(type, listener) {
            this.onceListeners.set(type, listener);
        }

        emit(type, data) {
            this.listeners.get(type)?.(type, data);
            const onceListener = this.onceListeners.get(type);
            if (onceListener) {
                this.onceListeners.delete(type);
                onceListener(type, data);
            }
        }

        loadSource(url) {
            this.url = url;
        }

        attachMedia(media) {
            this.media = media;
            media.setAttribute('src', 'blob:https://radio.example/revoked');
        }

        startLoad() {
            this.startLoadCalls++;
        }

        stopLoad() {
            this.stopLoadCalls++;
        }

        recoverMediaError() {
            this.recoverMediaErrorCalls++;
            if (pauseDuringRecovery) {
                this.media.paused = true;
                this.media.dispatch('pause');
            }
        }

        destroy() {
            this.destroyCalls++;
        }
    }

    const window = {WebSocket: FakeWebSocket};
    if (withHls) {
        window.Hls = FakeHls;
    }

    const mediaSession = {
        actions: new Map(),
        setActionHandler(action, handler) {
            this.actions.set(action, handler);
        }
    };

    vm.runInNewContext(playerSource, {
        AbortSignal: {timeout: () => null},
        HTMLMediaElement: {NETWORK_NO_SOURCE: 3},
        JSON,
        Math,
        MediaMetadata: class {
            constructor(metadata) {
                Object.assign(this, metadata);
            }
        },
        Number,
        Promise,
        String,
        WebSocket: FakeWebSocket,
        clearInterval() {},
        clearTimeout() {},
        document,
        fetch: async () => ({ok: false}),
        navigator: {mediaSession},
        setInterval: () => 1,
        setTimeout: () => 1,
        window
    });

    return {audio, artist, button, FakeHls, FakeWebSocket, mediaSession, title};
}

test('fatal HLS errors select Icecast and preserve WebSocket metadata', () => {
    const harness = createHarness();
    harness.FakeWebSocket.instances[0].message({
        artist: 'WebSocket-artiest',
        title: 'WebSocket-titel'
    });
    harness.button.click();
    const hls = harness.FakeHls.instances[0];

    hls.emit(harness.FakeHls.Events.ERROR, {fatal: true, type: 'networkError'});

    assert.equal(hls.destroyCalls, 1);
    assert.equal(harness.audio.getAttribute('src'), null);
    assert.deepEqual(harness.audio.sources.map((source) => source.type), ['audio/aac', 'audio/mpeg']);
    assert.equal(harness.audio.loadCalls, 1);
    assert.equal(harness.audio.playCalls, 2);
    assert.equal(harness.title.textContent, 'WebSocket-titel');
    assert.equal(harness.artist.textContent, 'WebSocket-artiest');
});

test('fatal HLS errors replace owned ID3 metadata and reconnect the WebSocket', () => {
    const harness = createHarness();
    harness.button.click();
    const hls = harness.FakeHls.instances[0];
    const metadataTrack = new FakeEventTarget();
    metadataTrack.kind = 'metadata';
    harness.audio.textTracks.add(metadataTrack);
    metadataTrack.activeCues = [
        {value: {key: 'TIT2', data: 'HLS-titel'}},
        {value: {key: 'TPE1', data: 'HLS-artiest'}}
    ];
    metadataTrack.dispatch('cuechange');

    assert.equal(harness.FakeWebSocket.instances[0].closed, true);

    hls.emit(harness.FakeHls.Events.ERROR, {fatal: true, type: 'networkError'});

    assert.equal(harness.title.textContent, 'ZuidWest FM');
    assert.equal(harness.artist.textContent, 'In heel West-Brabant');
    assert.equal(harness.FakeWebSocket.instances.length, 2);

    harness.FakeWebSocket.instances[1].message({artist: 'Nieuwe artiest', title: 'Nieuwe titel'});

    assert.equal(harness.title.textContent, 'Nieuwe titel');
    assert.equal(harness.artist.textContent, 'Nieuwe artiest');
});

test('native HLS takes over valid ID3 metadata and falls back on playback errors', () => {
    const harness = createHarness({withHls: false});
    harness.button.click();
    harness.audio.currentSrc = 'https://radio.example/live.m3u8';
    harness.audio.dispatch('playing');
    const socket = harness.FakeWebSocket.instances[0];
    socket.message({artist: 'WebSocket-artiest', title: 'WebSocket-titel'});

    assert.equal(socket.closed, false);

    const metadataTrack = new FakeEventTarget();
    metadataTrack.kind = 'metadata';
    harness.audio.textTracks.add(metadataTrack);

    metadataTrack.activeCues = [{value: {key: 'TPE1', data: 'ID3 zonder titel'}}];
    metadataTrack.dispatch('cuechange');

    assert.equal(socket.closed, false);
    assert.equal(harness.title.textContent, 'WebSocket-titel');
    assert.equal(harness.artist.textContent, 'WebSocket-artiest');

    metadataTrack.activeCues = [
        {value: {key: 'TIT2', data: 'Testtitel'}},
        {value: {key: 'TPE1', data: 'Testartiest'}}
    ];
    metadataTrack.dispatch('cuechange');

    assert.equal(socket.closed, true);
    assert.equal(harness.title.textContent, 'Testtitel');
    assert.equal(harness.artist.textContent, 'Testartiest');

    harness.audio.dispatch('error');

    assert.deepEqual(harness.audio.sources.map((source) => source.type), ['audio/aac', 'audio/mpeg']);
    assert.equal(harness.audio.playCalls, 2);
    assert.equal(harness.audio.paused, false);
    assert.equal(harness.button.attributes.get('aria-label'), 'Pauzeer');
});

test('an ID3 title without artist ends the current track', () => {
    const harness = createHarness();
    harness.button.click();
    const metadataTrack = new FakeEventTarget();
    metadataTrack.kind = 'metadata';
    harness.audio.textTracks.add(metadataTrack);
    metadataTrack.activeCues = [
        {value: {key: 'TIT2', data: 'HLS-titel'}},
        {value: {key: 'TPE1', data: 'HLS-artiest'}}
    ];
    metadataTrack.dispatch('cuechange');

    assert.equal(harness.title.textContent, 'HLS-titel');

    metadataTrack.activeCues = [{value: {key: 'TIT2', data: 'Nu: Testprogramma'}}];
    metadataTrack.dispatch('cuechange');

    assert.equal(harness.title.textContent, 'ZuidWest FM');
    assert.equal(harness.artist.textContent, 'In heel West-Brabant');
    assert.equal(harness.mediaSession.metadata.title, 'ZuidWest FM');
    assert.equal(harness.mediaSession.metadata.artist, 'In heel West-Brabant');
    assert.equal(harness.FakeWebSocket.instances.length, 1);
    assert.equal(harness.FakeWebSocket.instances[0].closed, true);
});

test('a title-only ID3 cue takes over from WebSocket metadata', () => {
    const harness = createHarness();
    harness.button.click();
    const socket = harness.FakeWebSocket.instances[0];
    socket.message({artist: 'WebSocket-artiest', title: 'WebSocket-titel'});
    const metadataTrack = new FakeEventTarget();
    metadataTrack.kind = 'metadata';
    harness.audio.textTracks.add(metadataTrack);
    metadataTrack.activeCues = [{value: {key: 'TIT2', data: 'Nu: Testprogramma'}}];
    metadataTrack.dispatch('cuechange');

    assert.equal(socket.closed, true);
    assert.equal(harness.title.textContent, 'ZuidWest FM');
    assert.equal(harness.artist.textContent, 'In heel West-Brabant');
});

test('fatal media recovery ignores an internal pause and still honours a user pause', () => {
    const harness = createHarness({pauseDuringRecovery: true});
    harness.button.click();
    const hls = harness.FakeHls.instances[0];

    hls.emit(harness.FakeHls.Events.ERROR, {
        fatal: true,
        type: harness.FakeHls.ErrorTypes.MEDIA_ERROR
    });

    assert.equal(hls.recoverMediaErrorCalls, 1);
    assert.equal(hls.startLoadCalls, 2);
    assert.equal(hls.stopLoadCalls, 0);
    assert.equal(harness.button.attributes.get('aria-label'), 'Pauzeer');

    harness.button.click();

    assert.equal(harness.audio.paused, true);
    assert.equal(hls.stopLoadCalls, 1);
    assert.equal(harness.button.attributes.get('aria-label'), 'Luister live');
});

test('an aborted older play promise cannot cancel recovered playback', async () => {
    const harness = createHarness({deferFirstPlay: true});
    harness.button.click();
    const hls = harness.FakeHls.instances[0];

    hls.emit(harness.FakeHls.Events.ERROR, {
        fatal: true,
        type: harness.FakeHls.ErrorTypes.MEDIA_ERROR
    });
    harness.audio.rejectFirstPlay(new Error('AbortError'));
    await Promise.resolve();

    assert.equal(harness.audio.paused, false);
    assert.equal(harness.button.attributes.get('aria-label'), 'Pauzeer');
});

test('a fatal media error after a user pause does not restart playback', () => {
    const harness = createHarness();
    harness.button.click();
    const hls = harness.FakeHls.instances[0];
    harness.button.click();
    const playCalls = harness.audio.playCalls;
    const startLoadCalls = hls.startLoadCalls;

    hls.emit(harness.FakeHls.Events.ERROR, {
        fatal: true,
        type: harness.FakeHls.ErrorTypes.MEDIA_ERROR
    });

    assert.equal(hls.recoverMediaErrorCalls, 1);
    assert.equal(hls.startLoadCalls, startLoadCalls);
    assert.equal(harness.audio.playCalls, playCalls);
    assert.equal(harness.button.attributes.get('aria-label'), 'Luister live');
});

test('a fatal network error after a user pause falls back without restarting playback', () => {
    const harness = createHarness();
    harness.button.click();
    const hls = harness.FakeHls.instances[0];
    harness.button.click();
    const playCalls = harness.audio.playCalls;

    hls.emit(harness.FakeHls.Events.ERROR, {fatal: true, type: 'networkError'});

    assert.equal(hls.destroyCalls, 1);
    assert.equal(harness.audio.playCalls, playCalls);
    assert.equal(harness.audio.paused, true);
    assert.equal(harness.button.attributes.get('aria-label'), 'Luister live');
});

test('a system pause cancels recovery intent and Media Session stop stays stopped', () => {
    const harness = createHarness();
    harness.button.click();
    const hls = harness.FakeHls.instances[0];

    harness.audio.pause();
    hls.emit(harness.FakeHls.Events.ERROR, {fatal: true, type: 'networkError'});

    assert.equal(harness.audio.playCalls, 1);
    assert.equal(harness.button.attributes.get('aria-label'), 'Luister live');

    harness.button.click();
    harness.mediaSession.actions.get('stop')();
    hls.emit(harness.FakeHls.Events.ERROR, {fatal: true, type: 'networkError'});

    assert.equal(harness.audio.playCalls, 2);
    assert.equal(harness.audio.paused, true);
    assert.equal(harness.button.attributes.get('aria-label'), 'Luister live');
});

test('a system pause after restarting a recovered stream cancels fallback intent', () => {
    const harness = createHarness({pauseDuringRecovery: true});
    harness.button.click();
    const hls = harness.FakeHls.instances[0];

    hls.emit(harness.FakeHls.Events.ERROR, {
        fatal: true,
        type: harness.FakeHls.ErrorTypes.MEDIA_ERROR
    });
    harness.button.click();
    harness.button.click();
    harness.audio.pause();
    const playCalls = harness.audio.playCalls;

    hls.emit(harness.FakeHls.Events.ERROR, {fatal: true, type: 'networkError'});

    assert.equal(harness.audio.playCalls, playCalls);
    assert.equal(harness.audio.paused, true);
    assert.equal(harness.button.attributes.get('aria-label'), 'Luister live');
});

test('a late HLS error after a media element error cannot restart playback', () => {
    const harness = createHarness();
    harness.button.click();
    const hls = harness.FakeHls.instances[0];
    harness.audio.paused = true;
    harness.audio.dispatch('error');
    const playCalls = harness.audio.playCalls;

    hls.emit(harness.FakeHls.Events.ERROR, {fatal: true, type: 'networkError'});

    assert.equal(harness.audio.playCalls, playCalls);
    assert.equal(harness.audio.paused, true);
    assert.equal(harness.button.attributes.get('aria-label'), 'Luister live');
});

test('a second fatal media error falls back to Icecast', () => {
    const harness = createHarness();
    harness.button.click();
    const hls = harness.FakeHls.instances[0];
    const fatalMediaError = {fatal: true, type: harness.FakeHls.ErrorTypes.MEDIA_ERROR};

    hls.emit(harness.FakeHls.Events.ERROR, fatalMediaError);
    hls.emit(harness.FakeHls.Events.ERROR, fatalMediaError);

    assert.equal(hls.recoverMediaErrorCalls, 1);
    assert.equal(hls.destroyCalls, 1);
    assert.deepEqual(harness.audio.sources.map((source) => source.type), ['audio/aac', 'audio/mpeg']);
});

test('pausing after HLS metadata reconnects WebSocket metadata', () => {
    const harness = createHarness();
    harness.button.click();
    const metadataTrack = new FakeEventTarget();
    metadataTrack.kind = 'metadata';
    harness.audio.textTracks.add(metadataTrack);
    metadataTrack.activeCues = [
        {value: {key: 'TIT2', data: 'HLS-titel'}},
        {value: {key: 'TPE1', data: 'HLS-artiest'}}
    ];
    metadataTrack.dispatch('cuechange');

    harness.button.click();

    assert.equal(harness.FakeWebSocket.instances.length, 2);
    harness.FakeWebSocket.instances[1].message({artist: 'WebSocket-artiest', title: 'WebSocket-titel'});

    assert.equal(harness.title.textContent, 'WebSocket-titel');
    assert.equal(harness.artist.textContent, 'WebSocket-artiest');
    assert.equal(harness.button.attributes.get('aria-label'), 'Luister live');
});

test('rejecting the HLS source preserves WebSocket metadata', () => {
    const harness = createHarness({withHls: false});
    const hlsSource = harness.audio.sources[0];
    const socket = harness.FakeWebSocket.instances[0];
    socket.message({artist: 'WebSocket-artiest', title: 'WebSocket-titel'});

    harness.audio.dispatch('error', {target: hlsSource});

    assert.equal(socket.closed, false);
    assert.equal(harness.title.textContent, 'WebSocket-titel');
    assert.equal(harness.artist.textContent, 'WebSocket-artiest');
});

test('pausing an Icecast stream preserves the current metadata', () => {
    const harness = createHarness({withHls: false, withHlsSource: false});
    harness.button.click();
    harness.FakeWebSocket.instances[0].message({
        artist: 'Testartiest',
        title: 'Testtitel'
    });

    harness.audio.pause();

    assert.equal(harness.title.textContent, 'Testtitel');
    assert.equal(harness.artist.textContent, 'Testartiest');
    assert.equal(harness.button.attributes.get('aria-label'), 'Luister live');

    harness.button.click();

    assert.equal(harness.audio.playCalls, 2);
    assert.equal(harness.title.textContent, 'Testtitel');
    assert.equal(harness.artist.textContent, 'Testartiest');
    assert.equal(harness.button.attributes.get('aria-label'), 'Pauzeer');
});

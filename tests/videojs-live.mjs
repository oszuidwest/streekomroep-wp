import assert from 'node:assert/strict';
import {readFileSync} from 'node:fs';
import test from 'node:test';

const liveSource = readFileSync(new URL('../static/videojs-live.js', import.meta.url), 'utf8');
const AsyncFunction = Object.getPrototypeOf(async function () {}).constructor;

// Runs the module body against fake globals; defined() lets <hlsjs-video> register.
function load(players) {
    let defined;
    const definition = new Promise((resolve) => {
        defined = resolve;
    });
    const fakeDocument = {
        querySelectorAll(selector) {
            assert.equal(selector, 'hlsjs-video[data-live-src]');
            return players;
        },
    };
    const fakeCustomElements = {
        whenDefined(name) {
            assert.equal(name, 'hlsjs-video');
            return definition;
        },
    };
    const done = new AsyncFunction('customElements', 'document', liveSource)(fakeCustomElements, fakeDocument);
    return {defined, done};
}

test('the live stream gets the playlist retry budget and an explicit HLS type', async () => {
    const player = {dataset: {liveSrc: 'https://example.test/live.m3u8'}};
    const {defined, done} = load([player]);
    defined();
    await done;

    assert.equal(player.source.src, 'https://example.test/live.m3u8');
    assert.equal(player.source.type, 'application/vnd.apple.mpegurl');
    // hls.js replaces the whole policy, so every field must be present.
    assert.deepEqual(player.source.engine.hlsJs.playlistLoadPolicy, {
        default: {
            maxTimeToFirstByteMs: 10000,
            maxLoadTimeMs: 20000,
            timeoutRetry: {maxNumRetry: 2, retryDelayMs: 0, maxRetryDelayMs: 0},
            errorRetry: {maxNumRetry: 30, retryDelayMs: 1000, maxRetryDelayMs: 4000},
        },
    });
});

test('the source waits until hlsjs-video is defined', async () => {
    const player = {dataset: {liveSrc: 'https://example.test/live.m3u8'}};
    const {defined, done} = load([player]);
    await Promise.resolve();
    // Setting it earlier would shadow the element's own source setter.
    assert.equal(player.source, undefined);

    defined();
    await done;
    assert.ok(player.source);
});

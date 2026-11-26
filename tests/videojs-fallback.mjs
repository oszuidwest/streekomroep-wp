import assert from 'node:assert/strict';
import {readFileSync} from 'node:fs';
import test from 'node:test';

const fallbackSource = readFileSync(new URL('../static/videojs-fallback.js', import.meta.url), 'utf8');

// Runs the script against fake globals; define(tag) resolves whenDefined(tag).
function load(videos) {
    const definitions = new Map();
    const definition = (tag) => {
        if (!definitions.has(tag)) {
            let resolve;
            const promise = new Promise((done) => {
                resolve = done;
            });
            definitions.set(tag, {promise, resolve});
        }
        return definitions.get(tag);
    };
    const fakeDocument = {
        querySelectorAll(selector) {
            assert.equal(selector, 'video-player video[controls], live-video-player video[controls]');
            return videos;
        },
    };
    const fakeCustomElements = {whenDefined: (tag) => definition(tag).promise};
    new Function('customElements', 'document', fallbackSource)(fakeCustomElements, fakeDocument);
    return {
        async define(tag) {
            definition(tag).resolve();
            await new Promise((done) => setImmediate(done));
        },
    };
}

function nativeVideo(playerTag) {
    const attributes = new Set(['controls']);
    return {
        attributes,
        closest(selector) {
            assert.equal(selector, 'video-player, live-video-player');
            return {localName: playerTag};
        },
        removeAttribute(name) {
            attributes.delete(name);
        },
    };
}

test('native controls stay until their own player is defined', async () => {
    const onDemand = nativeVideo('video-player');
    const live = nativeVideo('live-video-player');
    const players = load([onDemand, live]);
    await new Promise((done) => setImmediate(done));
    // A blocked or missing Video.js module never defines the player.
    assert.ok(onDemand.attributes.has('controls'));
    assert.ok(live.attributes.has('controls'));

    await players.define('video-player');
    assert.ok(!onDemand.attributes.has('controls'));
    assert.ok(live.attributes.has('controls'));

    await players.define('live-video-player');
    assert.ok(!live.attributes.has('controls'));
});

// Every player wraps a native <video controls>, so it still plays when the Video.js
// modules do not load (no JavaScript, a blocked CDN). Once the player is defined it
// draws its own controls, so the native ones go. Without hlsjs-video.js the player
// still plays that native <video> itself.
document.querySelectorAll('video-player video[controls], live-video-player video[controls]').forEach((video) => {
    const player = video.closest('video-player, live-video-player');
    customElements.whenDefined(player.localName).then(() => video.removeAttribute('controls'));
});

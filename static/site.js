function initDarkMode() {
    const dark = localStorage.theme === 'dark'
        || (!('theme' in localStorage) && window.matchMedia('(prefers-color-scheme: dark)').matches);
    document.documentElement.classList.toggle('dark', dark);
    document.getElementById('themeLightBtn').hidden = dark;
    document.getElementById('themeDarkBtn').hidden = !dark;
}

document.querySelectorAll('[data-theme]').forEach(function (button) {
    button.onclick = function () {
        localStorage.theme = button.dataset.theme;
        initDarkMode();
    };
});
initDarkMode();

document.querySelectorAll('[data-scroller]').forEach(function (scroller) {
    const track = scroller.querySelector('[data-scroller-track]');
    const nav = scroller.querySelector('[data-scroller-nav]');
    const prev = scroller.querySelector('[data-scroller-prev]');
    const next = scroller.querySelector('[data-scroller-next]');
    if (!track || !nav || !prev || !next) {
        return;
    }
    function update() {
        const max = track.scrollWidth - track.clientWidth;
        nav.hidden = max <= 4;
        prev.disabled = track.scrollLeft <= 4;
        next.disabled = track.scrollLeft >= max - 4;
    }
    prev.onclick = function () {
        track.scrollBy({left: -track.clientWidth, behavior: 'smooth'});
    };
    next.onclick = function () {
        track.scrollBy({left: track.clientWidth, behavior: 'smooth'});
    };
    track.addEventListener('scroll', update, {passive: true});
    window.addEventListener('resize', update);
    update();
});

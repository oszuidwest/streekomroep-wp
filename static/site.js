// Keep the persisted preference aligned with the theme toggle controls.
function initDarkMode() {
    const dark = localStorage.theme === 'dark'
        || (!('theme' in localStorage) && window.matchMedia('(prefers-color-scheme: dark)').matches);
    document.documentElement.classList.toggle('dark', dark);
    document.getElementById('themeLightBtn').hidden = !dark;
    document.getElementById('themeDarkBtn').hidden = dark;
}

document.querySelectorAll('[data-theme]').forEach(function (button) {
    button.onclick = function () {
        localStorage.theme = button.dataset.theme;
        initDarkMode();
    };
});
initDarkMode();

// Share paging and disabled-state handling across every horizontal carousel.
document.querySelectorAll('[data-scroller]').forEach(function (scroller) {
    const find = (part) => scroller.querySelector(`[data-scroller-${part}]`);
    const [track, nav, prev, next] = ['track', 'nav', 'prev', 'next'].map(find);
    if (!track || !nav || !prev || !next) {
        return;
    }
    function update() {
        const max = track.scrollWidth - track.clientWidth;
        nav.hidden = max <= 4;
        prev.disabled = track.scrollLeft <= 4;
        next.disabled = track.scrollLeft >= max - 4;
    }
    prev.onclick = () => track.scrollBy({left: -track.clientWidth, behavior: 'smooth'});
    next.onclick = () => track.scrollBy({left: track.clientWidth, behavior: 'smooth'});
    track.addEventListener('scroll', update, {passive: true});
    window.addEventListener('resize', update);
    update();
});

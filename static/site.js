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
const SCROLLER_TOLERANCE = 4;
document.querySelectorAll('[data-scroller]').forEach(function (scroller) {
    const find = (part) => scroller.querySelector(`[data-scroller-${part}]`);
    const [track, nav, prev, next] = ['track', 'nav', 'prev', 'next'].map(find);
    if (!track || !nav || !prev || !next) {
        return;
    }
    function pageSize() {
        const style = getComputedStyle(track);
        const padding = (parseFloat(style.scrollPaddingLeft) || 0)
            + (parseFloat(style.scrollPaddingRight) || 0);
        const visible = Math.max(track.clientWidth - padding, 0);
        const item = track.firstElementChild;
        if (!item) {
            return visible;
        }
        // Page by whole item strides so paging lands on the snap grid; the
        // last visible item has no trailing gap, hence the gap credit.
        const gap = parseFloat(style.columnGap) || 0;
        const stride = item.getBoundingClientRect().width + gap;
        if (stride <= 0) {
            return visible;
        }
        return stride * Math.max(1, Math.floor((visible + gap + SCROLLER_TOLERANCE) / stride));
    }
    function update() {
        const max = track.scrollWidth - track.clientWidth;
        nav.hidden = max <= SCROLLER_TOLERANCE;
        prev.disabled = track.scrollLeft <= SCROLLER_TOLERANCE;
        next.disabled = track.scrollLeft >= max - SCROLLER_TOLERANCE;
    }
    prev.onclick = () => track.scrollBy({left: -pageSize(), behavior: 'smooth'});
    next.onclick = () => track.scrollBy({left: pageSize(), behavior: 'smooth'});
    track.addEventListener('scroll', update, {passive: true});
    window.addEventListener('resize', update);
    update();
});

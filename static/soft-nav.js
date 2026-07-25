/**
 * Client-side navigation, so the radio keeps playing while you read on.
 *
 * A full page load tears down the audio element, and no browser API brings it back. The only way
 * to keep a stream running is to not navigate at all: fetch the next page, swap #zw-page for the
 * one that came back, and leave the player outside it untouched.
 *
 * Deliberately narrow:
 * - It only takes over while audio is actually playing. Nobody listening means normal page loads,
 *   so search engines, first-time visitors and anyone just reading never touch this code.
 * - Anything unexpected (a redirect off-site, a non-HTML answer, a page without #zw-page, a
 *   failed fetch, a thrown error) falls back to a normal navigation. The stream stops, which is
 *   what would have happened anyway, and the visitor still gets their page.
 */
(function () {
    const PAGE_ID = 'zw-page';
    const SKIP_EXTENSIONS = /\.(?:pdf|jpe?g|png|gif|webp|avif|svg|zip|mp3|mp4|m4a|docx?|xlsx?)$/i;
    const EXECUTABLE = /^(?:text\/javascript|application\/javascript|module)$/i;

    let controller = null;

    const isPlaying = () => Boolean(window.zwRadio && window.zwRadio.isPlaying());

    function hardVisit(url) {
        window.location.href = url;
    }

    const isSameOrigin = (url) => url.origin === window.location.origin;

    /** Everything WordPress and its plugins own has to go through a real page load. */
    function isThemeRoute(url) {
        return !url.pathname.startsWith('/wp-')
            && !/\/feed\/?$/.test(url.pathname)
            && !SKIP_EXTENSIONS.test(url.pathname);
    }

    function isNavigable(url) {
        return isSameOrigin(url) && isThemeRoute(url);
    }

    function plainClick(event) {
        return !event.defaultPrevented && event.button === 0
            && !event.metaKey && !event.ctrlKey && !event.shiftKey && !event.altKey;
    }

    function findLink(event) {
        const link = event.target.closest('a[href]');
        if (!link || link.hasAttribute('download') || link.dataset.noSoftNav !== undefined) {
            return null;
        }

        const target = link.getAttribute('target');
        if (target && target !== '_self') {
            return null;
        }

        const url = new URL(link.href, window.location.href);
        if (!isNavigable(url)) {
            return null;
        }

        // Jumping within the page is the browser's job.
        if (url.hash && url.pathname === window.location.pathname && url.search === window.location.search) {
            return null;
        }

        return url;
    }

    /**
     * The scripts the new page brought with it.
     *
     * Taken before the swap, because moving #zw-page into this document takes its scripts with
     * it. Scripts that arrive through the DOM never run on their own, and WordPress prints a
     * plugin's tags only on the pages that need them. Anything already loaded is left alone:
     * running it twice would bind everything twice.
     */
    function collectScripts(doc) {
        const loaded = new Set(Array.from(document.querySelectorAll('script[src]')).map((node) => node.src));
        const seen = new Set(Array.from(document.querySelectorAll('script[id]')).map((node) => node.id));

        return Array.from(doc.querySelectorAll('script')).filter((node) => {
            if (node.type && !EXECUTABLE.test(node.type)) {
                return false;
            }

            if (node.src) {
                const src = new URL(node.getAttribute('src'), window.location.href).href;
                if (loaded.has(src)) {
                    return false;
                }
                loaded.add(src);
                return true;
            }

            // An unnamed inline script cannot be told apart from one that already ran.
            return Boolean(node.id) && !seen.has(node.id);
        });
    }

    function runScripts(scripts) {
        scripts.forEach((node) => {
            const script = document.createElement('script');
            Array.from(node.attributes).forEach((attribute) => script.setAttribute(attribute.name, attribute.value));
            script.textContent = node.textContent;
            document.body.appendChild(script);
        });
    }

    function swap(doc, url) {
        const next = doc.getElementById(PAGE_ID);
        const page = document.getElementById(PAGE_ID);
        if (!next || !page) {
            return false;
        }

        const scripts = collectScripts(doc);
        page.replaceWith(next);

        document.title = doc.title;
        // Templates key off the body classes, and so do a few plugins.
        document.body.className = doc.body.className;

        const canonical = document.querySelector('link[rel="canonical"]');
        const nextCanonical = doc.querySelector('link[rel="canonical"]');
        if (canonical && nextCanonical) {
            canonical.href = nextCanonical.href;
        }

        runScripts(scripts);

        const anchor = url.hash && document.getElementById(url.hash.slice(1));
        if (anchor) {
            anchor.scrollIntoView();
        } else {
            window.scrollTo(0, 0);
        }

        // Everything that bound itself to the old page rebinds here.
        document.dispatchEvent(new CustomEvent('zw:page'));
        return true;
    }

    async function visit(url, push) {
        if (controller) {
            controller.abort();
        }

        // Mark the entry being left, so going back to it lands here instead of nowhere.
        if (push && (!history.state || !history.state.zw)) {
            history.replaceState({zw: true}, '', window.location.href);
        }

        controller = new AbortController();
        document.documentElement.setAttribute('data-navigating', '');

        try {
            const response = await fetch(url.href, {
                signal: controller.signal,
                credentials: 'same-origin',
                headers: {'X-Requested-With': 'zw-soft-nav'},
            });

            if (!response.ok || !(response.headers.get('content-type') || '').includes('text/html')) {
                return hardVisit(url.href);
            }

            // A redirect may have left the site, or landed somewhere this cannot handle.
            const finalUrl = new URL(response.url);
            if (!isNavigable(finalUrl)) {
                return hardVisit(url.href);
            }

            const doc = new DOMParser().parseFromString(await response.text(), 'text/html');
            if (!swap(doc, finalUrl)) {
                return hardVisit(url.href);
            }

            if (push) {
                history.pushState({zw: true}, '', finalUrl.href);
            }
        } catch (error) {
            if (error.name !== 'AbortError') {
                hardVisit(url.href);
            }
        } finally {
            document.documentElement.removeAttribute('data-navigating');
        }
    }

    document.addEventListener('click', function (event) {
        if (!isPlaying() || !plainClick(event)) {
            return;
        }

        const url = findLink(event);
        if (!url) {
            return;
        }

        event.preventDefault();
        visit(url, true);
    });

    // Search is the one form people reach for mid-visit; anything that posts is left alone.
    document.addEventListener('submit', function (event) {
        const form = event.target;
        if (!isPlaying() || event.defaultPrevented || form.dataset.noSoftNav !== undefined) {
            return;
        }

        if ((form.getAttribute('method') || 'get').toLowerCase() !== 'get') {
            return;
        }

        const url = new URL(form.getAttribute('action') || window.location.href, window.location.href);
        if (!isNavigable(url)) {
            return;
        }

        url.search = new URLSearchParams(new FormData(form)).toString();
        event.preventDefault();
        visit(url, true);
    });

    window.addEventListener('popstate', function (event) {
        // Only entries this script marked can be restored without a load.
        if (!isPlaying() || !event.state || !event.state.zw) {
            return;
        }

        visit(new URL(window.location.href), false);
    });
})();

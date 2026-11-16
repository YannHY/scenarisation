(() => {
    'use strict';

    const form = document.querySelector('.native-consent-actions');
    if (form) {
        let submitted = false;
        form.addEventListener('submit', (event) => {
            if (submitted) {
                event.preventDefault();
                return;
            }
            submitted = true;
            form.setAttribute('aria-busy', 'true');
        });
        window.addEventListener('pageshow', () => {
            submitted = false;
            form.removeAttribute('aria-busy');
        });
    }

    const link = document.getElementById('native-return');
    if (!link) return;
    const url = new URL(link.href);
    // The server validates this return before storing it; keep the browser fallback loopback-only.
    if (url.protocol !== 'http:' || url.hostname !== '127.0.0.1' || !url.port
        || url.pathname !== '/auth/callback' || url.username || url.password || url.hash) return;
    window.location.replace(url.href);
})();

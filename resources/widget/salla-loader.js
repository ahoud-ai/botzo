/*
 * Runs inside Salla stores. The snippet approved by Salla only adds this file, so this file and
 * the server can change without a new review, and must keep working with that snippet.
 */
(function () {
    var current = document.currentScript;
    if (!current || window.__botzoSallaLoader) {
        return;
    }
    window.__botzoSallaLoader = true;

    var base = new URL(current.src).origin;
    var triesLeft = 50;

    function openWidget() {
        var id = window.salla.config.get('store.id');
        if (!/^[0-9]+$/.test(String(id))) {
            return;
        }

        fetch(base + '/api/salla/widget/' + id)
            .then(function (response) {
                return response.ok ? response.json() : null;
            })
            .then(function (data) {
                if (!data || !data.key) {
                    return;
                }
                var script = document.createElement('script');
                script.src = base + '/widget.js';
                script.async = true;
                script.setAttribute('data-key', data.key);
                document.head.appendChild(script);
            })
            .catch(function () {
                // The store must not be affected when Botzo is unreachable.
            });
    }

    // The theme may add salla after this file runs.
    (function waitForSalla() {
        if (window.salla && window.salla.onReady && window.salla.config) {
            window.salla.onReady(openWidget);
        } else if (triesLeft-- > 0) {
            setTimeout(waitForSalla, 200);
        }
    })();
})();

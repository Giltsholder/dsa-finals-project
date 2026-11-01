/* Closes the header hamburger menu when clicking outside it or pressing Escape. */
(function () {
    'use strict';

    document.addEventListener('click', function (event) {
        var inside = event.target.closest ? event.target.closest('details.menu') : null;
        var open = document.querySelectorAll('details.menu[open]');
        for (var i = 0; i < open.length; i++) {
            if (open[i] !== inside) open[i].removeAttribute('open');
        }
    });

    document.addEventListener('keydown', function (event) {
        if (event.key !== 'Escape') return;
        var open = document.querySelector('details.menu[open]');
        if (!open) return;
        open.removeAttribute('open');
        var summary = open.querySelector('summary');
        if (summary) summary.focus();
    });
})();

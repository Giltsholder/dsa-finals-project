/* Adds an eye button to every password field on the page.
   Progressive enhancement: without JavaScript the fields work as normal. */
(function () {
    'use strict';

    var EYE_OPEN =
        '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" ' +
        'stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">' +
        '<path d="M2.062 12.348a1 1 0 0 1 0-.696 10.75 10.75 0 0 1 19.876 0 1 1 0 0 1 0 .696 10.75 10.75 0 0 1-19.876 0"/>' +
        '<circle cx="12" cy="12" r="3"/></svg>';

    var EYE_OFF =
        '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" ' +
        'stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">' +
        '<path d="M10.733 5.076a10.744 10.744 0 0 1 11.205 6.575 1 1 0 0 1 0 .696 10.747 10.747 0 0 1-1.444 2.49"/>' +
        '<path d="M14.084 14.158a3 3 0 0 1-4.242-4.242"/>' +
        '<path d="M17.479 17.499a10.75 10.75 0 0 1-15.417-5.151 1 1 0 0 1 0-.696 10.75 10.75 0 0 1 4.446-5.143"/>' +
        '<path d="m2 2 20 20"/></svg>';

    function enhance(input) {
        if (input.dataset.passwordToggle === 'on') return;
        input.dataset.passwordToggle = 'on';
        input.setAttribute('spellcheck', 'false');
        input.setAttribute('autocapitalize', 'off');
        input.setAttribute('autocorrect', 'off');

        var wrap = document.createElement('span');
        wrap.className = 'pass-field';
        input.parentNode.insertBefore(wrap, input);
        wrap.appendChild(input);

        var button = document.createElement('button');
        button.type = 'button';
        button.className = 'pass-toggle';
        button.innerHTML = EYE_OPEN;
        button.title = 'Show password';
        button.setAttribute('aria-label', 'Show password');
        button.setAttribute('aria-pressed', 'false');
        wrap.appendChild(button);

        button.addEventListener('click', function (event) {
            event.preventDefault();                 // don't let the surrounding <label> steal focus
            var show = input.type === 'password';
            input.type = show ? 'text' : 'password';
            button.innerHTML = show ? EYE_OFF : EYE_OPEN;
            button.title = show ? 'Hide password' : 'Show password';
            button.setAttribute('aria-pressed', show ? 'true' : 'false');
            button.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
        });
    }

    var fields = document.querySelectorAll('input[type="password"]');
    for (var i = 0; i < fields.length; i++) enhance(fields[i]);
})();

(function () {
    'use strict';

    document.addEventListener('click', function (event) {
        var link = event.target.closest('a[href="#"]');

        if (link) {
            event.preventDefault();
        }
    });
})();
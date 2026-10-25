(function () {
    'use strict';

    var uploaderTab = document.querySelector('.tr-top-uploader-tab');
    if (uploaderTab) {
        uploaderTab.addEventListener('click', function (e) {
            var td = e.target.closest('[data-table]');
            if (!td || td.classList.contains('nx-colhead')) return;
            var siblings = td.parentNode.children;
            for (var i = 0; i < siblings.length; i++) {
                siblings[i].classList.remove('nx-colhead');
            }
            td.classList.add('nx-colhead');
            var tables = document.querySelectorAll('.top-uploader');
            tables.forEach(function (t) { t.classList.add('nx-hidden'); });
            var target = document.querySelectorAll('.' + td.getAttribute('data-table'));
            target.forEach(function (t) {
                t.classList.remove('nx-hidden');
                t.style.opacity = '0';
                t.style.transition = 'opacity 0.2s';
                requestAnimationFrame(function () { t.style.opacity = '1'; });
            });
        });
    }
})();

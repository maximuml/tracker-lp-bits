(function () {
    'use strict';

    var clearBtn = document.getElementById('clear-shout-box');
    if (clearBtn) {
        clearBtn.addEventListener('click', function () {
            layer.confirm(clearBtn.getAttribute('data-confirm'), {title: 'Info', btn: ['Yes', 'Cancel'], btnAlign: 'c'}, function (layerIndex) {
                nativePost('/web/shoutbox/clear', {}, function (response) {
                    layer.close(layerIndex);
                    if (response.ret != 0) {
                        layer.alert(response.msg, {title: 'Info', btn: ['OK', 'Cancel'], btnAlign: 'c'});
                    } else {
                        var iframe = document.getElementById('iframe-shout-box');
                        if (iframe) {
                            iframe.src = '/web/shoutbox?type=shoutbox';
                        } else if (window.Livewire && typeof window.Livewire.dispatch === 'function') {
                            window.Livewire.dispatch('shout-refresh');
                        }
                    }
                });
            });
        });
    }

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

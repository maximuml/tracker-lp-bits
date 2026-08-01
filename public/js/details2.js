(function () {
    'use strict';

    function initDetails2() {
        const wrapper = document.querySelector('.d2-wrapper');
        if (!wrapper) return;
        const data = JSON.parse(wrapper.dataset.details2 || '{}');

        initTabs();
        initCopy();
        initBookmark(data.torrent_id);
        initThanks(data.torrent_id);
        initBonus(data.torrent_id);
        initApproval(data.torrent_id);
        initLazyLoad('.d2-load-files', '.d2-files-content', 'viewfilelist.php?id=');
        initLazyLoad('.d2-load-peers', '.d2-peers-content', 'viewpeerlist.php?id=');
    }

    function initTabs() {
        const tabs = document.querySelectorAll('.d2-tab');
        const panels = document.querySelectorAll('.d2-panel');
        if (!tabs.length) return;

        tabs.forEach(function (tab) {
            tab.addEventListener('click', function () {
                const target = tab.dataset.tab;
                tabs.forEach(function (t) { t.classList.remove('d2-tab--active'); });
                panels.forEach(function (p) { p.classList.remove('d2-panel--active'); });
                tab.classList.add('d2-tab--active');
                const panel = document.querySelector('.d2-panel[data-panel="' + target + '"]');
                if (panel) panel.classList.add('d2-panel--active');
            });
        });
    }

    function initCopy() {
        const urlBtn = document.getElementById('d2-copy-url');
        if (urlBtn) {
            urlBtn.addEventListener('click', function () {
                const toolbar = document.querySelector('.d2-toolbar');
                const url = toolbar ? toolbar.dataset.torrentUrl : '';
                copyText(url, urlBtn);
            });
        }

        const hashBtn = document.getElementById('d2-copy-hash');
        if (hashBtn) {
            hashBtn.addEventListener('click', function () {
                copyText(hashBtn.dataset.hash, hashBtn);
            });
        }

        const hashOnlyBtn = document.getElementById('d2-copy-hash-only');
        if (hashOnlyBtn) {
            hashOnlyBtn.addEventListener('click', function () {
                const code = document.getElementById('d2-info-hash');
                if (code) copyText(code.innerText, hashOnlyBtn);
            });
        }
    }

    function copyText(text, btn) {
        if (!text) return;
        if (navigator.clipboard) {
            navigator.clipboard.writeText(text).then(function () {
                flashButton(btn, 'Copied!');
            }).catch(function () {
                fallbackCopy(text, btn);
            });
        } else {
            fallbackCopy(text, btn);
        }
    }

    function fallbackCopy(text, btn) {
        const ta = document.createElement('textarea');
        ta.value = text;
        ta.style.position = 'fixed';
        ta.style.opacity = '0';
        document.body.appendChild(ta);
        ta.select();
        try {
            document.execCommand('copy');
            flashButton(btn, 'Copied!');
        } catch (e) {
            flashButton(btn, 'Error');
        }
        document.body.removeChild(ta);
    }

    function flashButton(btn, text) {
        const original = btn.innerText;
        btn.innerText = text;
        setTimeout(function () {
            btn.innerText = original;
        }, 1500);
    }

    function initBookmark(torrentId) {
        const btn = document.getElementById('d2-bookmark');
        if (!btn) return;

        btn.addEventListener('click', function () {
            btn.disabled = true;
            fetch('bookmark.php?torrentid=' + encodeURIComponent(torrentId))
                .then(function (r) { return r.text(); })
                .then(function (text) {
                    const isBookmarked = /added/i.test(text);
                    btn.innerHTML = '<span class="d2-icon">' + (isBookmarked ? '&#x2605;' : '&#x2606;') + '</span> ' + (isBookmarked ? 'Remove bookmark' : 'Add bookmark');
                    btn.dataset.bookmarked = isBookmarked ? '1' : '0';
                })
                .catch(function () { alert('Bookmark failed'); })
                .finally(function () { btn.disabled = false; });
        });
    }

    function initThanks(torrentId) {
        const btn = document.getElementById('d2-say-thanks');
        if (!btn) return;

        btn.addEventListener('click', function () {
            btn.disabled = true;
            const form = new URLSearchParams();
            form.append('id', torrentId);
            fetch('thanks.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: form.toString()
            })
                .then(function (r) { return r.text(); })
                .then(function () {
                    btn.innerText = 'You said thanks';
                    btn.disabled = true;
                })
                .catch(function () {
                    alert('Thanks failed');
                    btn.disabled = false;
                });
        });
    }

    function initBonus(torrentId) {
        const container = document.querySelector('.d2-bonus');
        if (!container) return;

        container.addEventListener('click', function (e) {
            const target = e.target.closest('.d2-give-bonus');
            if (!target) return;

            const value = target.dataset.value;
            if (!value || !confirm('Give ' + value + ' bonus?')) return;

            const form = new URLSearchParams();
            form.append('id', torrentId);
            form.append('value', value);

            fetch('magic.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: form.toString()
            })
                .then(function (r) { return r.json(); })
                .then(function (res) {
                    if (res && res.ret === 0) {
                        container.innerHTML = '<p>Bonus given: ' + value + '</p>';
                    } else {
                        alert(res && res.msg ? res.msg : 'Bonus failed');
                    }
                })
                .catch(function () { alert('Bonus failed'); });
        });
    }

    function initApproval(torrentId) {
        const btn = document.getElementById('d2-approve');
        if (!btn || typeof jQuery === 'undefined' || typeof layer === 'undefined') return;

        btn.addEventListener('click', function () {
            jQuery('#d2-approve').on('click', function () {
                layer.open({
                    type: 2,
                    title: 'Approval',
                    area: ['60%', '600px'],
                    content: '/web/torrent-approval-page?torrent_id=' + torrentId,
                });
            });
        });
    }

    function initLazyLoad(buttonSelector, contentSelector, urlPrefix) {
        const wrapper = document.querySelector(buttonSelector).closest('[data-torrent-id]');
        if (!wrapper) return;

        const btn = wrapper.querySelector(buttonSelector);
        const content = wrapper.querySelector(contentSelector);
        const torrentId = wrapper.dataset.torrentId;
        if (!btn || !content || !torrentId) return;

        btn.addEventListener('click', function () {
            if (wrapper.dataset.loaded === '1') {
                content.style.display = content.style.display === 'none' ? 'block' : 'none';
                btn.innerText = content.style.display === 'none' ? 'Show' : 'Hide';
                return;
            }
            btn.disabled = true;
            btn.innerText = 'Loading...';
            fetch(urlPrefix + encodeURIComponent(torrentId))
                .then(function (r) { return r.text(); })
                .then(function (html) {
                    content.innerHTML = html;
                    wrapper.dataset.loaded = '1';
                    content.style.display = 'block';
                    btn.innerText = 'Hide';
                })
                .catch(function () {
                    alert('Load failed');
                    btn.disabled = false;
                    btn.innerText = 'Show';
                });
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initDetails2);
    } else {
        initDetails2();
    }
})();

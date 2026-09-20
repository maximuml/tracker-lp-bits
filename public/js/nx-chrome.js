/**
 * Mobile chrome toggle (Stage 4.2): the burger button shows/hides the
 * combined nav+userbar container at narrow widths. Progressive
 * enhancement — without JS the container never receives the
 * nxm-collapse--ready class, so the menu stays fully expanded.
 */
(function () {
    var btn = document.querySelector('.nxm-burger');
    var panel = btn && document.getElementById(btn.getAttribute('aria-controls'));
    if (!btn || !panel) {
        return;
    }

    panel.classList.add('nxm-collapse--ready');

    function setOpen(open) {
        panel.classList.toggle('nxm-open', open);
        btn.setAttribute('aria-expanded', open ? 'true' : 'false');
    }

    btn.addEventListener('click', function () {
        setOpen(!panel.classList.contains('nxm-open'));
    });

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && panel.classList.contains('nxm-open')) {
            setOpen(false);
            btn.focus();
        }
    });
})();

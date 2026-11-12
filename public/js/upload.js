var uploadOfferSelect = document.querySelector('select[name="offer"]');
if (uploadOfferSelect) {
    uploadOfferSelect.addEventListener("change", function () {
        var id = this.value;
        if (id == 0) {
            return;
        }
        nativePost("/web/offers/show", { id: id }, function (response) {
            if (response.ret != 0) {
                alert(response.msg);
                return;
            }
            var nameEl = document.getElementById("name");
            if (nameEl) nameEl.value = response.data.name;
            clearContent();
            doInsert(response.data.descr, '', false);
            var catEl = document.getElementById("browsecat");
            if (catEl) {
                catEl.disabled = false;
                catEl.value = response.data.category;
                catEl.dispatchEvent(new Event('change'));
            }
        });
    });
}

var uploadComposeForm = document.getElementById("compose");
if (uploadComposeForm) {
    var syncRelationRows = function () {
        var typeSelect = uploadComposeForm.querySelector("select[name=type]");
        var mode = typeSelect ? typeSelect.getAttribute("data-mode") : null;
        var value = typeSelect ? typeSelect.value : '0';
        document.querySelectorAll("[relation]").forEach(function (el) { el.style.display = 'none'; });
        if (mode && parseInt(value, 10) > 0) {
            document.querySelectorAll('[relation="mode_' + mode + '"]').forEach(function (el) { el.style.display = ''; });
        }
    };
    uploadComposeForm.addEventListener("change", function (e) {
        if (!e.target || !e.target.matches("select[name=type]")) return;
        syncRelationRows();
    });
    // After a failed POST the form is re-rendered with the chosen category
    // still selected — the relation rows must be visible again on load.
    syncRelationRows();

    var uploadSubmitBtn = document.getElementById("qr");
    var uploadIdleLabel = uploadSubmitBtn ? uploadSubmitBtn.textContent : null;
    uploadComposeForm.addEventListener("submit", function () {
        if (!uploadSubmitBtn || uploadSubmitBtn.disabled) { return; }
        uploadSubmitBtn.disabled = true;
        uploadSubmitBtn.classList.add("is-processing");
        uploadSubmitBtn.setAttribute("aria-busy", "true");
        var label = uploadSubmitBtn.querySelector(".nx-upload__btn-text");
        if (label) {
            label.textContent = uploadSubmitBtn.getAttribute("data-processing-label") || "Processing…";
        }
    });
    window.addEventListener("pageshow", function (event) {
        if (event.persisted && uploadSubmitBtn) {
            uploadSubmitBtn.disabled = false;
            uploadSubmitBtn.classList.remove("is-processing");
            uploadSubmitBtn.removeAttribute("aria-busy");
            var label = uploadSubmitBtn.querySelector(".nx-upload__btn-text");
            if (label && uploadIdleLabel !== null) { label.textContent = uploadIdleLabel; }
        }
    });
} else {
    document.querySelectorAll("[relation]").forEach(function (el) { el.style.display = 'none'; });
}

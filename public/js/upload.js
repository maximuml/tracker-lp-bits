var uploadOfferSelect = document.querySelector('select[name="offer"]');
if (uploadOfferSelect) {
    uploadOfferSelect.addEventListener("change", function () {
        var id = this.value;
        if (id == 0) {
            return;
        }
        var params = { action: "getOffer", params: { id: id } };
        nativePost("ajax.php", params, function (response) {
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
    uploadComposeForm.addEventListener("change", function (e) {
        if (!e.target || !e.target.matches("select[name=type]")) return;
        var mode = e.target.getAttribute("data-mode");
        var value = e.target.value;
        document.querySelectorAll("tr[relation]").forEach(function (tr) { tr.style.display = 'none'; });
        if (value > 0) {
            document.querySelectorAll('tr[relation="mode_' + mode + '"]').forEach(function (tr) { tr.style.display = ''; });
        }
    });
}
document.querySelectorAll("tr[relation]").forEach(function (tr) { tr.style.display = 'none'; });

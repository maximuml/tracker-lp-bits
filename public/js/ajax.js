/**
 * Native AJAX helper functions (replaces jQuery.ajax / jQuery.post).
 *
 * Provides a simple promise-based API for POST requests that
 * automatically includes the CSRF token.
 */
window.nativePost = function (url, data, callback) {
    var formData = new FormData();
    for (var key in data) {
        if (data.hasOwnProperty(key)) {
            if (typeof data[key] === 'object' && data[key] !== null) {
                for (var subKey in data[key]) {
                    if (data[key].hasOwnProperty(subKey)) {
                        formData.append(key + '[' + subKey + ']', data[key][subKey]);
                    }
                }
            } else {
                formData.append(key, data[key]);
            }
        }
    }
    fetch(url, {
        method: 'POST',
        body: formData,
        credentials: 'same-origin',
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    }).then(function (res) { return res.json(); }).then(function (response) {
        if (callback) callback(response);
    }).catch(function () {
        if (callback) callback({ ret: 1, msg: 'Request failed' });
    });
};

/**
 * Serialize a form element's data into a plain object.
 * (replaces jQuery(form).serialize())
 */
window.serializeForm = function (form) {
    var data = {};
    var elements = form.querySelectorAll('input, select, textarea');
    for (var i = 0; i < elements.length; i++) {
        var el = elements[i];
        if (!el.name) continue;
        if (el.type === 'checkbox' && !el.checked) continue;
        if (el.type === 'radio' && !el.checked) continue;
        data[el.name] = el.value;
    }
    return data;
};

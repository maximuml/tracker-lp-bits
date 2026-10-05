var meiliAutoTimer = null;
var meiliAutoResults = [];
var meiliAutoSelected = -1;
var meiliAutoContainer = null;
var meiliAutoList = null;
var meiliAutoInput = null;
var meiliAutoXhr = null;
var meiliAutoQuery = '';

function meiliAutoInit()
{
    meiliAutoInput = document.getElementById('searchinput');
    if (!meiliAutoInput) {
        return;
    }

    meiliAutoInput.addEventListener('input', function () {
        meiliSuggestInput(this.value);
    });
    meiliAutoInput.addEventListener('keydown', function (e) {
        meiliSuggestKey(e);
    });

    meiliAutoContainer = document.createElement('div');
    meiliAutoContainer.id = 'meili-autocomplete-container';
    meiliAutoContainer.className = 'nx-autocomplete';
    meiliAutoContainer.style.display = 'none';
    meiliAutoContainer.style.position = 'absolute';
    meiliAutoContainer.style.zIndex = '1000';
    meiliAutoContainer.style.width = meiliAutoInput.offsetWidth + 'px';

    meiliAutoList = document.createElement('div');
    meiliAutoContainer.appendChild(meiliAutoList);
    meiliAutoInput.parentNode.appendChild(meiliAutoContainer);

    document.addEventListener('click', function (e) {
        if (e.target !== meiliAutoInput && e.target !== meiliAutoContainer && !meiliAutoContainer.contains(e.target)) {
            meiliAutoClose();
        }
    });
}

function meiliSuggestInput(value)
{
    clearTimeout(meiliAutoTimer);
    if (meiliAutoXhr) {
        try {
            meiliAutoXhr.abort();
        } catch (e) {}
        meiliAutoXhr = null;
    }
    var query = value.replace(/^\s+|\s+$/g, '');
    if (query.length < 2) {
        meiliAutoClose();
        return;
    }
    meiliAutoTimer = setTimeout(function () {
        meiliAutoFetch(query);
    }, 200);
}

function meiliSuggestKey(e)
{
    if (meiliAutoContainer.style.display === 'none') {
        return;
    }
    if (e.keyCode === 40) {
        e.preventDefault();
        meiliAutoSelectNext();
    } else if (e.keyCode === 38) {
        e.preventDefault();
        meiliAutoSelectPrev();
    } else if (e.keyCode === 27) {
        e.preventDefault();
        meiliAutoClose();
    } else if (e.keyCode === 13) {
        e.preventDefault();
        if (meiliAutoSelected >= 0) {
            meiliAutoChoose();
        } else {
            meiliAutoClose();
            if (meiliAutoInput.form) {
                meiliAutoInput.form.submit();
            }
        }
    }
}

function meiliAutoFetch(query)
{
    if (meiliAutoXhr) {
        try {
            meiliAutoXhr.abort();
        } catch (e) {}
    }
    meiliAutoQuery = query;
    var xhr = new XMLHttpRequest();
    meiliAutoXhr = xhr;
    xhr.open('GET', '/web/autocomplete_torrents?q=' + encodeURIComponent(query), true);
    xhr.onreadystatechange = function () {
        if (xhr.readyState === 4) {
            if (meiliAutoXhr === xhr) {
                meiliAutoXhr = null;
            }
            if (xhr.status === 200) {
                try {
                    var data = JSON.parse(xhr.responseText);
                    var currentQuery = meiliAutoInput.value.replace(/^\s+|\s+$/g, '');
                    if (query !== meiliAutoQuery && query !== currentQuery) {
                        return;
                    }
                    meiliAutoRender(data.torrents || []);
                } catch (ex) {
                    meiliAutoClose();
                }
            } else if (xhr.status !== 0) {
                meiliAutoClose();
            }
        }
    };
    xhr.send();
}

function meiliAutoRender(torrents)
{
    meiliAutoResults = torrents;
    meiliAutoSelected = -1;
    if (!torrents.length) {
        meiliAutoClose();
        return;
    }

    meiliAutoList.innerHTML = '';
    for (var i = 0; i < torrents.length; i++) {
        var item = document.createElement('div');
        item.className = 'nx-autocomplete-item';
        item.innerText = torrents[i].name;
        item.setAttribute('data-index', i);
        item.onmousedown = function (e) {
            e.preventDefault();
            meiliAutoSelected = parseInt(this.getAttribute('data-index'), 10);
            meiliAutoChoose();
        };
        item.onmouseover = function () {
            meiliAutoSelected = parseInt(this.getAttribute('data-index'), 10);
            meiliAutoUpdateHighlight();
        };
        meiliAutoList.appendChild(item);
    }

    meiliAutoContainer.style.display = 'block';
    meiliAutoContainer.style.width = meiliAutoInput.offsetWidth + 'px';
    meiliAutoUpdateHighlight();
}

function meiliAutoSelectNext()
{
    if (meiliAutoSelected < meiliAutoResults.length - 1) {
        meiliAutoSelected++;
        meiliAutoUpdateHighlight();
    }
}

function meiliAutoSelectPrev()
{
    if (meiliAutoSelected > 0) {
        meiliAutoSelected--;
        meiliAutoUpdateHighlight();
    }
}

function meiliAutoUpdateHighlight()
{
    var items = meiliAutoList.children;
    for (var i = 0; i < items.length; i++) {
        items[i].classList.toggle('nx-autocomplete-item--active', i === meiliAutoSelected);
    }
}

function meiliAutoChoose()
{
    if (meiliAutoSelected >= 0 && meiliAutoResults[meiliAutoSelected]) {
        meiliAutoInput.value = meiliAutoResults[meiliAutoSelected].name;
        meiliAutoClose();
        if (meiliAutoInput.form) {
            meiliAutoInput.form.submit();
        }
    }
}

function meiliAutoClose()
{
    if (meiliAutoContainer) {
        meiliAutoContainer.style.display = 'none';
    }
    meiliAutoResults = [];
    meiliAutoSelected = -1;
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', meiliAutoInit);
} else {
    meiliAutoInit();
}

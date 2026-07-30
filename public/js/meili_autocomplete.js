var meiliAutoTimer = null;
var meiliAutoResults = [];
var meiliAutoSelected = -1;
var meiliAutoContainer = null;
var meiliAutoList = null;
var meiliAutoInput = null;

function meiliAutoInit()
{
    meiliAutoInput = document.getElementById('searchinput');
    if (!meiliAutoInput) {
        return;
    }

    meiliAutoContainer = document.createElement('div');
    meiliAutoContainer.id = 'meili-autocomplete-container';
    meiliAutoContainer.style.display = 'none';
    meiliAutoContainer.style.position = 'absolute';
    meiliAutoContainer.style.zIndex = '1000';
    meiliAutoContainer.style.width = meiliAutoInput.offsetWidth + 'px';
    meiliAutoContainer.style.border = '1px solid rgb(119, 119, 119)';
    meiliAutoContainer.style.backgroundColor = 'rgb(255, 255, 255)';
    meiliAutoContainer.style.color = 'rgb(0, 0, 0)';

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
    var xhr = new XMLHttpRequest();
    xhr.open('GET', 'autocomplete_torrents.php?q=' + encodeURIComponent(query), true);
    xhr.onreadystatechange = function () {
        if (xhr.readyState === 4) {
            if (xhr.status === 200) {
                try {
                    var data = JSON.parse(xhr.responseText);
                    meiliAutoRender(data.torrents || []);
                } catch (ex) {
                    meiliAutoClose();
                }
            } else {
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
        item.style.padding = '4px 6px';
        item.style.cursor = 'pointer';
        item.style.whiteSpace = 'nowrap';
        item.style.overflow = 'hidden';
        item.style.textOverflow = 'ellipsis';
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
        if (i === meiliAutoSelected) {
            items[i].style.backgroundColor = '#3366cc';
            items[i].style.color = '#ffffff';
        } else {
            items[i].style.backgroundColor = '#ffffff';
            items[i].style.color = '#000000';
        }
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

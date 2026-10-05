<iframe id="{{ $iframeId }}" title="{{ 'Image preview' }}" src="{{ $baseUrl }}/web/attachment?callback_func={{ $callbackFunc }}" class="nx-attach-preview"></iframe><input id="{{ $inputId }}" type="text" name="{{ $name }}" value="{{ $value }}"><div id="{{ $previewBoxId }}">{{ $previewHtml }}</div><script @if ($cspNonce !== '')nonce="{{ $cspNonce }}"@endif>
    function {{ $callbackFunc }}(delkey, url)
    {
        var previewBox = document.getElementById('{{ $previewBoxId }}')
        var existsImg = document.getElementById('{{ $imgId }}')
        var input = document.getElementById('{{ $inputId }}')
        if (existsImg) {
            previewBox.removeChild(existsImg)
            input.value = ''
        }
        var img = document.createElement('img')
        img.src=url
        img.setAttribute('data-scale', '700x0')
        img.setAttribute('data-zoomable', '')
        input.value = '[attach]' + delkey + '[/attach]'
        img.id='{{ $imgId }}'
        previewBox.appendChild(img)
    }
</script>

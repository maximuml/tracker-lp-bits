@props(['name', 'label', 'smileRow', 'submitLabel'])
<label class="nx-sr-only" for="{{ $name }}">{{ $label }}</label><textarea id='{{ $name }}' name='{{ $name }}' cols="100" rows="8" data-ctrlenter="compose:qr"></textarea>{{ $smileRow }}<br /><input type="submit" id="qr" class="nx-postbtn" value="{{ $submitLabel }}" />

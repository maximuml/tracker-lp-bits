@foreach ($options as $o)
<option value="{{ $o['value'] }}"{{ $o['selected'] ? ' selected' : '' }}>{{ $o['label'] }}</option>
@endforeach

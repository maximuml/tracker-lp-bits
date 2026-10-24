@props(['name', 'selectName', 'chooseOneLabel', 'options'])
<b>{{ $name }}</b>&nbsp;<select name="{{ $selectName }}">
<option value="0">{{ $chooseOneLabel }}</option>
@foreach($options as $option){{ $option }}
@endforeach</select>&nbsp;&nbsp;&nbsp;

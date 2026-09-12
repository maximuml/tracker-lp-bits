@props(['label', 'name', 'options' => [], 'note' => null])
<tr>
    <td class="rowhead nowrap" valign="top" align="right">{{ $label }}</td>
    <td>
        @foreach ($options as $opt)
            <label><input type="checkbox" name="{{ $name }}" value="{{ $opt['value'] }}"@if ($opt['checked'] ?? false) checked @endif @if ($opt['disabled'] ?? false) disabled @endif>{{ $opt['label'] ?? '' }}</label>&nbsp;
        @endforeach
        @if ($note !== null && $note !== '')<br>{{ $note }}@endif
    </td>
</tr>

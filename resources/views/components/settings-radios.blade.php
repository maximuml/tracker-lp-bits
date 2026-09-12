@props(['label', 'name', 'options' => [], 'selected' => null, 'note' => null, 'break' => false])
<tr>
    <td class="rowhead nowrap" valign="top" align="right">{{ $label }}</td>
    <td>
        @foreach ($options as $val => $option)
            @if (is_array($option))
                <label><input type="radio" name="{{ $name }}" value="{{ $option['value'] ?? $val }}"@if ((string) $selected === (string) ($option['value'] ?? $val)) checked @endif @if ($option['disabled'] ?? false) disabled @endif>{{ $option['label'] ?? '' }}</label>@if ($break)<br>@else&nbsp;@endif
            @else
                <label><input type="radio" name="{{ $name }}" value="{{ $val }}"@if ((string) $selected === (string) $val) checked @endif>{{ $option }}</label>@if ($break)<br>@else&nbsp;@endif
            @endif
        @endforeach
        @if ($note !== null && $note !== '')<br>{{ $note }}@endif
    </td>
</tr>

@props(['layout' => 'tr', 'label', 'name', 'options' => [], 'note' => null])
@if ($layout === 'grid')
<div class="nx-fhead nx-nowrap">{{ $label }}</div>
<div class="nx-fcell">
@else
<tr>
    <td class="rowhead nowrap" valign="top" align="right">{{ $label }}</td>
    <td>
@endif
        @foreach ($options as $opt)
            <label><input type="checkbox" name="{{ $name }}" value="{{ $opt['value'] }}"@if ($opt['checked'] ?? false) checked @endif @if ($opt['disabled'] ?? false) disabled @endif>{{ $opt['label'] ?? '' }}</label>&nbsp;
        @endforeach
        @if ($note !== null && $note !== '')<br>{{ $note }}@endif
    @if ($layout === 'grid')
</div>
@else
</td>
</tr>
@endif

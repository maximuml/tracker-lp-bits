@props(['layout' => 'tr', 'label', 'name', 'options' => [], 'note' => null])
@if ($layout === 'grid')
<div class="nx-fhead whitespace-nowrap">{{ $label }}</div>
<div class="nx-fcell">
@else
<tr>
    <td class="whitespace-nowrap align-top px-2.5 py-1.5 text-right font-semibold text-nxm-text-dim">{{ $label }}</td>
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

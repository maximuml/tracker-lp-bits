{{-- Search-box category/taxonomy checkbox grid. Replaces the layout
     table markup of the removed SearchBox category-table helper with a flex grid. --}}
@props(['vm'])
<div class="nx-catgrid">
    @if ($vm->sectionName !== null)
    <div class="nx-catgrid__section"><span class="big">{{ $vm->sectionName }}</span></div>
    @endif
    @foreach ($vm->groups as $group)
    <div class="nx-catgrid__label">{{ $group->label }}</div>
    @foreach ($group->rows as $cells)
    <div class="nx-catgrid__row">
        @foreach ($cells as $cell)
        <div class="nx-catgrid__cell">
            @if ($cell->kind === 'selectAll')
            <input name="{{ $cell->checkPrefix }}_check" value="{{ __('nexus.select_all') }}" class="btn medium" type="button" data-setchecked="{{ $cell->checkPrefix }}" data-setchecked-ctrl="{{ $cell->checkPrefix }}_check" data-checkall="{{ __('nexus.select_all') }}" data-uncheckall="{{ __('nexus.unselect_all') }}">
            @elseif ($cell->kind === 'category')
            <input type="checkbox" id="{{ $cell->inputName }}" name="{{ $cell->inputName }}" value="{{ $cell->value }}" aria-label="{{ $cell->label !== '' ? $cell->label : $cell->inputName }}"{{ $cell->checked ? ' checked' : '' }} />
            <a href="{{ $cell->href }}" aria-label="{{ $cell->label !== '' ? $cell->label : $cell->inputName }}"><img src="{{ $cell->iconSrc }}" class="{{ $cell->iconClass }}" alt="{{ $cell->label }}" title="{{ $cell->label }}" /></a>
            @else
            <label><input type="checkbox" id="{{ $cell->inputName }}" name="{{ $cell->inputName }}" value="{{ $cell->value }}"{{ $cell->checked ? ' checked' : '' }} />@if ($cell->href !== null)<a href="{{ $cell->href }}">{{ $cell->label }}</a>@else{{ $cell->label }}@endif</label>
            @endif
        </div>
        @endforeach
    </div>
    @endforeach
    @endforeach
</div>

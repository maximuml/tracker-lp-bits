@if ($vm->sectionName !== null)
    <p class="nx-setcard__hint">{{ $vm->sectionName }}</p>
@endif
@foreach ($vm->groups as $groupIndex => $group)
    <div class="nx-setrow" role="group" aria-labelledby="ucp-g-categories-{{ $groupIndex }}">
        <div class="nx-setrow__label" id="ucp-g-categories-{{ $groupIndex }}">{{ $group->label }}</div>
        <div class="nx-setrow__control">
            <div class="nxm-chiprow">
                @foreach ($group->rows as $row)
                    @foreach ($row as $cell)
                        @if ($cell->kind === 'selectAll')
                            <input name="{{ $cell->checkPrefix }}_check" value="{{ __('nexus.select_all') }}" class="nx-btn nx-btn--ghost nxm-filter-all" type="button" data-setchecked="{{ $cell->checkPrefix }}" data-setchecked-ctrl="{{ $cell->checkPrefix }}_check" data-checkall="{{ __('nexus.select_all') }}" data-uncheckall="{{ __('nexus.unselect_all') }}">
                        @else
                            <label class="nx-chip"><input type="checkbox" class="nx-chip__input" id="{{ $cell->inputName }}" name="{{ $cell->inputName }}" value="{{ $cell->value }}" @checked($cell->checked)>{{ $cell->label !== '' ? $cell->label : $cell->inputName }}</label>
                        @endif
                    @endforeach
                @endforeach
            </div>
        </div>
    </div>
@endforeach

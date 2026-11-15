@extends('layouts.app')

@section('title', __('log.user_ban_log'))

@section('content')
<x-page :title="'User ban log'" width="list">
    <form id="filterForm" action="{{ $vm->serverRequestUri }}" method="get">
        <input id="q" type="text" name="q" value="{{ $vm->q }}" placeholder="{{ __('log.aria_username') }}" aria-label="{{ __('log.aria_username') }}">
        <input type="submit" class="nx-btn nx-btn--primary">
        <input type="reset" class="nx-btn js-filter-reset">
    </form>
<x-data-table caption="User ban log" captionHidden>
    <x-slot:head><thead><tr>
        @foreach ($vm->header as $label)
            <th class="bg-nxm-surface-alt font-semibold" scope="col">{{ $label }}</th>
        @endforeach
    </tr></thead></x-slot:head>
        @foreach ($vm->rows as $row)
            <tr>
                @foreach ($vm->header as $key => $label)
                    <td class="">{{ $row[$key] ?? '' }}</td>
                @endforeach
            </tr>
        @endforeach
</x-data-table>
<x-pagination :page="$vm->pager['page']" :pages="$vm->pager['pages']" :href="$vm->pager['href']" :prev-label="$vm->pager['prevLabel']" :next-label="$vm->pager['nextLabel']" :zero-based="$vm->pager['zeroBased']" label="Pagination bottom" />
</x-page>
@endsection

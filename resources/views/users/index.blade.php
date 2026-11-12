@extends('layouts.app')

@section('title', __('users.text_users'))

@section('content')
<x-page :title="__('users.text_users')" width="list">
<form class="nx-box mb-3 flex flex-wrap items-end gap-3 text-left" method="get" action="/web/users" role="search">
    <div class="flex flex-col gap-1 grow min-w-[12rem]">
        <label for="nx-users-search">{{ __('users.text_search') }}</label>
        <input id="nx-users-search" type="search" name="search" value="{{ $search }}">
    </div>
    <div class="flex flex-col gap-1">
        <label for="nx-users-class">{{ __('users.col_class') }}</label>
        <select id="nx-users-class" name="class">
        <option value="-">{{ __('users.select_any_class')}}</option>
        @foreach ($classOptions as $opt)
        <option value="{{ (int) $opt['value'] }}"@if ($opt['selected']) selected @endif>{{ $opt['label'] }}</option>
        @endforeach
        </select>
    </div>
    <div class="flex flex-col gap-1">
        <label for="nx-users-country">{{ __('users.col_country') }}</label>
        <select id="nx-users-country" name="country">
        @foreach ($countryOptions as $opt)
        <option value="{{ (int) $opt['value'] }}"@if ($opt['selected']) selected @endif>{{ $opt['label'] }}</option>
        @endforeach
        </select>
    </div>
    <button class="nx-btn nx-btn--primary" type="submit">{{ __('users.submit_okay')}}</button>
</form>

<p>
@foreach ($letterItems as $item)
@if ($item['href'] === null)
<span class="gray"><b>{{ $item['label'] }}</b></span>
@else
<a href="{{ $item['href'] }}"><b>{{ $item['label'] }}</b></a>
@endif
@endforeach
</p>

<x-pagination :page="$pager['page']" :pages="$pager['pages']" :href="$pager['href']" :prev-label="$pager['prevLabel']" :next-label="$pager['nextLabel']" :zero-based="$pager['zeroBased']" label="Pagination top" />

@if (empty($rows))
<x-empty-state :title="__('users.text_no_users')" />
@else
<x-data-table :caption="__('users.text_users')" :caption-hidden="true" :headers="[
    __('users.col_user_name'),
    __('users.col_registered'),
    __('users.col_last_access'),
    __('users.col_class'),
    __('users.col_country'),
]">
@foreach ($rows as $row)
<tr>
    <td>{{ $row['username_html'] }}</td>
    <td>{{ $row['addedFormatted'] }}</td>
    <td>{{ $row['lastAccessFormatted'] }}</td>
    <td>{{ $row['class_name'] }}</td>
    <td class="text-center">@if ($row['country'] > 0)<img width="32" height="24" src="{{ \App\Support\FlagIcon::path($row['country_flagpic'] ?? '') }}" alt="{{ $row['country_name'] }}" loading="lazy" decoding="async">@else---@endif</td>
</tr>
@endforeach
</x-data-table>
@endif

<x-pagination :page="$pager['page']" :pages="$pager['pages']" :href="$pager['href']" :prev-label="$pager['prevLabel']" :next-label="$pager['nextLabel']" :zero-based="$pager['zeroBased']" label="Pagination bottom" />
</x-page>
@endsection

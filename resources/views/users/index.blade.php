@extends('layouts.app', ['chromeVariant' => 'legacy'])

@section('title', __('users.text_users'))

@section('content')
<h1>{{ __('users.text_users') }}</h1>

<form method=get action=?>
{{ __('users.text_search')}} <input type=text name=search value="{{ $search }}">
<select name=class>
<option value='-'>{{ __('users.select_any_class')}}</option>
@foreach ($classOptions as $opt)
<option value="{{ (int) $opt['value'] }}"@if ($opt['selected']) selected @endif>{{ $opt['label'] }}</option>
@endforeach
</select>
<select name=country>
@foreach ($countryOptions as $opt)
<option value="{{ (int) $opt['value'] }}"@if ($opt['selected']) selected @endif>{{ $opt['label'] }}</option>
@endforeach
</select>
<input type=submit value="{{ __('users.submit_okay')}}">
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

{{ $pagertop ?? '' }}

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
    <td class="text-center">@if ($row['country'] > 0)<img src="pic/flag/{{ $row['country_flagpic'] }}" alt="{{ $row['country_name'] }}">@else---@endif</td>
</tr>
@endforeach
</x-data-table>

{{ $pagerbottom ?? '' }}
@endsection

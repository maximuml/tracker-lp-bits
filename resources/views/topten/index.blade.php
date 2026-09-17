@extends('layouts.legacy')

@section('title', __('legacy/topten.head_top_ten'))

@section('content')
<p align="center">@foreach ([1 => 'text_users', 2 => 'text_torrents', 3 => 'text_countries', 5 => 'text_community', 6 => 'text_other'] as $navType => $navKey)@if ($type === $navType && $limit === 10 && $subtype === null)<b>{{ __('legacy/topten.'.$navKey) }}</b>@else<a href="topten.php?type={{ $navType }}">{{ __('legacy/topten.'.$navKey) }}</a>@endif@if (! $loop->last) | @endif@endforeach
</p>

@foreach ($sections as $section)
<x-dynamic-component :component="'topten.'.$section['view']" :rows="$section['data']" :caption="$section['caption'].view('components.topten.limit-links', ['type' => $type, 'subtype' => $section['subtype'] ?? '', 'limits' => $section['limits'] ?? []])->render()" :what="$section['what'] ?? ''" />
@endforeach

<p><font class="small">{{ __('legacy/topten.text_this_page_last_updated')}}{{ date('Y-m-d H:i:s') }}, {{ __('legacy/topten.text_started_recording_date')}}{{ $dateFounded }}{{ __('legacy/topten.text_update_interval')}}</font></p>
@endsection

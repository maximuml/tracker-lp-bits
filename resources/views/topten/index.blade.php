@extends('layouts.legacy')

@section('title', $lang['head_top_ten'] ?? 'Top 10')

@section('content')
<p align="center">@foreach ([1 => 'text_users', 2 => 'text_torrents', 3 => 'text_countries', 5 => 'text_community', 6 => 'text_other'] as $navType => $navKey)@if ($type === $navType && $limit === 10 && $subtype === null)<b>{{ $lang[$navKey] ?? '' }}</b>@else<a href="topten.php?type={{ $navType }}">{{ $lang[$navKey] ?? '' }}</a>@endif@if (! $loop->last) | @endif@endforeach
</p>

@foreach ($sections as $section)
<x-dynamic-component :component="'topten.'.$section['view']" :rows="$section['data']" :caption="$section['caption'].view('components.topten.limit-links', ['type' => $type, 'subtype' => $section['subtype'] ?? '', 'limits' => $section['limits'] ?? [], 'lang' => $lang])->render()" :lang="$lang" :what="$section['what'] ?? ''" />
@endforeach

<p><font class="small">{{ $lang['text_this_page_last_updated'] ?? 'This page last updated ' }}{{ date('Y-m-d H:i:s') }}, {{ $lang['text_started_recording_date'] ?? 'Started recording account xfer stats on ' }}{{ $dateFounded }}{{ $lang['text_update_interval'] ?? '' }}</font></p>
@endsection

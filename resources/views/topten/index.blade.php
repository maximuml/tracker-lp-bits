@extends('layouts.app', ['chromeVariant' => 'legacy'])

@section('title', __('topten.head_top_ten'))

@section('content')
<h1 class="nx-sr-only">{{ __('topten.head_top_ten') }}</h1>
<p class="text-center">@foreach ([1 => 'text_users', 2 => 'text_torrents', 3 => 'text_countries', 5 => 'text_community', 6 => 'text_other'] as $navType => $navKey)@if ($type === $navType && $limit === 10 && $subtype === null)<b>{{ __('topten.'.$navKey) }}</b>@else<a href="/web/topten?type={{ $navType }}">{{ __('topten.'.$navKey) }}</a>@endif@if (! $loop->last) | @endif@endforeach
</p>

@foreach ($sections as $section)
<x-dynamic-component :component="'topten.'.$section['view']" :rows="$section['data']" :caption="\App\Support\Html\SafeHtml::fromUntrustedHtml($section['caption'].view('components.topten.limit-links', ['type' => $type, 'subtype' => $section['subtype'] ?? '', 'limits' => $section['limits'] ?? []])->render())" :what="$section['what'] ?? ''" />
@endforeach

<p><span class="small">{{ __('topten.text_this_page_last_updated')}}{{ $generatedAt }}, {{ __('topten.text_started_recording_date')}}{{ $dateFounded }}{{ __('topten.text_update_interval')}}</span></p>
@endsection

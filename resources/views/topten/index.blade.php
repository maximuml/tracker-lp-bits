@extends('layouts.app')

@section('title', __('topten.head_top_ten'))

@section('content')
<x-page :title="__('topten.head_top_ten')" width="list">
<x-slot:tabs><x-tabs :tabs="$toptenTabs" :active="(string) $type" :label="__('topten.head_top_ten')" /></x-slot:tabs>

@foreach ($sections as $section)
<x-dynamic-component :component="'topten.'.$section['view']" :rows="$section['data']" :caption="\App\Support\Html\SafeHtml::fromUntrustedHtml($section['caption'].view('components.topten.limit-links', ['type' => $type, 'subtype' => $section['subtype'] ?? '', 'limits' => $section['limits'] ?? []])->render())" :what="$section['what'] ?? ''" />
@endforeach

<p><span class="small">{{ __('topten.text_this_page_last_updated')}}{{ $generatedAt }}, {{ __('topten.text_started_recording_date')}}{{ $dateFounded }}{{ __('topten.text_update_interval')}}</span></p>
</x-page>
@endsection

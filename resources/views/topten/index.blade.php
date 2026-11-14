@extends('layouts.app')

@section('title', __('topten.head_top_ten'))

@section('content')
<x-page :title="__('topten.head_top_ten')" width="list">
<x-slot:tabs><x-tabs :tabs="$vm->tabs" :active="(string) $vm->type" :label="__('topten.head_top_ten')" /></x-slot:tabs>

@foreach ($vm->sections as $section)
<x-dynamic-component :component="'topten.'.$section->view" :rows="$section->rows" :caption="$section->captionHtml($vm->type)" :what="$section->what" />
@endforeach

<p><span class="small">{{ __('topten.text_this_page_last_updated')}}{{ $vm->generatedAt }}, {{ __('topten.text_started_recording_date')}}{{ $vm->dateFounded }}{{ __('topten.text_update_interval')}}</span></p>
</x-page>
@endsection

@extends('layouts.app', ['chromeVariant' => 'legacy'])

@section('title', __('legacy/tags.head_tags'))

@section('content')
<x-frame :caption="__('legacy/tags.text_tags')" :center="false">
<p>{{ __('legacy/tags.text_bb_tags_note') }} <b>{{ $siteName }}</b> {{ __('legacy/tags.text_bb_tags_note_two') }} <i>{{ __('legacy/tags.text_bb_tags') }}</i> {{ __('legacy/tags.text_bb_tags_note_end') }}</p>

<form method=post action=?>
<textarea name=test cols=60 rows=3>{{ $test ?? '' }}</textarea>
<input type=submit value="{{ __('legacy/tags.submit_test_this_code')}}">
</form>

@if (($test ?? '') !== '')
    <p><hr>{{ \App\Support\Format::formatComment($test) }}</hr></p>
@endif

@foreach ($tagItems ?? [] as $item)
    <p class=sub><b>{{ $item['name'] }}</b></p>
    <x-data-table :caption="__('legacy/tags.text_tags')" captionHidden class="nx-main">
    <tr class="align-top"><td class="w-[25%]">{{ __('legacy/tags.text_description')}}</td><td>{{ $item['description'] }}
    <tr class="align-top"><td>{{ __('legacy/tags.text_syntax')}}</td><td><tt>{{ $item['syntax'] }}</tt>
    <tr class="align-top"><td>{{ __('legacy/tags.text_example')}}</td><td><tt>{{ $item['example'] }}</tt>
    <tr class="align-top"><td>{{ __('legacy/tags.text_result')}}</td><td>{{ $item['result'] }}
    @if ($item['remarks'] !== '')
        <tr><td>{{ __('legacy/tags.text_remarks')}}</td><td>{{ $item['remarks'] }}
    @endif
    </x-data-table>
@endforeach
</x-frame>
@endsection

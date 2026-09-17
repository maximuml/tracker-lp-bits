@extends('layouts.legacy')

@section('title', __('legacy/tags.head_tags'))

@section('content')
{{ \App\Support\Frame::open((string) (__('legacy/tags.text_tags')), false, 10, '100%', 'left') }}
<p>@safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml(sprintf((string) (__('legacy/tags.text_bb_tags_note')), $siteName)))</p>

<form method=post action=?>
<textarea name=test cols=60 rows=3>{{ $test ?? '' }}</textarea>
<input type=submit style='height: 23px; margin-left: 5px' value="{{ __('legacy/tags.submit_test_this_code')}}">
</form>

@if (($test ?? '') !== '')
    <p><hr>@safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml(\App\Support\Format::formatComment($test)))</hr></p>
@endif

@foreach ($tagItems ?? [] as $item)
    <p class=sub><b>{{ $item['name'] }}</b></p>
    <table data-nx="data" class=main width=100% border=1 cellspacing=0 cellpadding=5>
    <tr valign=top><td width=25%>{{ __('legacy/tags.text_description')}}</td><td>{{ $item['description'] }}
    <tr valign=top><td>{{ __('legacy/tags.text_syntax')}}</td><td><tt>{{ $item['syntax'] }}</tt>
    <tr valign=top><td>{{ __('legacy/tags.text_example')}}</td><td><tt>{{ $item['example'] }}</tt>
    <tr valign=top><td>{{ __('legacy/tags.text_result')}}</td><td>{{ $item['result'] }}
    @if ($item['remarks'] !== '')
        <tr><td>{{ __('legacy/tags.text_remarks')}}</td><td>{{ $item['remarks'] }}
    @endif
    </table>
@endforeach
{{ \App\Support\Frame::close() }}
@endsection

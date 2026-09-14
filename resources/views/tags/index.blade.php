@extends('layouts.legacy')

@section('title', $lang_tags['head_tags'] ?? 'Tags')

@section('content')
@safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml(\App\Support\Frame::open((string) ($lang_tags['text_tags'] ?? ''), false, 10, '100%', 'left')))
<p>@safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml(sprintf((string) ($lang_tags['text_bb_tags_note'] ?? '%s'), $siteName)))</p>

<form method=post action=?>
<textarea name=test cols=60 rows=3>{{ $test ?? '' }}</textarea>
<input type=submit style='height: 23px; margin-left: 5px' value="{{ $lang_tags['submit_test_this_code'] ?? '' }}">
</form>

@if (($test ?? '') !== '')
    <p><hr>@safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml(\App\Support\Format::formatComment($test)))</hr></p>
@endif

@foreach ($tagItems ?? [] as $item)
    <p class=sub><b>@safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml($item['name']))</b></p>
    <table class=main width=100% border=1 cellspacing=0 cellpadding=5>
    <tr valign=top><td width=25%>{{ $lang_tags['text_description'] ?? '' }}</td><td>@safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml($item['description']))
    <tr valign=top><td>{{ $lang_tags['text_syntax'] ?? '' }}</td><td><tt>@safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml($item['syntax']))</tt>
    <tr valign=top><td>{{ $lang_tags['text_example'] ?? '' }}</td><td><tt>@safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml($item['example']))</tt>
    <tr valign=top><td>{{ $lang_tags['text_result'] ?? '' }}</td><td>@safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml($item['result']))
    @if ($item['remarks'] !== '')
        <tr><td>{{ $lang_tags['text_remarks'] ?? '' }}</td><td>@safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml($item['remarks']))
    @endif
    </table>
@endforeach
@safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml(\App\Support\Frame::CLOSE))
@endsection

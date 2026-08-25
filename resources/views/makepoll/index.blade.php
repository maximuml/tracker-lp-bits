@php
$lang_makepoll = (array) (\App\Support\SupportContext::getGlobal('lang_makepoll') ?? []);
$poll = (array) ($poll ?? []);
$pollid = (int) ($poll['id'] ?? 0);
$ageWarning = (string) ($ageWarning ?? '');
$returnto = (string) ($returnto ?? '');
$title = $title ?? ($pollid > 0
    ? ($lang_makepoll['head_edit_poll'] ?? 'Edit poll')
    : ($lang_makepoll['head_new_poll'] ?? 'New poll'));
@endphp
@extends('layouts.legacy')

@section('title', $title)

@section('content')
@if ($pollid > 0)
    <h1>{{ $lang_makepoll['text_edit_poll'] ?? 'Edit poll' }}</h1>
@else
    @if ($ageWarning !== '')
        <p><font class=striking><b>{{ $ageWarning }}</b></font></p>
    @endif
    <h1>{{ $lang_makepoll['text_make_poll'] ?? 'Make poll' }}</h1>
@endif

<form method="post" action="makepoll.php">
@csrf
<style type="text/css">
input.mp { width: 450px; }
</style>
<table border=1 cellspacing=0 cellpadding=5>
<tr><td class=rowhead>{{ $lang_makepoll['text_question'] ?? 'Question' }} <font color=red>*</font></td><td align=left><input name=question class=mp maxlength=255 value="{{ htmlspecialchars((string) ($poll['question'] ?? '')) }}"></td></tr>
@for ($i = 0; $i <= 19; $i++)
<tr><td class=rowhead>{{ ($lang_makepoll['text_option'] ?? 'Option').($i + 1) }}@if ($i < 2) <font color=red>*</font>@endif</td><td align=left><input name=option{{ $i }} class=mp maxlength=40 value="{{ htmlspecialchars((string) ($poll["option{$i}"] ?? '')) }}"><br /></td></tr>
@endfor
<tr><td colspan=2 align=center><input type=submit value="{{ $pollid ? ($lang_makepoll['submit_edit_poll'] ?? 'Edit poll') : ($lang_makepoll['submit_create_poll'] ?? 'Create poll') }}" style='height: 20pt'></td></tr>
</table>
<p><font color=red>*</font>{{ $lang_makepoll['text_required'] ?? 'Required' }}</p>
@if ($pollid > 0)
<input type=hidden name=pollid value="{{ $pollid }}">
@endif
<input type=hidden name=returnto value="{{ htmlspecialchars($returnto) }}">
</form>
@endsection

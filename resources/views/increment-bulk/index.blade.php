@extends('layouts.legacy')

@section('title', $lang_incrementbulk['page_title'] ?? 'Increment Bulk')

@section('content')
    <table class=main width=737 border=0 cellspacing=0 cellpadding=0><tr><td class=embedded>
                <div align=center>
                    <h1>{{ $lang_incrementbulk['page_title'] }}</a></h1>
                    <form method=post action=take-increment-bulk.php>
                        @if ($returnto || $httpReferer)
                            <input type=hidden name=returnto value="{{ $returnto }}">
                        @endif
                        <table cellspacing=0 cellpadding=5>
                            @if ($sent !== null && $sent == 1)
                            <tr><td colspan=2 class="text" align="center"><font color=red><b> {{ $validTypeMap[$type] ?? '' }}{{ $lang_incrementbulk['sent_success'] }}</font></b></tr></td>
                            @endif
                            <tr>
                                <td class="rowhead" valign="top">{{ $lang_incrementbulk['labels']['type'] }}</td>
                                <td class="rowfollow">
                                    @foreach ($validTypeMap as $name => $text)
                                    <label><input type="radio" name="type" value="{{ $name }}">{{ $text }}@if ($name == 'uploaded')&nbsp;(GB)@endif</label>
                                    @endforeach
                                </td>
                            </tr>
                            <tr><td class="rowhead" valign="top">{{ $lang_incrementbulk['labels']['amount'] }} </td><td class="rowfollow"><input type=text name=amount size=10></td></tr>
                            <tr><td class="rowhead" valign="top">{{ $lang_incrementbulk['labels']['duration'] }}</td><td class="rowfollow"><input type=number min="1" name=duration size=10> {{ $lang_incrementbulk['labels']['duration_help'] }}</td></tr>
                            <tr>
                                <td class="rowhead" valign="top">{{ $lang_incrementbulk['labels']['user_class'] }}</td><td class="rowfollow">
                                    <table style="border: 0" width="100%" cellpadding="0" cellspacing="0">
                                        @foreach ($classes as $chunk)
                                        <tr>
                                            @foreach ($chunk as $class => $info)
                                            <td style="border: 0"><label><input type="checkbox" name="classes[]" value="{{ $class }}" />{{ $info }}</label></td>
                                            @endforeach
                                        </tr>
                                        @endforeach
                                    </table>
                                </td>
                            </tr>
                            <tr><td class="rowhead" valign="top">{{ $lang_incrementbulk['labels']['msg_subject'] }} </td><td class="rowfollow"><input type=text name=subject size=82></td></tr>
                            <tr><td class="rowhead" valign="top">{{ $lang_incrementbulk['labels']['msg_body'] }} </td><td class="rowfollow"><textarea name=msg cols=80 rows=5>{{ $body }}</textarea></td></tr>
                            <tr>
                                <td class="rowfollow" colspan=2><div align="center"><b>{{ $lang_incrementbulk['labels']['operator'] }}:&nbsp;&nbsp;</b>
                                        <label><input name="sender" type="radio" value="self" checked>{{ $CURUSER['username'] ?? '' }}</label>
                                        &nbsp; <label><input name="sender" type="radio" value="system">System</label>
                                    </div></td></tr>
                            <tr><td class="rowfollow" colspan=2 align=center><input type=submit value="{{ $submitLabel }}" class=btn></td></tr>
                        </table>
                        <input type=hidden name=receiver value={{ $receiver }}>
                    </form>

                </div></td></tr></table>
@endsection

@extends('layouts.legacy_torrents')

@section('title', 'Bans')

@section('content')
@php
$__server_REQUEST_METHOD = \App\Support\SupportContext::getServerValue('REQUEST_METHOD');
$__server_REQUEST_URI = \App\Support\SupportContext::getServerValue('REQUEST_URI');
if (\App\Support\UserDisplay::currentClass() < UC_ADMINISTRATOR) {
    \App\Support\LegacyResponse::abort('Sorry', 'Access denied.');
}

$remove = intval(\App\Support\SupportContext::getQuery('remove') ?? 0);
if (\App\Support\Validators::isId($remove)) {
    \Nexus\Database\NexusDB::table('bans')->where('id', $remove)->delete();
    \App\Support\Log::writeWithContext("Ban ".htmlspecialchars($remove)." was removed by {$CURUSER['id']} ($CURUSER[username])", 'mod');
}

if ($__server_REQUEST_METHOD == "POST" && \App\Support\UserDisplay::currentClass() >= UC_ADMINISTRATOR) {
    $first = trim(\App\Support\SupportContext::getPost("first"));
    $last = trim(\App\Support\SupportContext::getPost("last"));
    $comment = trim(\App\Support\SupportContext::getPost("comment"));
    if (! $first || ! $last || ! $comment) {
        \App\Support\LegacyResponse::abort('Error', 'Missing form data.');
    }
    $firstlong = ip2long($first);
    $lastlong = ip2long($last);
    if ($firstlong == -1 || $lastlong == -1) {
        \App\Support\LegacyResponse::abort('Error', 'Bad IP address.');
    }
    \Nexus\Database\NexusDB::table('bans')->insert([
        'added' => date("Y-m-d H:i:s"),
        'addedby' => $CURUSER['id'],
        'first' => $firstlong,
        'last' => $lastlong,
        'comment' => $comment,
    ]);
    header("Location: {$__server_REQUEST_URI}");
    return;
}

$bans = \Nexus\Database\NexusDB::table('bans')->orderByDesc('added')->get();
@endphp

<h1>Current Bans</h1>

@if ($bans->isEmpty())
    <p align="center"><b>Nothing found</b></p>
@else
    <table border="1" cellspacing="0" cellpadding="5">
        <tr>
            <td class="colhead">Added</td>
            <td class="colhead" align="left">First IP</td>
            <td class="colhead" align="left">Last IP</td>
            <td class="colhead" align="left">By</td>
            <td class="colhead" align="left">Comment</td>
            <td class="colhead">Remove</td>
        </tr>
        @foreach ($bans as $ban)
            @php $arr = (array) $ban; @endphp
            <tr>
                <td>{{ \App\Support\Time::format($arr['added']) }}</td>
                <td align="left">{{ long2ip($arr['first']) }}</td>
                <td align="left">{{ long2ip($arr['last']) }}</td>
                <td align="left">{!! \App\Support\UserDisplay::username($arr['addedby']) !!}</td>
                <td align="left">{{ $arr['comment'] }}</td>
                <td><a href="bans.php?remove={{ $arr['id'] }}">Remove</a></td>
            </tr>
        @endforeach
    </table>
@endif

@if (\App\Support\UserDisplay::currentClass() >= UC_ADMINISTRATOR)
    <h1>Add ban</h1>
    <table border="1" cellspacing="0" cellpadding="5">
        <form method="post" action="bans.php">
            <tr><td class="rowhead">First IP</td><td><input type="text" name="first" size="40"></td></tr>
            <tr><td class="rowhead">Last IP</td><td><input type="text" name="last" size="40"></td></tr>
            <tr><td class="rowhead">Comment</td><td><input type="text" name="comment" size="40"></td></tr>
            <tr><td colspan="2" align="center"><input type="submit" value="Okay" class="btn"></td></tr>
        </form>
    </table>
@endif
@endsection

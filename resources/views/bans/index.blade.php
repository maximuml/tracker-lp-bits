@extends('layouts.legacy_torrents')

@section('title', 'Bans')

@section('content')
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
                <td>{!! \App\Support\Time::format($arr['added']) !!}</td>
                <td align="left">{{ long2ip($arr['first']) }}</td>
                <td align="left">{{ long2ip($arr['last']) }}</td>
                <td align="left">{!! \App\Support\UserDisplay::username($arr['addedby']) !!}</td>
                <td align="left">{{ $arr['comment'] }}</td>
                <td><a href="bans.php?remove={{ $arr['id'] }}">Remove</a></td>
            </tr>
        @endforeach
    </table>
@endif

@if ($canAdd)
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

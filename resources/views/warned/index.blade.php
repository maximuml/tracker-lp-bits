@extends('layouts.legacy_torrents')

@section('title', 'Warned Users')

@section('content')
@php
$count = (int) ($count ?? 0);
$warned = $warnedCount ?? number_format($count);
$rows = (array) ($rows ?? []);
\App\Support\Html::beginFrame("Warned Users: ({$warned})", true);
\App\Support\Html::beginTable();
@endphp

<table border="1" width="675" cellspacing="0" cellpadding="2">
    <form action="nowarn.php" method="post">
        <tr align="center">
            <td class="colhead" width="90">User Name</td>
            <td class="colhead" width="70">Registered</td>
            <td class="colhead" width="75">Last access</td>
            <td class="colhead" width="75">User Class</td>
            <td class="colhead" width="70">Downloaded</td>
            <td class="colhead" width="70">UpLoaded</td>
            <td class="colhead" width="45">Ratio</td>
            <td class="colhead" width="125">End<br>Of Warning</td>
            <td class="colhead" width="65">Remove<br>Warning</td>
            <td class="colhead" width="65">Disable<br>Account</td>
        </tr>
        @foreach ($rows as $arr)
            @php
            if ($arr['added'] == '0000-00-00 00:00:00' || $arr['added'] == null) {
                $arr['added'] = '-';
            }
            if ($arr['last_access'] == '0000-00-00 00:00:00' || $arr['added'] == null) {
                $arr['last_access'] = '-';
            }
            if ($arr["downloaded"] != 0) {
                $ratio = number_format($arr["uploaded"] / $arr["downloaded"], 3);
            } else {
                $ratio = "---";
            }
            $ratio = "<font color=" . \App\Support\Ratio::color($ratio) . ">$ratio</font>";
            $uploaded = \App\Support\Format::size($arr["uploaded"]);
            $downloaded = \App\Support\Format::size($arr["downloaded"]);
            $added = substr($arr['added'],0,10);
            $last_access = substr($arr['last_access'],0,10);
            $class = \App\Support\UserClass::name($arr["class"], false, true, true);
            @endphp
            <tr>
                <td align="left">{!! \App\Support\UserDisplay::username($arr['id']) !!}</td>
                <td align="center">{{ $added }}</td>
                <td align="center">{{ $last_access }}</td>
                <td align="center">{!! $class !!}</td>
                <td align="center">{!! $downloaded !!}</td>
                <td align="center">{!! $uploaded !!}</td>
                <td align="center">{!! $ratio !!}</td>
                <td align="center">{{ $arr['warneduntil'] }}</td>
                <td bgcolor="#008000" align="center"><input type="checkbox" name="usernw[]" value="{{ $arr['id'] }}"></td>
                <td bgcolor="#FF000" align="center"><input type="checkbox" name="desact[]" value="{{ $arr['id'] }}"></td>
            </tr>
        @endforeach
        @if (\App\Support\UserDisplay::currentClass() >= UC_ADMINISTRATOR)
            <tr><td colspan="10" align="right"><input type="submit" name="submit" value="Apply Changes"></td></tr>
            <input type="hidden" name="nowarned" value="nowarned">
        @endif
    </form>
</table>
<p>{!! $pagemenu ?? '' !!}<br>{!! $browsemenu ?? '' !!}</p>
@endsection

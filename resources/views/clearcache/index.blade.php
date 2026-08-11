@extends('layouts.legacy_torrents')

@section('title', 'Clear cache')

@section('content')
@php
$__server_REQUEST_METHOD = \App\Support\SupportContext::getServerValue('REQUEST_METHOD');
if (\App\Support\UserDisplay::currentClass() < UC_MODERATOR) {
    \App\Support\LegacyResponse::abort('Error', 'Permission denied.');
}
$done = false;
if ($__server_REQUEST_METHOD == 'POST') {
    $cachename = \App\Support\SupportContext::getPost('cachename');
    if ($cachename == '') {
        \App\Support\LegacyResponse::abort('Error', 'You must fill in cache name.');
    }
    if (\App\Support\SupportContext::getPost('multilang') == 'yes') {
        $Cache->delete_value($cachename, true);
    } else {
        $Cache->delete_value($cachename);
    }
    $done = true;
}
@endphp

<h1>Clear cache</h1>
@if ($done)
    <p align="center"><font class="striking">Cache cleared</font></p>
@endif

<form method="post" action="clearcache.php">
<table border="1" cellspacing="0" cellpadding="5">
    <tr><td class="rowhead">Cache name</td><td><input type="text" name="cachename" size="40"></td></tr>
    <tr><td class="rowhead">Multi languages</td><td><input type="checkbox" name="multilang">Yes</td></tr>
    <tr><td colspan="2" align="center"><input type="submit" value="Okay" class="btn"></td></tr>
</table>
</form>
@endsection

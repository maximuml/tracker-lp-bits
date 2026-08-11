@extends('layouts.legacy_torrents')

@section('title', 'Update Users Donated Amounts')

@section('content')
@php
$__server_REQUEST_METHOD = \App\Support\SupportContext::getServerValue('REQUEST_METHOD');
if (\App\Support\UserDisplay::currentClass() < UC_SYSOP) {
    \App\Support\LegacyResponse::abort('Sorry', 'Permission denied.');
}

if ($__server_REQUEST_METHOD == 'POST') {
    if (\App\Support\SupportContext::getPost('username') == '' || \App\Support\SupportContext::getPost('donated') == '') {
        \App\Support\LegacyResponse::abort('Error', 'Missing form data.');
    }
    $username = trim(\App\Support\SupportContext::getPost('username'));
    $donated = trim(\App\Support\SupportContext::getPost('donated'));
    $user = \App\Models\User::query()->where('username', $username)->first(['id']);
    if (! $user) {
        \App\Support\LegacyResponse::abort('Error', 'Unable to update account.');
    }
    \App\Models\User::query()->where('id', $user->id)->update(['donated' => $donated]);
    \App\Support\LegacyResponse::redirect('userdetails.php?id=' . $user->id);
}
@endphp

<h1>Update Users Donated Amounts</h1>
<form method="post" action="donated.php">
<table border="1" cellspacing="0" cellpadding="5">
    <tr><td class="rowhead">User name</td><td><input type="text" name="username" size="40"></td></tr>
    <tr><td class="rowhead">Donated</td><td><input type="text" name="donated" size="5"></td></tr>
    <tr><td colspan="2" align="center"><input type="submit" value="Okay" class="btn"></td></tr>
</table>
</form>
@endsection

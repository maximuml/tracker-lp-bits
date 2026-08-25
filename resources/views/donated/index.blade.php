@php
$title = 'Update Users Donated Amounts';
$error = (string) ($error ?? '');
@endphp
@extends('layouts.legacy')

@section('title', $title)

@section('content')
<h1>Update Users Donated Amounts</h1>
@if ($error !== '')
    <p align="center"><font class="striking">{{ htmlspecialchars($error) }}</font></p>
@endif
<form method="post" action="donated.php">
@csrf
<table border="1" cellspacing="0" cellpadding="5">
    <tr><td class="rowhead">User name</td><td><input type="text" name="username" size="40"></td></tr>
    <tr><td class="rowhead">Donated</td><td><input type="text" name="donated" size="5"></td></tr>
    <tr><td colspan="2" align="center"><input type="submit" value="Okay" class="btn"></td></tr>
</table>
</form>
@endsection

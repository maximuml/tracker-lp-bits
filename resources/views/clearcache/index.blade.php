@php
$title = 'Clear cache';
$done = (bool) ($done ?? false);
$error = (string) ($error ?? '');
@endphp
@extends('layouts.legacy')

@section('title', $title)

@section('content')
<h1>Clear cache</h1>
@if ($done)
    <p align="center"><font class="striking">Cache cleared</font></p>
@endif
@if ($error !== '')
    <p align="center"><font class="striking">{{ htmlspecialchars($error) }}</font></p>
@endif

<form method="post" action="clearcache.php">
@csrf
<table border="1" cellspacing="0" cellpadding="5">
    <tr><td class="rowhead">Cache name</td><td><input type="text" name="cachename" size="40"></td></tr>
    <tr><td class="rowhead">Multi languages</td><td><input type="checkbox" name="multilang" value="yes">Yes</td></tr>
    <tr><td colspan="2" align="center"><input type="submit" value="Okay" class="btn"></td></tr>
</table>
</form>
@endsection

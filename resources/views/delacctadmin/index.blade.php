@extends('layouts.legacy_torrents')

@section('title', 'Delete account')

@section('content')
<h1>Delete account</h1>
<table border="1" cellspacing="0" cellpadding="5">
    <form method="post" action="delacctadmin.php">
        @csrf
        <tr><td class="rowhead">User name</td><td><input size="40" name="userid"></td></tr>
        <tr><td colspan="2"><input type="submit" class="btn" value="Delete"></td></tr>
    </form>
</table>
@endsection

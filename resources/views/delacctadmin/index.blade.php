@extends('layouts.legacy_torrents')

@section('title', 'Delete account')

@section('content')
<h1>Delete account</h1>
<form method="post" action="delacctadmin.php">
        @csrf
        <div class="nx-fgrid">
        <div class="nx-fhead">User name</div><div class="nx-fcell"><input size="40" name="userid"></div>
        <div class="nx-ffull"><input type="submit" class="btn" value="Delete"></div>
    </div>
</form>
@endsection

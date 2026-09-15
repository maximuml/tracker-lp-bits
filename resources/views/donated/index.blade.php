@extends('layouts.legacy')

@section('title', 'Update Users Donated Amounts')

@section('content')
<h1>Update Users Donated Amounts</h1>
@if (($error ?? '') !== '')
    <p align="center"><font class="striking">{{ $error }}</font></p>
@endif
<form method="post" action="donated.php">
@csrf
<div class="nx-fgrid">
    <div class="nx-fhead">User name</div><div class="nx-fcell"><input type="text" name="username" size="40"></div>
    <div class="nx-fhead">Donated</div><div class="nx-fcell"><input type="text" name="donated" size="5"></div>
    <div class="nx-ffull nx-center"><input type="submit" value="Okay" class="btn"></div>
</div>
</form>
@endsection

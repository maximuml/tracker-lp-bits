@extends('layouts.app', ['chromeVariant' => 'legacy'])

@section('title', 'Update Users Donated Amounts')

@section('content')
<h1>Update Users Donated Amounts</h1>
@if (($error ?? '') !== '')
    <p class="text-center"><span class="striking">{{ $error }}</span></p>
@endif
<form method="post" action="/donated">
@csrf
<div class="nx-fgrid">
    <div class="nx-fhead">User name</div><div class="nx-fcell"><input type="text" name="username" size="40"></div>
    <div class="nx-fhead">Donated</div><div class="nx-fcell"><input type="text" name="donated" size="5"></div>
    <div class="nx-ffull text-center"><input type="submit" value="Okay" class="btn"></div>
</div>
</form>
@endsection

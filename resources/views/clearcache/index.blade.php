@extends('layouts.app')

@section('title', 'Clear cache')

@section('content')
<h1>Clear cache</h1>
@if ($done ?? false)
    <p class="text-center"><span class="striking">Cache cleared</span></p>
@endif
@if (($error ?? '') !== '')
    <p class="text-center"><span class="striking">{{ $error }}</span></p>
@endif

<form method="post" action="/web/system/clear-cache">
@csrf
<div class="nx-fgrid">
    <label class="nx-fhead" for="cachename">Cache name</label><div class="nx-fcell"><input type="text" id="cachename" name="cachename" size="40"></div>
    <div class="nx-fhead">Multi languages</div><div class="nx-fcell"><label><input type="checkbox" name="multilang" value="yes">Yes</label></div>
    <div class="nx-ffull text-center"><input type="submit" value="Okay" class="nx-btn nx-btn--primary"></div>
</div>
</form>
@endsection

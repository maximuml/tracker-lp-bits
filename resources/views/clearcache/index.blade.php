@extends('layouts.app', ['chromeVariant' => 'legacy'])

@section('title', 'Clear cache')

@section('content')
<h1>Clear cache</h1>
@if ($done ?? false)
    <p class="text-center"><span class="striking">Cache cleared</span></p>
@endif
@if (($error ?? '') !== '')
    <p class="text-center"><span class="striking">{{ $error }}</span></p>
@endif

<form method="post" action="/clearcache">
@csrf
<div class="nx-fgrid">
    <div class="nx-fhead">Cache name</div><div class="nx-fcell"><input type="text" name="cachename" size="40"></div>
    <div class="nx-fhead">Multi languages</div><div class="nx-fcell"><input type="checkbox" name="multilang" value="yes">Yes</div>
    <div class="nx-ffull text-center"><input type="submit" value="Okay" class="btn"></div>
</div>
</form>
@endsection

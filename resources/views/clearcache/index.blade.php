@extends('layouts.app')

@section('title', __('clearcache.page_title'))

@section('content')
<x-page :title="'Clear cache'" width="form">
@if ($done ?? false)
    <p class="text-center"><span class="striking">{{ __('clearcache.cleared') }}</span></p>
@endif
@if (($error ?? '') !== '')
    <p class="text-center"><span class="striking">{{ $error }}</span></p>
@endif

<form method="post" action="/web/system/clear-cache">
@csrf
<div class="nx-fgrid">
    <label class="nx-fhead" for="cachename">{{ __('clearcache.cache_name') }}</label><div class="nx-fcell"><input type="text" id="cachename" name="cachename" size="40" class="w-full"></div>
    <div class="nx-fhead">{{ __('clearcache.multi_languages') }}</div><div class="nx-fcell"><label><input type="checkbox" name="multilang" value="yes">{{ __('functions.text_yes') }}</label></div>
    <div class="nx-ffull text-center"><input type="submit" value="{{ __('comment.submit_okay') }}" class="nx-btn nx-btn--primary"></div>
</div>
</form>
</x-page>
@endsection

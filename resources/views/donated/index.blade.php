@extends('layouts.app')

@section('title', __('donate.head_update_amounts'))

@section('content')
<x-page :title="__('donate.head_update_amounts')" width="form">
@if (($vm->error ?? '') !== '')
    <p class="text-center"><span class="striking">{{ $vm->error }}</span></p>
@endif
<form method="post" action="/web/info/donated">
@csrf
<div class="nx-fgrid">
    <label class="nx-fhead" for="donated-username">{{ __('donate.text_username') }}</label><div class="nx-fcell"><input id="donated-username" type="text" name="username" size="40" class="w-full"></div>
    <label class="nx-fhead" for="donated-amount">{{ __('donate.text_donated_amount') }}</label><div class="nx-fcell"><input id="donated-amount" type="text" name="donated" size="5" class="w-full"></div>
    <div class="nx-ffull text-center"><input type="submit" value="{{ __('donate.submit_okay') }}" class="nx-btn nx-btn--primary" data-submit-once></div>
</div>
</form>
</x-page>
@endsection

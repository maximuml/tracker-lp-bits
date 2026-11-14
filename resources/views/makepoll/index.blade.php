@extends('layouts.app')

@section('title', $vm->title ?? (__('makepoll.head_new_poll')))

@section('content')
<x-page :title="($vm->pollid ?? 0) > 0 ? __('makepoll.text_edit_poll') : __('makepoll.text_make_poll')" width="form">
@if (($vm->pollAge['question'] ?? '') !== '')
    <p><span class="striking"><b>{{ __('makepoll.text_current_poll') }}(<i>{{ $vm->pollAge['question'] }}</i>){{ __('makepoll.text_is_only') }}{{ $vm->pollAge['age'] }}{{ __('makepoll.text_old') }}</b></span></p>
@endif

<form method="post" action="/web/polls/create">@csrf
<div class="nx-fgrid">
<label class="nx-fhead" for="poll-question">{{ __('makepoll.text_question')}} <span class="text-nxm-danger">*</span></label><div class="nx-fcell"><input id="poll-question" name=question class=mp maxlength=255 value="{{ (string) ($vm->poll['question'] ?? '') }}"></div>
@for ($i = 0; $i <= 19; $i++)
<label class="nx-fhead" for="poll-option{{ $i }}">{{ (__('makepoll.text_option')).($i + 1) }}@if ($i < 2) <span class="text-nxm-danger">*</span>@endif</label><div class="nx-fcell"><input id="poll-option{{ $i }}" name=option{{ $i }} class=mp maxlength=40 value="{{ (string) ($vm->poll["option{$i}"] ?? '') }}"><br /></div>
@endfor
<div class="nx-ffull text-center"><input type="submit" class="nx-btn nx-btn--primary" value="{{ $vm->pollid ? (__('makepoll.submit_edit_poll')) : (__('makepoll.submit_create_poll')) }}" data-submit-once></div>
</div>
<p><span class="text-nxm-danger">*</span>{{ __('makepoll.text_required')}}</p>
@if ($vm->pollid > 0)
<input type=hidden name=pollid value="{{ $vm->pollid }}">
@endif
<input type=hidden name=returnto value="{{ $vm->returnto }}">
</form>
</x-page>
@endsection

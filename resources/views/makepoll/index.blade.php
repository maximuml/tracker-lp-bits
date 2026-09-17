@extends('layouts.legacy')

@section('title', $title ?? (__('legacy/makepoll.head_new_poll')))

@section('content')
@if (($pollid ?? 0) > 0)
    <h1>{{ __('legacy/makepoll.text_edit_poll')}}</h1>
@else
    @if (($ageWarning ?? '') !== '')
        <p><font class=striking><b>{{ $ageWarning }}</b></font></p>
    @endif
    <h1>{{ __('legacy/makepoll.text_make_poll')}}</h1>
@endif

<form method="post" action="makepoll.php">
@csrf
<style type="text/css" nonce="{{ $cspNonce ?? '' }}">
input.mp { width: 450px; }
</style>
<div class="nx-fgrid">
<div class="nx-fhead">{{ __('legacy/makepoll.text_question')}} <font color=red>*</font></div><div class="nx-fcell"><input name=question class=mp maxlength=255 value="{{ (string) ($poll['question'] ?? '') }}"></div>
@for ($i = 0; $i <= 19; $i++)
<div class="nx-fhead">{{ (__('legacy/makepoll.text_option')).($i + 1) }}@if ($i < 2) <font color=red>*</font>@endif</div><div class="nx-fcell"><input name=option{{ $i }} class=mp maxlength=40 value="{{ (string) ($poll["option{$i}"] ?? '') }}"><br /></div>
@endfor
<div class="nx-ffull nx-center"><input type=submit value="{{ $pollid ? (__('legacy/makepoll.submit_edit_poll')) : (__('legacy/makepoll.submit_create_poll')) }}" style='height: 20pt'></div>
</div>
<p><font color=red>*</font>{{ __('legacy/makepoll.text_required')}}</p>
@if ($pollid > 0)
<input type=hidden name=pollid value="{{ $pollid }}">
@endif
<input type=hidden name=returnto value="{{ $returnto }}">
</form>
@endsection

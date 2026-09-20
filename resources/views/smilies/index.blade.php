@extends('layouts.legacy')

@section('title', (''))

@section('content')
<x-frame :caption="__('legacy/functions.text_smilies')">
<table class="main" border="1" cellspacing="0" cellpadding="5" data-nx="data"><tr><td class="colhead">{{ __('legacy/functions.col_type_something') }}</td><td class="colhead">{{ __('legacy/functions.col_to_make_a') }}</td></tr>
@for ($i = 1; $i < 192; $i++)
<tr><td>[em{{ $i }}]</td><td><img src="pic/smilies/{{ $i }}.gif" alt="[em{{ $i }}]" /></td></tr>
@endfor
</table>
</x-frame>
@endsection

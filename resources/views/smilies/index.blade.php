@extends('layouts.app', ['chromeVariant' => 'legacy'])

@section('title', (''))

@section('content')
<x-frame :caption="__('legacy/functions.text_smilies')">
<table class="main" data-nx="data"><caption class="nx-sr-only">{{ __('legacy/functions.text_smilies') }}</caption><tr><th class="bg-nxm-surface-alt font-semibold" scope="col">{{ __('legacy/functions.col_type_something') }}</th><th class="bg-nxm-surface-alt font-semibold" scope="col">{{ __('legacy/functions.col_to_make_a') }}</th></tr>
@for ($i = 1; $i < 192; $i++)
<tr><td>[em{{ $i }}]</td><td><img src="pic/smilies/{{ $i }}.gif" alt="[em{{ $i }}]" /></td></tr>
@endfor
</table>
</x-frame>
@endsection

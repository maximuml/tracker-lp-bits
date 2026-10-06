@extends('layouts.app', ['chromeVariant' => 'legacy'])

@section('title', (''))

@section('content')
<x-frame :caption="__('legacy/functions.text_smilies')">
<x-data-table :caption="__('legacy/functions.text_smilies')" captionHidden class="nx-main"><x-slot:head><thead><tr><th class="bg-nxm-surface-alt font-semibold" scope="col">{{ __('legacy/functions.col_type_something') }}</th><th class="bg-nxm-surface-alt font-semibold" scope="col">{{ __('legacy/functions.col_to_make_a') }}</th></tr></thead></x-slot:head>
@for ($i = 1; $i < 192; $i++)
<tr><td>[em{{ $i }}]</td><td><img src="pic/smilies/{{ $i }}.gif" alt="[em{{ $i }}]" /></td></tr>
@endfor
</x-data-table>
</x-frame>
@endsection

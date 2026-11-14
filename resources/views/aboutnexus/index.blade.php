@extends('layouts.app')

@section('title', PROJECTNAME)

@section('content')
<x-page :title="PROJECTNAME" width="list">
<x-frame :center="false">
<x-slot:caption><span id="version">{{ $vm->captions['version'] }}</span></x-slot>
{{ $vm->notes['version'] }}
<x-data-table :caption="$vm->captions['version']" captionHidden class="nx-main">
    <x-settings-row :label="__('aboutnexus.text_main_version')">{{ PROJECTNAME }}</x-settings-row>
    <x-settings-row :label="__('aboutnexus.text_sub_version')">{{ VERSION_NUMBER }}</x-settings-row>
    <x-settings-row :label="__('aboutnexus.text_release_date')">{{ RELEASE_DATE }}</x-settings-row>
</x-data-table>
<br /><br />
</x-frame>

<x-frame :center="false">
<x-slot:caption><span id="nexus">{{ $vm->captions['nexus'] }}</span></x-slot>
{{ $vm->notes['nexus'] }}
<br /><br />
</x-frame>

<x-frame :center="false">
<x-slot:caption><span id="authorization">{{ $vm->captions['authorization'] }}</span></x-slot>
{{ $vm->notes['authorization'] }}
<br /><br />
</x-frame>

<x-frame :center="false">
<x-slot:caption><span id="translation">{{ $vm->captions['translation'] }}</span></x-slot>
{{ $vm->notes['translation'] }}
<br /><br />
<x-data-table :caption="$vm->captions['translation']" captionHidden class="nx-main"><x-slot:head><thead><tr>
        <th class="bg-nxm-surface-alt font-semibold" scope="col">{{ __('aboutnexus.text_flag')}}</th>
        <th class="bg-nxm-surface-alt font-semibold" scope="col">{{ __('aboutnexus.text_language')}}</th>
        <th class="bg-nxm-surface-alt font-semibold" scope="col">{{ __('aboutnexus.text_state')}}</th>
    </tr></thead></x-slot:head>
    @foreach ($vm->languages as $row)
        <tr>
            <td class="align-top px-2.5 py-1.5"><img width="20" height="15" src="{{ \App\Support\FlagIcon::path($row['flagpic'] ?? '') }}" alt="{{ $row['lang_name'] }}" title="{{ $row['lang_name'] }}" loading="lazy" decoding="async" /></td>
            <td class="align-top px-2.5 py-1.5">{{ $row['lang_name'] }}</td>
            <td class="align-top px-2.5 py-1.5">{{ $row['trans_state'] }}</td>
        </tr>
    @endforeach
</x-data-table>
<br /><br />
</x-frame>

<x-frame :center="false">
<x-slot:caption><span id="stylesheet">{{ $vm->captions['stylesheet'] }}</span></x-slot>
{{ $vm->notes['stylesheet'] }}
<br /><br />
<x-data-table :caption="$vm->captions['stylesheet']" captionHidden class="nx-main"><x-slot:head><thead><tr>
        <th class="bg-nxm-surface-alt font-semibold" scope="col">{{ __('aboutnexus.text_name')}}</th>
        <th class="bg-nxm-surface-alt font-semibold" scope="col">{{ __('aboutnexus.text_designer')}}</th>
        <th class="bg-nxm-surface-alt font-semibold" scope="col">{{ __('aboutnexus.text_comment')}}</th>
    </tr></thead></x-slot:head>
    @foreach ($vm->stylesheets as $row)
        <tr>
            <td class="align-top px-2.5 py-1.5">{{ $row['name'] }}</td>
            <td class="align-top px-2.5 py-1.5">{{ $row['designer'] }}</td>
            <td class="align-top px-2.5 py-1.5">{{ $row['comment'] }}</td>
        </tr>
    @endforeach
</x-data-table>
<br /><br />
</x-frame>

<x-frame :center="false">
<x-slot:caption><span id="contact">{{ $vm->captions['contact'] }}</span></x-slot>
{{ $vm->notes['contact'] }}
<br /><br />
<x-data-table :caption="$vm->captions['contact']" captionHidden class="nx-main">
    <x-settings-row :label="__('aboutnexus.text_web_site')"><a href="{{ NEXUSPHPURL }}" target="_blank" rel="noopener">{{ NEXUSPHPURL }}</a></x-settings-row>
</x-data-table>
<br /><br />
</x-frame>
</x-page>
@endsection

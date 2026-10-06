@extends('layouts.app', ['chromeVariant' => 'legacy'])

@section('title', PROJECTNAME)

@section('content')
<x-frame :center="false">
<x-slot:caption><span id="version">{{ $captions['version'] }}</span></x-slot>
{{ $notes['version'] }}
<x-data-table :caption="$captions['version']" captionHidden class="nx-main">
    <x-settings-row :label="__('legacy/aboutnexus.text_main_version')">{{ PROJECTNAME }}</x-settings-row>
    <x-settings-row :label="__('legacy/aboutnexus.text_sub_version')">{{ VERSION_NUMBER }}</x-settings-row>
    <x-settings-row :label="__('legacy/aboutnexus.text_release_date')">{{ RELEASE_DATE }}</x-settings-row>
</x-data-table>
<br /><br />
</x-frame>

<x-frame :center="false">
<x-slot:caption><span id="nexus">{{ $captions['nexus'] }}</span></x-slot>
{{ $notes['nexus'] }}
<br /><br />
</x-frame>

<x-frame :center="false">
<x-slot:caption><span id="authorization">{{ $captions['authorization'] }}</span></x-slot>
{{ $notes['authorization'] }}
<br /><br />
</x-frame>

<x-frame :center="false">
<x-slot:caption><span id="translation">{{ $captions['translation'] }}</span></x-slot>
{{ $notes['translation'] }}
<br /><br />
<x-data-table :caption="$captions['translation']" captionHidden class="nx-main"><x-slot:head><thead><tr>
        <th class="bg-nxm-surface-alt font-semibold" scope="col">{{ __('legacy/aboutnexus.text_flag')}}</th>
        <th class="bg-nxm-surface-alt font-semibold" scope="col">{{ __('legacy/aboutnexus.text_language')}}</th>
        <th class="bg-nxm-surface-alt font-semibold" scope="col">{{ __('legacy/aboutnexus.text_state')}}</th>
    </tr></thead></x-slot:head>
    @foreach ($languages as $row)
        <tr>
            <td class="align-top px-2.5 py-1.5"><img width="24" height="15" src="pic/flag/{{ $row['flagpic'] }}" alt="{{ $row['lang_name'] }}" title="{{ $row['lang_name'] }}" /></td>
            <td class="align-top px-2.5 py-1.5">{{ $row['lang_name'] }}</td>
            <td class="align-top px-2.5 py-1.5">{{ $row['trans_state'] }}</td>
        </tr>
    @endforeach
</x-data-table>
<br /><br />
</x-frame>

<x-frame :center="false">
<x-slot:caption><span id="stylesheet">{{ $captions['stylesheet'] }}</span></x-slot>
{{ $notes['stylesheet'] }}
<br /><br />
<x-data-table :caption="$captions['stylesheet']" captionHidden class="nx-main"><x-slot:head><thead><tr>
        <th class="bg-nxm-surface-alt font-semibold" scope="col">{{ __('legacy/aboutnexus.text_name')}}</th>
        <th class="bg-nxm-surface-alt font-semibold" scope="col">{{ __('legacy/aboutnexus.text_designer')}}</th>
        <th class="bg-nxm-surface-alt font-semibold" scope="col">{{ __('legacy/aboutnexus.text_comment')}}</th>
    </tr></thead></x-slot:head>
    @foreach ($stylesheets as $row)
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
<x-slot:caption><span id="contact">{{ $captions['contact'] }}</span></x-slot>
{{ $notes['contact'] }}
<br /><br />
<x-data-table :caption="$captions['contact']" captionHidden class="nx-main">
    <x-settings-row :label="__('legacy/aboutnexus.text_web_site')"><a href="{{ NEXUSPHPURL }}" target="_blank">{{ NEXUSPHPURL }}</a></x-settings-row>
</x-data-table>
<br /><br />
</x-frame>
@endsection

@extends('layouts.legacy')

@section('title', PROJECTNAME)

@section('content')
<x-frame :caption="$captions['version']" :center="false">
{{ $notes['version'] }}
<table data-nx="data" class="main" border="1" cellspacing="0" cellpadding="5" align="center">
    <x-settings-row :label="__('legacy/aboutnexus.text_main_version')">{{ PROJECTNAME }}</x-settings-row>
    <x-settings-row :label="__('legacy/aboutnexus.text_sub_version')">{{ VERSION_NUMBER }}</x-settings-row>
    <x-settings-row :label="__('legacy/aboutnexus.text_release_date')">{{ RELEASE_DATE }}</x-settings-row>
</table>
<br /><br />
</x-frame>

<x-frame :caption="$captions['nexus']" :center="false">
{{ $notes['nexus'] }}
<br /><br />
</x-frame>

<x-frame :caption="$captions['authorization']" :center="false">
{{ $notes['authorization'] }}
<br /><br />
</x-frame>

<x-frame :caption="$captions['translation']" :center="false">
{{ $notes['translation'] }}
<br /><br />
<table data-nx="data" class="main" border="1" cellspacing="0" cellpadding="5" align="center">
    <tr>
        <td class="colhead">{{ __('legacy/aboutnexus.text_flag')}}</td>
        <td class="colhead">{{ __('legacy/aboutnexus.text_language')}}</td>
        <td class="colhead">{{ __('legacy/aboutnexus.text_state')}}</td>
    </tr>
    @foreach ($languages as $row)
        <tr>
            <td class="rowfollow"><img width="24" height="15" src="pic/flag/{{ $row['flagpic'] }}" alt="{{ $row['lang_name'] }}" title="{{ $row['lang_name'] }}" /></td>
            <td class="rowfollow">{{ $row['lang_name'] }}</td>
            <td class="rowfollow">{{ $row['trans_state'] }}</td>
        </tr>
    @endforeach
</table>
<br /><br />
</x-frame>

<x-frame :caption="$captions['stylesheet']" :center="false">
{{ $notes['stylesheet'] }}
<br /><br />
<table data-nx="data" class="main" border="1" cellspacing="0" cellpadding="5" align="center">
    <tr>
        <td class="colhead">{{ __('legacy/aboutnexus.text_name')}}</td>
        <td class="colhead">{{ __('legacy/aboutnexus.text_designer')}}</td>
        <td class="colhead">{{ __('legacy/aboutnexus.text_comment')}}</td>
    </tr>
    @foreach ($stylesheets as $row)
        <tr>
            <td class="rowfollow">{{ $row['name'] }}</td>
            <td class="rowfollow">{{ $row['designer'] }}</td>
            <td class="rowfollow">{{ $row['comment'] }}</td>
        </tr>
    @endforeach
</table>
<br /><br />
</x-frame>

<x-frame :caption="$captions['contact']" :center="false">
{{ $notes['contact'] }}
<br /><br />
<table data-nx="data" class="main" border="1" cellspacing="0" cellpadding="5" align="center">
    <x-settings-row :label="__('legacy/aboutnexus.text_web_site')"><a href="{{ NEXUSPHPURL }}" target="_blank">{{ NEXUSPHPURL }}</a></x-settings-row>
</table>
<br /><br />
</x-frame>
@endsection

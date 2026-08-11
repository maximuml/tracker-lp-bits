@extends('layouts.legacy')

@section('title', PROJECTNAME)

@section('content')
@php
\App\Support\Html::beginFrame("<span id=\"version\">".$lang_aboutnexus['text_version']."</span>");
echo sprintf ($lang_aboutnexus['text_version_note'], \App\Models\Setting::getSiteName(), PROJECTNAME);
@endphp
<table class="main" border="1" cellspacing="0" cellpadding="5" align="center">
    @php \App\Support\Html::tr($lang_aboutnexus['text_main_version'], PROJECTNAME, 1); @endphp
    @php \App\Support\Html::tr($lang_aboutnexus['text_sub_version'], VERSION_NUMBER, 1); @endphp
    @php \App\Support\Html::tr($lang_aboutnexus['text_release_date'], RELEASE_DATE, 1); @endphp
</table>
<br /><br />
@php \App\Support\Html::endFrame(); @endphp

@php
$rows = \Nexus\Database\NexusDB::table('language')->orderBy('trans_state')->get();
\App\Support\Html::beginFrame("<span id=\"nexus\">".$lang_aboutnexus['text_nexus'].PROJECTNAME."</span>");
echo sprintf (PROJECTNAME.$lang_aboutnexus['text_nexus_note'], PROJECTNAME);
@endphp
<br /><br />
@php \App\Support\Html::endFrame(); @endphp

@php
\App\Support\Html::beginFrame("<span id=\"authorization\">".$lang_aboutnexus['text_authorization']."</span>");
echo sprintf ($lang_aboutnexus['text_authorization_note'], PROJECTNAME);
@endphp
<br /><br />
@php \App\Support\Html::endFrame(); @endphp

@php
\App\Support\Html::beginFrame("<span id=\"translation\">".$lang_aboutnexus['text_translation']."</span>");
print (PROJECTNAME.$lang_aboutnexus['text_translation_note']);
@endphp
<br /><br />
<table class="main" border="1" cellspacing="0" cellpadding="5" align="center">
    <tr>
        <td class="colhead">{{ $lang_aboutnexus['text_flag'] }}</td>
        <td class="colhead">{{ $lang_aboutnexus['text_language'] }}</td>
        <td class="colhead">{{ $lang_aboutnexus['text_state'] }}</td>
    </tr>
    @foreach (\Nexus\Database\NexusDB::table('language')->orderBy('trans_state')->get() as $row)
        @php $arr = (array) $row; @endphp
        <tr>
            <td class="rowfollow"><img width="24" height="15" src="pic/flag/{{ $arr['flagpic'] }}" alt="{{ $arr['lang_name'] }}" title="{{ $arr['lang_name'] }}" style="padding-bottom:1px;" /></td>
            <td class="rowfollow">{{ $arr['lang_name'] }}</td>
            <td class="rowfollow">{{ $arr['trans_state'] }}</td>
        </tr>
    @endforeach
</table>
<br /><br />
@php \App\Support\Html::endFrame(); @endphp

@php
$rows = \Nexus\Database\NexusDB::table('stylesheets')->orderBy('id')->get();
\App\Support\Html::beginFrame("<span id=\"stylesheet\">".$lang_aboutnexus['text_stylesheet']."</span>");
echo sprintf ($lang_aboutnexus['text_stylesheet_note'], PROJECTNAME, \App\Models\Setting::getSiteName());
@endphp
<br /><br />
<table class="main" border="1" cellspacing="0" cellpadding="5" align="center">
    <tr>
        <td class="colhead">{{ $lang_aboutnexus['text_name'] }}</td>
        <td class="colhead">{{ $lang_aboutnexus['text_designer'] }}</td>
        <td class="colhead">{{ $lang_aboutnexus['text_comment'] }}</td>
    </tr>
    @foreach (\Nexus\Database\NexusDB::table('stylesheets')->orderBy('id')->get() as $row)
        @php $arr = (array) $row; @endphp
        <tr>
            <td class="rowfollow">{{ $arr['name'] }}</td>
            <td class="rowfollow">{{ $arr['designer'] }}</td>
            <td class="rowfollow">{{ $arr['comment'] }}</td>
        </tr>
    @endforeach
</table>
<br /><br />
@php \App\Support\Html::endFrame(); @endphp

@php
\App\Support\Html::beginFrame("<span id=\"contact\">".$lang_aboutnexus['text_contact'].PROJECTNAME."</span>");
print ($lang_aboutnexus['text_contact_note']);
@endphp
<br /><br />
<table class="main" border="1" cellspacing="0" cellpadding="5" align="center">
    @php \App\Support\Html::tr($lang_aboutnexus['text_web_site'], '<a href="' . NEXUSPHPURL . '" target="_blank">' . NEXUSPHPURL . '</a>', 1); @endphp
</table>
<br /><br />
@php \App\Support\Html::endFrame(); @endphp
@endsection

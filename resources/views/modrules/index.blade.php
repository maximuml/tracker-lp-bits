@extends('layouts.app')

@section('title', "Rules Management")

@section('content')
<x-page :title="$mode === 'newsect' ? 'Add Rules' : ($mode === 'edit' ? 'Edit Rules' : 'Rules Management')" width="form">
@if ($mode === 'newsect')
<form method="post" class="nx-inline" action="/web/staff/modrules?act=addsect">
    @csrf
    <div class="nx-fgrid nx-fgrid--auto">
        <label class="nx-fcell" for="rule-title">Title:</label><div class="nx-fcell"><input type="text" id="rule-title" name="title"/></div>
        <label class="nx-fcell" for="rule-text">Rules:</label><div class="nx-fcell"><textarea cols="90" rows="20" id="rule-text" name="text"></textarea></div>
            <label class="nx-fcell" for="rule-language">Language:</label>
            <div class="nx-fcell text-center">
                <select name="language" id="rule-language">
                    @foreach ($langs as $row)
                    <option value="{{ (int) $row['id'] }}"@if (($row['site_lang_folder'] ?? '') == $deflang) selected @endif>{{ $row['lang_name'] }}</option>
                    @endforeach
                </select>
            </div>
        <div class="nx-ffull text-center"><input type="submit" class="nx-btn nx-btn--primary" value="{{ __('fields.text_add') }}"></div>
    </div>
</form>
@elseif ($mode === 'edit')
<form method="post" class="nx-inline" action="/web/staff/modrules?act=edited">
    @csrf
    <div class="nx-fgrid nx-fgrid--auto">
        <label class="nx-fcell" for="rule-title">Title:</label><div class="nx-fcell"><input type="text" id="rule-title" name="title" value="{{ $rule['title'] ?? '' }}" /></div>
        <label class="nx-fcell" for="rule-text">Rules:</label><div class="nx-fcell"><textarea cols="90" rows="20" id="rule-text" name="text">{{ $rule['text'] ?? '' }}</textarea></div>
            <label class="nx-fcell" for="rule-language">Language:</label>
            <div class="nx-fcell text-center">
                <select name="language" id="rule-language">
                    @foreach ($langs as $row)
                    <option value="{{ (int) $row['id'] }}"@if (($row['id'] ?? 0) == ($rule['lang_id'] ?? 0)) selected @endif>{{ $row['lang_name'] }}</option>
                    @endforeach
                </select>
            </div>
        <div class="nx-ffull text-center"><input type="hidden" value="{{ (int) ($rule['id'] ?? 0) }}" name="id"><input type="submit" class="nx-btn nx-btn--primary" value="{{ __('label.save') }}"></div>
    </div>
</form>
@else
<br /><div class="text-center p-[5px]"><a href="/web/modrules?act=newsect">{{ __('modrules.add_section') }}</a></div>
@foreach ($rows as $arr)
<br /><x-data-table caption="Rules Management" captionHidden><x-slot:head><thead><tr><th class="bg-nxm-surface-alt font-semibold" scope="col">{{ $arr['title'] }} - {{ $arr['lang_name'] }}</th></tr></thead></x-slot:head>
    <tr><td>{{ $arr['textHtml'] }}</td></tr>
    <tr><td><a href="{{ request()->getPathInfo() }}?act=edit&id={{ (int) $arr['id'] }}">{{ __('functions.text_edit') }}</a>&nbsp;&nbsp;<form method="post" class="nx-inline" action="/web/staff/modrules?act=del">@csrf<input type="hidden" name="id" value="{{ (int) $arr['id'] }}"><input type="hidden" name="sure" value="1"><button type="submit" class="nx-postbtn nx-postbtn--danger">{{ __('functions.text_delete') }}</button></form></td></tr>
</x-data-table>
@endforeach
</x-page>
@endif
@endsection

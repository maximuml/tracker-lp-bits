@extends('layouts.legacy')

@section('title', "Rules Management")

@section('content')
@if ($mode === 'newsect')
<h1 align=center>Add Rules</h1>
<form method="post" class="nx-inline" action="modrules.php?act=addsect">
    @csrf
    <div class="nx-fgrid nx-fgrid--auto">
        <div class="nx-fcell">Title:</div><div class="nx-fcell"><input style="width: 400px;" type="text" name="title"/></div>
        <div class="nx-fcell" style="vertical-align: top;">Rules:</div><div class="nx-fcell"><textarea cols=90 rows=20 name="text"></textarea></div>
            <div class="nx-fcell">Language:</div>
            <div class="nx-fcell nx-center">
                <select name=language>
                    @foreach ($langs as $row)
                    <option value="{{ (int) $row['id'] }}"@if (($row['site_lang_folder'] ?? '') == $deflang) selected @endif>{{ $row['lang_name'] }}</option>
                    @endforeach
                </select>
            </div>
        <div class="nx-ffull nx-center"><input type="submit" value="Add" style="width: 60px;"></div>
    </div>
</form>
@elseif ($mode === 'edit')
<h1 align=center>Edit Rules</h1>
<form method="post" class="nx-inline" action="modrules.php?act=edited">
    @csrf
    <div class="nx-fgrid nx-fgrid--auto">
        <div class="nx-fcell">Title:</div><div class="nx-fcell"><input style="width: 400px;" type="text" name="title" value="{{ $rule['title'] ?? '' }}" /></div>
        <div class="nx-fcell" style="vertical-align: top;">Rules:</div><div class="nx-fcell"><textarea cols=90 rows=20 name="text">{{ $rule['text'] ?? '' }}</textarea></div>
            <div class="nx-fcell">Language:</div>
            <div class="nx-fcell nx-center">
                <select name=language>
                    @foreach ($langs as $row)
                    <option value="{{ (int) $row['id'] }}"@if (($row['id'] ?? 0) == ($rule['lang_id'] ?? 0)) selected @endif>{{ $row['lang_name'] }}</option>
                    @endforeach
                </select>
            </div>
        <div class="nx-ffull nx-center"><input type=hidden value="{{ (int) ($rule['id'] ?? 0) }}" name=id><input type="submit" value="Save" style="width: 60px;"></div>
    </div>
</form>
@else
<h1 align=center>Rules Management</h1>
<br /><div class="nx-center nx-cell-5 nx-w-940"><a href=modrules.php?act=newsect>Add Section</a></div>
@foreach ($rows as $arr)
<br /><table data-nx="data" width=940 border=1 cellspacing=0 cellpadding=5>
    <tr><td class=colhead>{{ $arr['title'] }} - {{ $arr['lang_name'] }}</td></tr>
    <tr><td align=left>{{ $arr['textHtml'] }}</td></tr>
    <tr><td align=left><a href="?act=edit&id={{ (int) $arr['id'] }}">Edit</a>&nbsp;&nbsp;<form method="post" class="nx-inline" action="modrules.php?act=del">@csrf<input type="hidden" name="id" value="{{ (int) $arr['id'] }}"><input type="hidden" name="sure" value="1"><button type="submit" class="nx-btn-link">Delete</button></form></td></tr>
</table>
@endforeach
@endif
@endsection

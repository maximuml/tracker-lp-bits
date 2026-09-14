@extends('layouts.legacy')

@section('title', "Rules Management")

@section('content')
@if ($mode === 'newsect')
<h1 align=center>Add Rules</h1>
<form method="post" class="nx-inline" action="modrules.php?act=addsect">
    @csrf
    <table border="1" cellspacing="0" cellpadding="10" align="center">
        <tr><td>Title:</td><td align=left><input style="width: 400px;" type="text" name="title"/></td></tr>
        <tr><td style="vertical-align: top;">Rules:</td><td><textarea cols=90 rows=20 name="text"></textarea></td></tr>
        <tr>
            <td>Language:</td>
            <td align="center">
                <select name=language>
                    @foreach ($langs as $row)
                    <option value="{{ (int) $row['id'] }}"@if (($row['site_lang_folder'] ?? '') == $deflang) selected @endif>{{ $row['lang_name'] }}</option>
                    @endforeach
                </select>
            </td>
        </tr>
        <tr><td colspan="2" align="center"><input type="submit" value="Add" style="width: 60px;"></td></tr>
    </table>
</form>
@elseif ($mode === 'edit')
<h1 align=center>Edit Rules</h1>
<form method="post" class="nx-inline" action="modrules.php?act=edited">
    @csrf
    <table border="1" cellspacing="0" cellpadding="10" align="center">
        <tr><td>Title:</td><td align=left><input style="width: 400px;" type="text" name="title" value="{{ $rule['title'] ?? '' }}" /></td></tr>
        <tr><td style="vertical-align: top;">Rules:</td><td><textarea cols=90 rows=20 name="text">{{ $rule['text'] ?? '' }}</textarea></td></tr>
        <tr>
            <td>Language:</td>
            <td align="center">
                <select name=language>
                    @foreach ($langs as $row)
                    <option value="{{ (int) $row['id'] }}"@if (($row['id'] ?? 0) == ($rule['lang_id'] ?? 0)) selected @endif>{{ $row['lang_name'] }}</option>
                    @endforeach
                </select>
            </td>
        </tr>
        <tr><td colspan="2" align="center"><input type=hidden value="{{ (int) ($rule['id'] ?? 0) }}" name=id><input type="submit" value="Save" style="width: 60px;"></td></tr>
    </table>
</form>
@else
<h1 align=center>Rules Management</h1>
<br /><table width=940 border=0 cellspacing=0 cellpadding=5>
<tr><td align=center><a href=modrules.php?act=newsect>Add Section</a></td></tr></table>
@foreach ($rows as $arr)
<br /><table width=940 border=1 cellspacing=0 cellpadding=5>
    <tr><td class=colhead>{{ $arr['title'] }} - {{ $arr['lang_name'] }}</td></tr>
    <tr><td align=left>@safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml($arr['textHtml']))</td></tr>
    <tr><td align=left><a href="?act=edit&id={{ (int) $arr['id'] }}">Edit</a>&nbsp;&nbsp;<form method="post" class="nx-inline" action="modrules.php?act=del">@csrf<input type="hidden" name="id" value="{{ (int) $arr['id'] }}"><input type="hidden" name="sure" value="1"><button type="submit" class="nx-btn-link">Delete</button></form></td></tr>
</table>
@endforeach
@endif
@endsection

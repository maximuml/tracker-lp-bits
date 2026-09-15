@extends('layouts.legacy')

@section('title', "FAQ Management")

@section('content')
@if (($mode ?? '') === 'edit')
    <h1 align="center">Edit Section or Item</h1>
    @if (empty($arr))
        <p>Invalid id</p>
    @elseif (($arr['type'] ?? '') === 'item')
        <form method="post" action="faqactions.php?action=edititem">
            @csrf
            <table border="1" cellspacing="0" cellpadding="10" align="center">
            <tr><td>ID:</td><td>{{ (int) $arr['id'] }} <input type="hidden" name="id" value="{{ (int) $arr['id'] }}" /></td></tr>
            <tr><td>Question:</td><td><input style="width: 600px;" type="text" name="question" value="{{ $arr['question'] ?? '' }}" /></td></tr>
            <tr><td style="vertical-align: top;">Answer:</td><td><textarea rows=20 style="width: 600px; height=600px;" name="answer">{{ $arr['answer'] ?? '' }}</textarea></td></tr>
            <tr><td>Status:</td><td>
                <select name="flag" style="width: 110px;">
                    <option value="0" style="color: #FF0000;"@if (($arr['flag'] ?? -1) == 0) selected="selected"@endif>Hidden</option>
                    <option value="1" style="color: #000000;"@if (($arr['flag'] ?? -1) == 1) selected="selected"@endif>Normal</option>
                    <option value="2" style="color: #0000FF;"@if (($arr['flag'] ?? -1) == 2) selected="selected"@endif>Updated</option>
                    <option value="3" style="color: #008000;"@if (($arr['flag'] ?? -1) == 3) selected="selected"@endif>New</option>
                </select>
            </td></tr>
            <tr><td>Category:</td><td>
                <select style="width: 400px;" name="categ">
                    @foreach (($categories ?? []) as $cat)
                    <option value="{{ (int) $cat['link_id'] }}"@if ($cat['link_id'] == ($arr['categ'] ?? null)) selected="selected"@endif>{{ $cat['question'] ?? '' }}</option>
                    @endforeach
                </select>
            </td></tr>
            <tr><td colspan="2" align="center"><input type="submit" name="edit" value="Edit" style="width: 60px;"></td></tr>
            </table>
        </form>
    @elseif (($arr['type'] ?? '') === 'categ')
        <form method="post" action="faqactions.php?action=editsect">
            @csrf
            <table border="1" cellspacing="0" cellpadding="10" align="center">
            <tr><td>ID:</td><td>{{ (int) $arr['id'] }} <input type="hidden" name="id" value="{{ (int) $arr['id'] }}" /></td></tr>
            <tr><td>Language:</td><td>{{ $arr['lang_name'] ?? '' }}</td></tr>
            <tr><td>Title:</td><td><input style="width: 300px;" type="text" name="title" value="{{ $arr['question'] ?? '' }}" /></td></tr>
            <tr><td>Status:</td><td>
                <select name="flag" style="width: 110px;">
                    <option value="0" style="color: #FF0000;"@if (($arr['flag'] ?? -1) == 0) selected="selected"@endif>Hidden</option>
                    <option value="1" style="color: #000000;"@if (($arr['flag'] ?? -1) == 1) selected="selected"@endif>Normal</option>
                </select>
            </td></tr>
            <tr><td colspan="2" align="center"><input type="submit" name="edit" value="Edit" style="width: 60px;"></td></tr>
            </table>
        </form>
    @endif
@elseif (($mode ?? '') === 'confirm_delete')
    <h1 align="center">Confirmation required</h1>
    <table data-nx="data" border="1" cellspacing="0" cellpadding="5" align="center" width="95%">
    <tr><td align="center">Please click <a href="faqactions.php?action=delete&id={{ (int) ($id ?? 0) }}&confirm=yes">here</a> to confirm.</td></tr>
    </table>
@elseif (($mode ?? '') === 'additem')
    <h1 align="center">Add Item</h1>
    <form method="post" action="faqactions.php?action=addnewitem">
        @csrf
        <table border="1" cellspacing="0" cellpadding="10" align="center">
        <tr><td>Question:</td><td><input style="width: 600px;" type="text" name="question" value="" /></td></tr>
        <tr><td style="vertical-align: top;">Answer:</td><td><textarea rows=20 style="width: 600px; height=600px;" name="answer"></textarea></td></tr>
        <tr><td>Status:</td><td>
            <select name="flag" style="width: 110px;">
                <option value="0" style="color: #FF0000;">Hidden</option>
                <option value="1" style="color: #000000;">Normal</option>
                <option value="2" style="color: #0000FF;">Updated</option>
                <option value="3" style="color: #008000;" selected="selected">New</option>
            </select>
        </td></tr>
        <input type="hidden" name="categ" value="{{ (int) ($inid ?? 0) }}">
        <input type="hidden" name="langid" value="{{ (int) ($langid ?? 0) }}">
        <tr><td colspan="2" align="center"><input type="submit" value="Add" style="width: 60px;"></td></tr>
        </table>
    </form>
@elseif (($mode ?? '') === 'addsection')
    <h1 align="center">Add Section</h1>
    <form method="post" action="faqactions.php?action=addnewsect">
        @csrf
        <table border="1" cellspacing="0" cellpadding="10" align="center">
        <tr><td>Title:</td><td><input style="width: 300px;" type="text" name="title" value="" /></td></tr>
        <tr><td>Language:</td><td>
            <select name="language">
                @foreach (($languages ?? []) as $row)
                <option value="{{ (int) $row['id'] }}"@if (($row['site_lang_folder'] ?? '') == ($deflang ?? '')) selected @endif>{{ $row['lang_name'] ?? '' }}</option>
                @endforeach
            </select>
        </td></tr>
        <tr><td>Status:</td><td>
            <select name="flag" style="width: 110px;">
                <option value="0" style="color: #FF0000;">Hidden</option>
                <option value="1" style="color: #000000;" selected="selected">Normal</option>
            </select>
        </td></tr>
        <tr><td colspan="2" align="center"><input type="submit" name="edit" value="Add" style="width: 60px;"></td></tr>
        </table>
    </form>
@endif
@endsection

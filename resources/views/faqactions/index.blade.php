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
            <div class="nx-fgrid nx-fgrid--auto nx-fgrid--pad10">
            <div class="nx-fcell">ID:</div><div class="nx-fcell">{{ (int) $arr['id'] }} <input type="hidden" name="id" value="{{ (int) $arr['id'] }}" /></div>
            <div class="nx-fcell">Question:</div><div class="nx-fcell"><input style="width: 600px;" type="text" name="question" value="{{ $arr['question'] ?? '' }}" /></div>
            <div class="nx-fcell" style="vertical-align: top;">Answer:</div><div class="nx-fcell"><textarea rows=20 style="width: 600px; height=600px;" name="answer">{{ $arr['answer'] ?? '' }}</textarea></div>
            <div class="nx-fcell">Status:</div><div class="nx-fcell">
                <select name="flag" style="width: 110px;">
                    <option value="0" style="color: #FF0000;"@if (($arr['flag'] ?? -1) == 0) selected="selected"@endif>Hidden</option>
                    <option value="1" style="color: #000000;"@if (($arr['flag'] ?? -1) == 1) selected="selected"@endif>Normal</option>
                    <option value="2" style="color: #0000FF;"@if (($arr['flag'] ?? -1) == 2) selected="selected"@endif>Updated</option>
                    <option value="3" style="color: #008000;"@if (($arr['flag'] ?? -1) == 3) selected="selected"@endif>New</option>
                </select>
            </div>
            <div class="nx-fcell">Category:</div><div class="nx-fcell">
                <select style="width: 400px;" name="categ">
                    @foreach (($categories ?? []) as $cat)
                    <option value="{{ (int) $cat['link_id'] }}"@if ($cat['link_id'] == ($arr['categ'] ?? null)) selected="selected"@endif>{{ $cat['question'] ?? '' }}</option>
                    @endforeach
                </select>
            </div>
            <div class="nx-ffull nx-center"><input type="submit" name="edit" value="Edit" style="width: 60px;"></div>
            </div>
        </form>
    @elseif (($arr['type'] ?? '') === 'categ')
        <form method="post" action="faqactions.php?action=editsect">
            @csrf
            <div class="nx-fgrid nx-fgrid--auto nx-fgrid--pad10">
            <div class="nx-fcell">ID:</div><div class="nx-fcell">{{ (int) $arr['id'] }} <input type="hidden" name="id" value="{{ (int) $arr['id'] }}" /></div>
            <div class="nx-fcell">Language:</div><div class="nx-fcell">{{ $arr['lang_name'] ?? '' }}</div>
            <div class="nx-fcell">Title:</div><div class="nx-fcell"><input style="width: 300px;" type="text" name="title" value="{{ $arr['question'] ?? '' }}" /></div>
            <div class="nx-fcell">Status:</div><div class="nx-fcell">
                <select name="flag" style="width: 110px;">
                    <option value="0" style="color: #FF0000;"@if (($arr['flag'] ?? -1) == 0) selected="selected"@endif>Hidden</option>
                    <option value="1" style="color: #000000;"@if (($arr['flag'] ?? -1) == 1) selected="selected"@endif>Normal</option>
                </select>
            </div>
            <div class="nx-ffull nx-center"><input type="submit" name="edit" value="Edit" style="width: 60px;"></div>
            </div>
        </form>
    @endif
@elseif (($mode ?? '') === 'confirm_delete')
    <h1 align="center">Confirmation required</h1>
    <div class="nx-box nx-w-97 nx-mx-auto nx-center">
    Please click <a href="faqactions.php?action=delete&id={{ (int) ($id ?? 0) }}&confirm=yes">here</a> to confirm.
    </div>
@elseif (($mode ?? '') === 'additem')
    <h1 align="center">Add Item</h1>
    <form method="post" action="faqactions.php?action=addnewitem">
        @csrf
        <div class="nx-fgrid nx-fgrid--auto nx-fgrid--pad10">
        <div class="nx-fcell">Question:</div><div class="nx-fcell"><input style="width: 600px;" type="text" name="question" value="" /></div>
        <div class="nx-fcell" style="vertical-align: top;">Answer:</div><div class="nx-fcell"><textarea rows=20 style="width: 600px; height=600px;" name="answer"></textarea></div>
        <div class="nx-fcell">Status:</div><div class="nx-fcell">
            <select name="flag" style="width: 110px;">
                <option value="0" style="color: #FF0000;">Hidden</option>
                <option value="1" style="color: #000000;">Normal</option>
                <option value="2" style="color: #0000FF;">Updated</option>
                <option value="3" style="color: #008000;" selected="selected">New</option>
            </select>
        </div>
        <input type="hidden" name="categ" value="{{ (int) ($inid ?? 0) }}">
        <input type="hidden" name="langid" value="{{ (int) ($langid ?? 0) }}">
        <div class="nx-ffull nx-center"><input type="submit" value="Add" style="width: 60px;"></div>
        </div>
    </form>
@elseif (($mode ?? '') === 'addsection')
    <h1 align="center">Add Section</h1>
    <form method="post" action="faqactions.php?action=addnewsect">
        @csrf
        <div class="nx-fgrid nx-fgrid--auto nx-fgrid--pad10">
        <div class="nx-fcell">Title:</div><div class="nx-fcell"><input style="width: 300px;" type="text" name="title" value="" /></div>
        <div class="nx-fcell">Language:</div><div class="nx-fcell">
            <select name="language">
                @foreach (($languages ?? []) as $row)
                <option value="{{ (int) $row['id'] }}"@if (($row['site_lang_folder'] ?? '') == ($deflang ?? '')) selected @endif>{{ $row['lang_name'] ?? '' }}</option>
                @endforeach
            </select>
        </div>
        <div class="nx-fcell">Status:</div><div class="nx-fcell">
            <select name="flag" style="width: 110px;">
                <option value="0" style="color: #FF0000;">Hidden</option>
                <option value="1" style="color: #000000;" selected="selected">Normal</option>
            </select>
        </div>
        <div class="nx-ffull nx-center"><input type="submit" name="edit" value="Add" style="width: 60px;"></div>
        </div>
    </form>
@endif
@endsection

@extends('layouts.legacy')

@section('title', 'FAQ Management')

@section('content')
<h1 align="center">FAQ Management</h1>
<form method="post" action="faqactions.php?action=reorder">
@csrf
@foreach (($faqCateg ?? []) as $lang => $temp2)
    @foreach ($temp2 as $id => $temp)
<br />
<table data-nx="data" border="1" cellspacing="0" cellpadding="5" align="center" width="95%">
<tr><td class="colhead" align="center" colspan="2">Position</td><td class="colhead" align="left">Section/Item Title</td><td class="colhead" align="center">Language</td><td class="colhead" align="center">Status</td><td class="colhead" align="center">Actions</td></tr>
<tr><td align="center" width="40px"><select name="order[{{ (int) $id }}]">
    @for ($n = 1; $n <= count($temp2); $n++)
        <option value="{{ $n }}"@if ($n == ($temp['order'] ?? 0)) selected="selected"@endif>{{ $n }}</option>
    @endfor
    </select></td><td align="center" width="40px">&nbsp;</td><td><b>{{ $temp['title'] ?? '' }}</b></td><td align="center" width="60px">{{ $temp['lang_name'] ?? '' }}</td><td align="center" width="60px">@if (($temp['flag'] ?? '') == "0")<font color="red">Hidden</font>@else Normal @endif</td><td align="center" width="60px"><a href="faqactions.php?action=edit&id={{ (int) ($temp['id'] ?? 0) }}">Edit</a> <a href="faqactions.php?action=delete&id={{ (int) ($temp['id'] ?? 0) }}">Delete</a></td></tr>
    @if (isset($temp['items']) && is_array($temp['items']))
        @foreach ($temp['items'] as $id2 => $tempItem)
<tr><td align="center" width="40px">&nbsp;</td><td align="center" width="40px"><select name="order[{{ (int) $id2 }}]">
            @for ($n = 1; $n <= count($temp['items']); $n++)
                <option value="{{ $n }}"@if ($n == ($tempItem['order'] ?? 0)) selected="selected"@endif>{{ $n }}</option>
            @endfor
            </select></td><td>{{ $tempItem['question'] ?? '' }}</td><td align="center"></td><td align="center" width="60px">@if (($tempItem['flag'] ?? '') == "0")<font color="#FF0000">Hidden</font>@elseif (($tempItem['flag'] ?? '') == "2")<font color="#0000FF"><img src="pic/updated.png" alt="Updated" width="46" height="11" align="absbottom"></font>@elseif (($tempItem['flag'] ?? '') == "3")<font color="#008000"><img src="pic/new.png" alt="New" width="27" height="11" align="absbottom"></font>@else Normal @endif</td><td align="center" width="60px"><a href="faqactions.php?action=edit&id={{ (int) $id2 }}">Edit</a> <a href="faqactions.php?action=delete&id={{ (int) $id2 }}">Delete</a></td></tr>
        @endforeach
    @endif
<tr><td colspan="6" align="center"><a href="faqactions.php?action=additem&inid={{ (int) $id }}&langid={{ (int) $lang }}">Add new item</a></td></tr>
</table>
    @endforeach
@endforeach
@if (! empty($faqOrphaned))
<br />
<table data-nx="data" border="1" cellspacing="0" cellpadding="5" align="center" width="95%">
<tr><td align="center" colspan="3"><b>Orphaned Items</b></td></tr>
<tr><td class="colhead" align="left">Item Title</td><td class="colhead" align="center">Status</td><td class="colhead" align="center">Actions</td></tr>
    @foreach ($faqOrphaned as $lang => $temp2)
        @foreach ($temp2 as $id => $temp)
<tr><td>{{ $temp['question'] ?? '' }}</td><td align="center" width="60px">@if (($temp['flag'] ?? '') == "0")<font color="#FF0000">Hidden</font>@elseif (($temp['flag'] ?? '') == "2")<font color="#0000FF">Updated</font>@elseif (($temp['flag'] ?? '') == "3")<font color="#008000">New</font>@else Normal @endif</td><td align="center" width="60px"><a href="faqactions.php?action=edit&id={{ (int) $id }}">edit</a> <a href="faqactions.php?action=delete&id={{ (int) $id }}">delete</a></td></tr>
        @endforeach
    @endforeach
</table>
@endif
<br />
<div class="nx-box nx-box--tight nx-w-97 nx-mx-auto nx-center">
    <a href="faqactions.php?action=addsection">Add new section</a>
</div>
<p align="center"><input type="submit" name="reorder" value="Reorder"></p>
</form>
<p>When the position numbers don't reflect the position in the table, it means the order id is bigger than the total number of sections/items and you should check all the order id's in the table and click "reorder"</p>
@endsection

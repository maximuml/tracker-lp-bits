@extends('layouts.legacy')

@section('title', 'FAQ Management')

@section('content')
<h1 class="nx-center">FAQ Management</h1>
<form method="post" action="faqactions.php?action=reorder">
@csrf
@foreach (($faqCateg ?? []) as $lang => $temp2)
    @foreach ($temp2 as $id => $temp)
<br />
<table data-nx="data">
<tr><th class="colhead" colspan="2" scope="colgroup">Position</th><th class="colhead nx-align-left" scope="col">Section/Item Title</th><th class="colhead" scope="col">Language</th><th class="colhead" scope="col">Status</th><th class="colhead" scope="col">Actions</th></tr>
<tr><td class="nx-center nx-w-40"><select name="order[{{ (int) $id }}]">
    @for ($n = 1; $n <= count($temp2); $n++)
        <option value="{{ $n }}"@if ($n == ($temp['order'] ?? 0)) selected="selected"@endif>{{ $n }}</option>
    @endfor
    </select></td><td class="nx-center nx-w-40">&nbsp;</td><td><b>{{ $temp['title'] ?? '' }}</b></td><td class="nx-center nx-w-60">{{ $temp['lang_name'] ?? '' }}</td><td class="nx-center nx-w-60">@if (($temp['flag'] ?? '') == "0")<span class="nx-color-red">Hidden</span>@else Normal @endif</td><td class="nx-center nx-w-60"><a href="faqactions.php?action=edit&id={{ (int) ($temp['id'] ?? 0) }}">Edit</a> <a href="faqactions.php?action=delete&id={{ (int) ($temp['id'] ?? 0) }}">Delete</a></td></tr>
    @if (isset($temp['items']) && is_array($temp['items']))
        @foreach ($temp['items'] as $id2 => $tempItem)
<tr><td class="nx-center nx-w-40">&nbsp;</td><td class="nx-center nx-w-40"><select name="order[{{ (int) $id2 }}]">
            @for ($n = 1; $n <= count($temp['items']); $n++)
                <option value="{{ $n }}"@if ($n == ($tempItem['order'] ?? 0)) selected="selected"@endif>{{ $n }}</option>
            @endfor
            </select></td><td>{{ $tempItem['question'] ?? '' }}</td><td class="nx-center"></td><td class="nx-center nx-w-60">@if (($tempItem['flag'] ?? '') == "0")<span class="nx-color-red">Hidden</span>@elseif (($tempItem['flag'] ?? '') == "2")<span class="nx-color-blue"><img src="pic/updated.png" alt="Updated" width="46" height="11" class="nx-va-bottom"></span>@elseif (($tempItem['flag'] ?? '') == "3")<span class="nx-color-green"><img src="pic/new.png" alt="New" width="27" height="11" class="nx-va-bottom"></span>@else Normal @endif</td><td class="nx-center nx-w-60"><a href="faqactions.php?action=edit&id={{ (int) $id2 }}">Edit</a> <a href="faqactions.php?action=delete&id={{ (int) $id2 }}">Delete</a></td></tr>
        @endforeach
    @endif
<tr><td colspan="6" class="nx-center"><a href="faqactions.php?action=additem&inid={{ (int) $id }}&langid={{ (int) $lang }}">Add new item</a></td></tr>
</table>
    @endforeach
@endforeach
@if (! empty($faqOrphaned))
<br />
<table data-nx="data">
<tr><td class="nx-center" colspan="3"><b>Orphaned Items</b></td></tr>
<tr><th class="colhead nx-align-left" scope="col">Item Title</th><th class="colhead" scope="col">Status</th><th class="colhead" scope="col">Actions</th></tr>
    @foreach ($faqOrphaned as $lang => $temp2)
        @foreach ($temp2 as $id => $temp)
<tr><td>{{ $temp['question'] ?? '' }}</td><td class="nx-center nx-w-60">@if (($temp['flag'] ?? '') == "0")<span class="nx-color-red">Hidden</span>@elseif (($temp['flag'] ?? '') == "2")<span class="nx-color-blue">Updated</span>@elseif (($temp['flag'] ?? '') == "3")<span class="nx-color-green">New</span>@else Normal @endif</td><td class="nx-center nx-w-60"><a href="faqactions.php?action=edit&id={{ (int) $id }}">edit</a> <a href="faqactions.php?action=delete&id={{ (int) $id }}">delete</a></td></tr>
        @endforeach
    @endforeach
</table>
@endif
<br />
<div class="nx-box nx-box--tight nx-w-97 nx-mx-auto nx-center">
    <a href="faqactions.php?action=addsection">Add new section</a>
</div>
<p class="nx-center"><input type="submit" name="reorder" value="Reorder"></p>
</form>
<p>When the position numbers don't reflect the position in the table, it means the order id is bigger than the total number of sections/items and you should check all the order id's in the table and click "reorder"</p>
@endsection

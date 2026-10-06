@extends('layouts.app', ['chromeVariant' => 'legacy'])

@section('title', 'FAQ Management')

@section('content')
<h1 class="text-center">FAQ Management</h1>
<form method="post" action="/web/faq/actions?action=reorder">@csrf
@foreach (($faqCateg ?? []) as $lang => $temp2)
    @foreach ($temp2 as $id => $temp)
<br />
<x-data-table :caption="$temp['title'] ?? 'FAQ section'" captionHidden><x-slot:head><thead><tr><th class="bg-nxm-surface-alt font-semibold" colspan="2" scope="colgroup">Position</th><th class="bg-nxm-surface-alt font-semibold text-left" scope="col">Section/Item Title</th><th class="bg-nxm-surface-alt font-semibold" scope="col">Language</th><th class="bg-nxm-surface-alt font-semibold" scope="col">Status</th><th class="bg-nxm-surface-alt font-semibold" scope="col">Actions</th></tr></thead></x-slot:head>
<tr><td class="text-center w-[40px]"><select name="order[{{ (int) $id }}]">
    @for ($n = 1; $n <= count($temp2); $n++)
        <option value="{{ $n }}"@if ($n == ($temp['order'] ?? 0)) selected="selected"@endif>{{ $n }}</option>
    @endfor
    </select></td><td class="text-center w-[40px]">&nbsp;</td><td><b>{{ $temp['title'] ?? '' }}</b></td><td class="text-center w-[60px]">{{ $temp['lang_name'] ?? '' }}</td><td class="text-center w-[60px]">@if (($temp['flag'] ?? '') == "0")<span class="text-nxm-danger">Hidden</span>@else Normal @endif</td><td class="text-center w-[60px]"><a href="faqactions.php?action=edit&id={{ (int) ($temp['id'] ?? 0) }}">Edit</a> <a href="faqactions.php?action=delete&id={{ (int) ($temp['id'] ?? 0) }}">Delete</a></td></tr>
    @if (isset($temp['items']) && is_array($temp['items']))
        @foreach ($temp['items'] as $id2 => $tempItem)
<tr><td class="text-center w-[40px]">&nbsp;</td><td class="text-center w-[40px]"><select name="order[{{ (int) $id2 }}]">
            @for ($n = 1; $n <= count($temp['items']); $n++)
                <option value="{{ $n }}"@if ($n == ($tempItem['order'] ?? 0)) selected="selected"@endif>{{ $n }}</option>
            @endfor
            </select></td><td>{{ $tempItem['question'] ?? '' }}</td><td class="text-center"></td><td class="text-center w-[60px]">@if (($tempItem['flag'] ?? '') == "0")<span class="text-nxm-danger">Hidden</span>@elseif (($tempItem['flag'] ?? '') == "2")<span class="text-[#0000ff]"><img src="pic/updated.png" alt="Updated" width="46" height="11" class="align-bottom"></span>@elseif (($tempItem['flag'] ?? '') == "3")<span class="text-nxm-success"><img src="pic/new.png" alt="New" width="27" height="11" class="align-bottom"></span>@else Normal @endif</td><td class="text-center w-[60px]"><a href="faqactions.php?action=edit&id={{ (int) $id2 }}">Edit</a> <a href="faqactions.php?action=delete&id={{ (int) $id2 }}">Delete</a></td></tr>
        @endforeach
    @endif
<tr><td colspan="6" class="text-center"><a href="faqactions.php?action=additem&inid={{ (int) $id }}&langid={{ (int) $lang }}">Add new item</a></td></tr>
</x-data-table>
    @endforeach
@endforeach
@if (! empty($faqOrphaned))
<br />
<x-data-table caption="Orphaned Items" captionHidden>
<tr><td class="text-center" colspan="3"><b>Orphaned Items</b></td></tr>
<tr><th class="bg-nxm-surface-alt font-semibold text-left" scope="col">Item Title</th><th class="bg-nxm-surface-alt font-semibold" scope="col">Status</th><th class="bg-nxm-surface-alt font-semibold" scope="col">Actions</th></tr>
    @foreach ($faqOrphaned as $lang => $temp2)
        @foreach ($temp2 as $id => $temp)
<tr><td>{{ $temp['question'] ?? '' }}</td><td class="text-center w-[60px]">@if (($temp['flag'] ?? '') == "0")<span class="text-nxm-danger">Hidden</span>@elseif (($temp['flag'] ?? '') == "2")<span class="text-[#0000ff]">Updated</span>@elseif (($temp['flag'] ?? '') == "3")<span class="text-nxm-success">New</span>@else Normal @endif</td><td class="text-center w-[60px]"><a href="faqactions.php?action=edit&id={{ (int) $id }}">edit</a> <a href="faqactions.php?action=delete&id={{ (int) $id }}">delete</a></td></tr>
        @endforeach
    @endforeach
</x-data-table>
@endif
<br />
<div class="nx-box nx-box--tight w-[97%] mx-auto text-center">
    <a href="faqactions.php?action=addsection">Add new section</a>
</div>
<p class="text-center"><input type="submit" name="reorder" value="Reorder"></p>
</form>
<p>When the position numbers don't reflect the position in the table, it means the order id is bigger than the total number of sections/items and you should check all the order id's in the table and click "reorder"</p>
@endsection

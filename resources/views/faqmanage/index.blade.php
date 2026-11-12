@extends('layouts.app')

@section('title', __('faqmanage.page_title'))

@section('content')
<x-page :title="'FAQ Management'" width="list">
<form method="post" action="/web/faq/actions?action=reorder">@csrf
@foreach (($faqCateg ?? []) as $lang => $temp2)
    @foreach ($temp2 as $id => $temp)
<br />
<x-data-table :caption="$temp['title'] ?? 'FAQ section'" captionHidden><x-slot:head><thead><tr><th class="bg-nxm-surface-alt font-semibold" colspan="2" scope="colgroup">{{ __('faqmanage.col_position') }}</th><th class="bg-nxm-surface-alt font-semibold text-left" scope="col">{{ __('faqmanage.col_title') }}</th><th class="bg-nxm-surface-alt font-semibold" scope="col">{{ __('functions.text_language') }}</th><th class="bg-nxm-surface-alt font-semibold" scope="col">{{ __('label.status') }}</th><th class="bg-nxm-surface-alt font-semibold" scope="col">{{ __('label.actions') }}</th></tr></thead></x-slot:head>
<tr><td class="text-center w-[40px]"><select name="order[{{ (int) $id }}]" aria-label="Order {{ (int) $id }}">
    @for ($n = 1; $n <= count($temp2); $n++)
        <option value="{{ $n }}"@if ($n == ($temp['order'] ?? 0)) selected="selected"@endif>{{ $n }}</option>
    @endfor
    </select></td><td class="text-center w-[40px]">&nbsp;</td><td><b>{{ $temp['title'] ?? '' }}</b></td><td class="text-center w-[60px]">{{ $temp['lang_name'] ?? '' }}</td><td class="text-center w-[60px]">@if (($temp['flag'] ?? '') == "0")<span class="text-nxm-danger">{{ __('faqmanage.status_hidden') }}</span>@else Normal @endif</td><td class="text-center w-[60px]"><a href="/web/faqactions?action=edit&id={{ (int) ($temp['id'] ?? 0) }}">{{ __('functions.text_edit') }}</a> <a href="/web/faqactions?action=delete&id={{ (int) ($temp['id'] ?? 0) }}">{{ __('functions.text_delete') }}</a></td></tr>
    @if (isset($temp['items']) && is_array($temp['items']))
        @foreach ($temp['items'] as $id2 => $tempItem)
<tr><td class="text-center w-[40px]">&nbsp;</td><td class="text-center w-[40px]"><select name="order[{{ (int) $id2 }}]" aria-label="Order {{ (int) $id2 }}">
            @for ($n = 1; $n <= count($temp['items']); $n++)
                <option value="{{ $n }}"@if ($n == ($tempItem['order'] ?? 0)) selected="selected"@endif>{{ $n }}</option>
            @endfor
            </select></td><td>{{ $tempItem['question'] ?? '' }}</td><td class="text-center"></td><td class="text-center w-[60px]">@if (($tempItem['flag'] ?? '') == "0")<span class="text-nxm-danger">{{ __('faqmanage.status_hidden') }}</span>@elseif (($tempItem['flag'] ?? '') == "2")<span class="text-nxm-accent-text"><img src="/pic/updated.png" alt="{{ __('faqmanage.status_updated') }}" width="46" height="11" class="align-bottom"></span>@elseif (($tempItem['flag'] ?? '') == "3")<span class="text-nxm-success"><img src="/pic/new.png" alt="{{ __('faqmanage.status_new') }}" width="27" height="11" class="align-bottom"></span>@else Normal @endif</td><td class="text-center w-[60px]"><a href="/web/faqactions?action=edit&id={{ (int) $id2 }}">{{ __('functions.text_edit') }}</a> <a href="/web/faqactions?action=delete&id={{ (int) $id2 }}">{{ __('functions.text_delete') }}</a></td></tr>
        @endforeach
    @endif
<tr><td colspan="6" class="text-center"><a href="/web/faqactions?action=additem&inid={{ (int) $id }}&langid={{ (int) $lang }}">{{ __('faqmanage.add_item') }}</a></td></tr>
</x-data-table>
    @endforeach
@endforeach
@if (! empty($faqOrphaned))
<br />
<x-data-table caption="Orphaned Items" captionHidden>
<tr><td class="text-center" colspan="3"><b>{{ __('faqmanage.orphaned') }}</b></td></tr>
<tr><th class="bg-nxm-surface-alt font-semibold text-left" scope="col">{{ __('faqmanage.item_title') }}</th><th class="bg-nxm-surface-alt font-semibold" scope="col">{{ __('label.status') }}</th><th class="bg-nxm-surface-alt font-semibold" scope="col">{{ __('label.actions') }}</th></tr>
    @foreach ($faqOrphaned as $lang => $temp2)
        @foreach ($temp2 as $id => $temp)
<tr><td>{{ $temp['question'] ?? '' }}</td><td class="text-center w-[60px]">@if (($temp['flag'] ?? '') == "0")<span class="text-nxm-danger">{{ __('faqmanage.status_hidden') }}</span>@elseif (($temp['flag'] ?? '') == "2")<span class="text-nxm-accent-text">{{ __('faqmanage.status_updated') }}</span>@elseif (($temp['flag'] ?? '') == "3")<span class="text-nxm-success">{{ __('faqmanage.status_new') }}</span>@else Normal @endif</td><td class="text-center w-[60px]"><a href="/web/faqactions?action=edit&id={{ (int) $id }}">edit</a> <a href="/web/faqactions?action=delete&id={{ (int) $id }}">delete</a></td></tr>
        @endforeach
    @endforeach
</x-data-table>
@endif
<br />
<div class="nx-box nx-box--tight w-[97%] mx-auto text-center">
    <a href="/web/faqactions?action=addsection">{{ __('faqmanage.add_section') }}</a>
</div>
<p class="text-center"><input type="submit" name="reorder" class="nx-btn nx-btn--primary" value="{{ __('faqmanage.reorder') }}" data-submit-once></p>
</form>
<p>{{ __('faqmanage.position_note') }}</p>
</x-page>
@endsection

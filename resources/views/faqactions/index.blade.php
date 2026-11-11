@extends('layouts.app')

@section('title', "FAQ Management")

@section('content')
<x-page :title="($mode ?? '') === 'edit' ? 'Edit Section or Item' : (($mode ?? '') === 'confirm_delete' ? 'Confirmation required' : (($mode ?? '') === 'additem' ? 'Add Item' : (($mode ?? '') === 'addsection' ? 'Add Section' : 'FAQ Management')))" width="form">
@if (($mode ?? '') === 'edit')
    @if (empty($arr))
        <p>{{ __('faqactions.invalid_id') }}</p>
    @elseif (($arr['type'] ?? '') === 'item')
        <form method="post" action="/web/faq/actions?action=edititem">
            @csrf
            <div class="nx-fgrid nx-fgrid--auto nx-fgrid--pad10">
            <div class="nx-fcell">ID:</div><div class="nx-fcell">{{ (int) $arr['id'] }} <input type="hidden" name="id" value="{{ (int) $arr['id'] }}" /></div>
            <label class="nx-fcell" for="faq-question">Question:</label><div class="nx-fcell"><input type="text" id="faq-question" name="question" value="{{ $arr['question'] ?? '' }}" /></div>
            <label class="nx-fcell" for="faq-answer">Answer:</label><div class="nx-fcell"><textarea rows=20 id="faq-answer" name="answer">{{ $arr['answer'] ?? '' }}</textarea></div>
            <label class="nx-fcell" for="faq-flag">Status:</label><div class="nx-fcell">
                <select name="flag" id="faq-flag">
                    <option value="0"@if (($arr['flag'] ?? -1) == 0) selected="selected"@endif>{{ __('faqactions.status_hidden') }}</option>
                    <option value="1"@if (($arr['flag'] ?? -1) == 1) selected="selected"@endif>{{ __('faqactions.status_normal') }}</option>
                    <option value="2"@if (($arr['flag'] ?? -1) == 2) selected="selected"@endif>{{ __('faqactions.status_updated') }}</option>
                    <option value="3"@if (($arr['flag'] ?? -1) == 3) selected="selected"@endif>{{ __('faqactions.status_new') }}</option>
                </select>
            </div>
            <label class="nx-fcell" for="faq-categ">Category:</label><div class="nx-fcell">
                <select name="categ" id="faq-categ">
                    @foreach (($categories ?? []) as $cat)
                    <option value="{{ (int) $cat['link_id'] }}"@if ($cat['link_id'] == ($arr['categ'] ?? null)) selected="selected"@endif>{{ $cat['question'] ?? '' }}</option>
                    @endforeach
                </select>
            </div>
            <div class="nx-ffull text-center"><input type="submit" name="edit" class="nx-btn nx-btn--primary" value="{{ __('functions.text_edit') }}"></div>
            </div>
        </form>
    @elseif (($arr['type'] ?? '') === 'categ')
        <form method="post" action="/web/faq/actions?action=editsect">
            @csrf
            <div class="nx-fgrid nx-fgrid--auto nx-fgrid--pad10">
            <div class="nx-fcell">ID:</div><div class="nx-fcell">{{ (int) $arr['id'] }} <input type="hidden" name="id" value="{{ (int) $arr['id'] }}" /></div>
            <div class="nx-fcell">Language:</div><div class="nx-fcell">{{ $arr['lang_name'] ?? '' }}</div>
            <label class="nx-fcell" for="faq-title">Title:</label><div class="nx-fcell"><input type="text" id="faq-title" name="title" value="{{ $arr['question'] ?? '' }}" /></div>
            <label class="nx-fcell" for="faq-flag">Status:</label><div class="nx-fcell">
                <select name="flag" id="faq-flag">
                    <option value="0"@if (($arr['flag'] ?? -1) == 0) selected="selected"@endif>{{ __('faqactions.status_hidden') }}</option>
                    <option value="1"@if (($arr['flag'] ?? -1) == 1) selected="selected"@endif>{{ __('faqactions.status_normal') }}</option>
                </select>
            </div>
            <div class="nx-ffull text-center"><input type="submit" name="edit" class="nx-btn nx-btn--primary" value="{{ __('functions.text_edit') }}"></div>
            </div>
        </form>
    @endif
@elseif (($mode ?? '') === 'confirm_delete')
    <div class="nx-box w-[97%] mx-auto text-center">
    Please click <form method="post" action="/web/faq/actions?action=delete&id={{ (int) ($id ?? 0) }}" class="nx-inline">@csrf<input type="hidden" name="confirm" value="yes"><button type="submit" class="nx-btn-link">here</button></form> to confirm.
    </div>
@elseif (($mode ?? '') === 'additem')
    <form method="post" action="/web/faq/actions?action=addnewitem">
        @csrf
        <div class="nx-fgrid nx-fgrid--auto nx-fgrid--pad10">
        <label class="nx-fcell" for="faq-question">Question:</label><div class="nx-fcell"><input type="text" id="faq-question" name="question" value="" /></div>
        <label class="nx-fcell" for="faq-answer">Answer:</label><div class="nx-fcell"><textarea rows=20 id="faq-answer" name="answer"></textarea></div>
        <label class="nx-fcell" for="faq-flag">Status:</label><div class="nx-fcell">
            <select name="flag" id="faq-flag">
                <option value="0">{{ __('faqactions.status_hidden') }}</option>
                <option value="1">{{ __('faqactions.status_normal') }}</option>
                <option value="2">{{ __('faqactions.status_updated') }}</option>
                <option value="3" selected="selected">{{ __('faqactions.status_new') }}</option>
            </select>
        </div>
        <input type="hidden" name="categ" value="{{ (int) ($inid ?? 0) }}">
        <input type="hidden" name="langid" value="{{ (int) ($langid ?? 0) }}">
        <div class="nx-ffull text-center"><input type="submit" class="nx-btn nx-btn--primary" value="{{ __('fields.text_add') }}"></div>
        </div>
    </form>
@elseif (($mode ?? '') === 'addsection')
    <form method="post" action="/web/faq/actions?action=addnewsect">
        @csrf
        <div class="nx-fgrid nx-fgrid--auto nx-fgrid--pad10">
        <label class="nx-fcell" for="faq-title">Title:</label><div class="nx-fcell"><input type="text" id="faq-title" name="title" value="" /></div>
        <label class="nx-fcell" for="faq-language">Language:</label><div class="nx-fcell">
            <select name="language" id="faq-language">
                @foreach (($languages ?? []) as $row)
                <option value="{{ (int) $row['id'] }}"@if (($row['site_lang_folder'] ?? '') == ($deflang ?? '')) selected @endif>{{ $row['lang_name'] ?? '' }}</option>
                @endforeach
            </select>
        </div>
        <label class="nx-fcell" for="faq-flag">Status:</label><div class="nx-fcell">
            <select name="flag" id="faq-flag">
                <option value="0">{{ __('faqactions.status_hidden') }}</option>
                <option value="1" selected="selected">{{ __('faqactions.status_normal') }}</option>
            </select>
        </div>
        <div class="nx-ffull text-center"><input type="submit" name="edit" class="nx-btn nx-btn--primary" value="{{ __('fields.text_add') }}"></div>
        </div>
    </form>
@endif
</x-page>
@endsection

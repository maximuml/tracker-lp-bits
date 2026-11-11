@extends('layouts.app', ['shell' => 'bare'])

@section('title', $headTitle)

@section('content')
<h1 class="nx-sr-only">{{ $headTitle }}</h1>
@if (! $canEdit)
<h2 class="text-center">{{ __('edit.text_cannot_edit_torrent') }}</h2>
<p>{{ __('edit.text_cannot_edit_torrent_note') }} <a href="/login?returnto={{ $requestUri }}&nowarn=1">{{ __('edit.text_logged_in') }}</a> {{ __('edit.text_cannot_edit_torrent_note_end') }}</p>
@else
<form method="post" id="compose" name="edittorrent" action="/takeedit" enctype="multipart/form-data">@csrf
<input type="hidden" name="id" value="{{ $torrentId }}" />
@if ($returnto !== '')
<input type="hidden" name="returnto" value="{{ $returnto }}" />
@endif
<div class="nx-setcards nx-upload">
    <section class="nx-ucard nx-setcard nx-setcard--wide" aria-labelledby="edit-sec-torrent">
        <h2 class="nx-setcard__title" id="edit-sec-torrent">{{ $torrentRow['name'] }}</h2>
        <div class="nx-setrow">
            <label class="nx-setrow__label" for="name">{{ __('edit.row_torrent_name') }}<span class="text-nxm-danger">*</span></label>
            <div class="nx-setrow__control"><input type="text" id="name" name="name" value="{{ $torrentRow['name'] }}" /></div>
        </div>
        @if ($priceRow !== null)
        <x-settings-row layout="setrow" :label="\App\Support\Locale::trans('label.torrent.price', [], null)"><input type="number" min="0" name="price" value="{{ $priceRow['value'] }}" placeholder="{{ $priceRow['placeholder'] }}" />&nbsp;&nbsp;{{ $priceRow['help'] }}</x-settings-row>
        @endif
        <div class="nx-setrow">
            <span class="nx-setrow__label" id="edit-descr-label">{{ __('edit.row_description') }}<span class="text-nxm-danger">*</span></span>
            <div class="nx-setrow__control" aria-labelledby="edit-descr-label"><livewire:bbcode-editor form="edittorrent" text="descr" :content="$descrContent ?? ''" :required="true" /></div>
        </div>
        @if ($technicalInfoEnabled)
        <x-settings-row layout="setrow" :label="__('functions.text_technical_info')" for="technical_info"><textarea id="technical_info" name="technical_info" rows="8">{{ $torrentRow['technical_info'] ?? '' }}</textarea><br/><b>&middot;</b> {{ __('functions.text_technical_info_help_text') }} <b><a href="https://mediaarea.net/en/MediaInfo" target='_blank' rel="noopener">{{ __('functions.text_technical_info_help_link_mediainfo') }}</a></b>{{ __('functions.text_technical_info_help_text_one_end') }}<br /><b>&middot;</b> {{ __('functions.text_technical_info_help_text_two') }} <b><a href="https://github.com/UniqProject/BDInfo" target='_blank' rel="noopener">{{ __('functions.text_technical_info_help_link_bdinfo') }}</a></b>{{ __('functions.text_technical_info_help_text_two_end') }}</x-settings-row>
        @endif
        <div class="nx-setrow">
            <label class="nx-setrow__label" for="type">{{ __('edit.row_type') }}<span class="text-nxm-danger">*</span></label>
            <div class="nx-setrow__control"><select id="type" name="type" data-mode='{{ $sectionMode }}'>
@foreach ($cats as $subrow)
<option value="{{ $subrow['id'] }}"@if ($subrow['id'] == $torrentRow['category']) selected="selected"@endif>{{ $subrow['name'] }}</option>
@endforeach
            </select></div>
        </div>
        <div class="nx-grouprow nx-setrows" id="editcat_section" data-mode="{{ $sectionMode }}">
            <div class="nx-setrow nx-grouprow" relation="{{ $modeClass }}">
                <span class="nx-setrow__label" id="edit-quality-label">{{ __('edit.row_quality') }}</span>
                <div class="nx-setrow__control" role="group" aria-labelledby="edit-quality-label">{{ $taxonomySelect }}</div>
            </div>
            {{ $customFieldsHtml }}
            {{ $hitAndRunHtml }}
            <div class="nx-setrow nx-grouprow" relation="{{ $modeClass }}">
                <span class="nx-setrow__label" id="edit-tags-label">{{ __('functions.text_tags') }}</span>
                <div class="nx-setrow__control" role="group" aria-labelledby="edit-tags-label">{{ $tagCheckbox }}</div>
            </div>
        </div>
        @if ($showVisibleCheck || $showAnonymousCheck)
        <x-settings-row layout="setrow" :label="__('edit.row_check')">@if ($showVisibleCheck)<input type="hidden" name="visible" value="0" /><label><input id="visible" type="checkbox" name="visible"@if ($visibleChecked) checked="checked"@endif value="1" />{{ __('edit.checkbox_visible') }}</label>@endif
@if ($showVisibleCheck && $showAnonymousCheck)&nbsp;&nbsp;@endif
@if ($showAnonymousCheck)<input type="hidden" name="anonymous" value="0" /><label><input type="checkbox" name="anonymous"@if ($anonymousChecked) checked="checked"@endif value="1" />{{ __('edit.checkbox_anonymous_note') }}</label>@endif</x-settings-row>
        @endif
        @if ($pick !== null && ($pick->promotionOptions !== null || $pick->posStates !== null))
        <x-settings-row layout="setrow" :label="__('edit.row_pick')">@include('components.torrent.edit.pick', ['pick' => $pick])</x-settings-row>
        @endif
    </section>
</div>
<div class="nx-upload__submit"><input id="qr" type="submit" class="nx-btn nx-btn--primary" value="{{ __('edit.submit_edit_it') }}" /> <input type="reset" class="nx-btn" value="{{ __('edit.submit_revert_changes') }}" /></div>
</form>
@if ($showDeleteForm)
<form method="post" action="/web/torrents/delete">
@csrf
<input type="hidden" name="id" value="{{ $torrentId }}" />
@if ($returnto !== '')
<input type="hidden" name="returnto" value="{{ $returnto }}" />
@endif
<div class="nx-setcards nx-upload">
    <section class="nx-ucard nx-setcard nx-setcard--wide" aria-labelledby="edit-sec-delete">
        <h2 class="nx-setcard__title" id="edit-sec-delete">{{ __('edit.text_delete_torrent') }} {{ __('edit.text_reason') }}</h2>
        <div class="nx-setrow">
            <span class="nx-setrow__label"><label><input name="reasontype" type="radio" value="1" /> {{ __('edit.radio_dead') }}</label></span>
            <div class="nx-setrow__control">{{ __('edit.text_dead_note') }}</div>
        </div>
        <div class="nx-setrow">
            <span class="nx-setrow__label"><label><input name="reasontype" type="radio" value="2" /> {{ __('edit.radio_dupe') }}</label></span>
            <div class="nx-setrow__control"><input type="text" name="reason[]" aria-label="{{ __('edit.radio_dupe') }} {{ __('edit.text_reason') }}" /></div>
        </div>
        <div class="nx-setrow">
            <span class="nx-setrow__label"><label><input name="reasontype" type="radio" value="3" /> {{ __('edit.radio_nuked') }}</label></span>
            <div class="nx-setrow__control"><input type="text" name="reason[]" aria-label="{{ __('edit.radio_nuked') }} {{ __('edit.text_reason') }}" /></div>
        </div>
        <div class="nx-setrow">
            <span class="nx-setrow__label"><label><input name="reasontype" type="radio" value="4" /> {{ __('edit.radio_rules') }}</label></span>
            <div class="nx-setrow__control"><input type="text" name="reason[]" aria-label="{{ __('edit.radio_rules') }} {{ __('edit.text_reason') }}" />{{ __('edit.text_req') }}</div>
        </div>
        <div class="nx-setrow">
            <span class="nx-setrow__label"><label><input name="reasontype" type="radio" value="5" checked="checked" /> {{ __('edit.radio_other') }}</label></span>
            <div class="nx-setrow__control"><input type="text" name="reason[]" aria-label="{{ __('edit.radio_other') }} {{ __('edit.text_reason') }}" />{{ __('edit.text_req') }}</div>
        </div>
    </section>
</div>
<div class="nx-upload__submit"><input type="submit" class="nx-btn nx-btn--danger" value="{{ __('edit.submit_delete_it') }}" /></div>
</form>
@endif
@endif
@endsection

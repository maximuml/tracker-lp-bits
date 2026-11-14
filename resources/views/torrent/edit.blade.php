@extends('layouts.app', ['shell' => 'bare'])

@section('title', $vm->headTitle)

@section('content')
<h1 class="nx-sr-only">{{ $vm->headTitle }}</h1>
@if (! $vm->canEdit)
<h2 class="text-center">{{ __('edit.text_cannot_edit_torrent') }}</h2>
<p>{{ __('edit.text_cannot_edit_torrent_note') }} <a href="/login?returnto={{ $vm->requestUri }}&nowarn=1">{{ __('edit.text_logged_in') }}</a> {{ __('edit.text_cannot_edit_torrent_note_end') }}</p>
@else
<form method="post" id="compose" name="edittorrent" action="/takeedit" enctype="multipart/form-data">@csrf
<input type="hidden" name="id" value="{{ $vm->torrentId }}" />
@if ($vm->returnto !== '')
<input type="hidden" name="returnto" value="{{ $vm->returnto }}" />
@endif
<div class="nx-setcards nx-upload">
    <section class="nx-ucard nx-setcard nx-setcard--wide" aria-labelledby="edit-sec-torrent">
        <h2 class="nx-setcard__title" id="edit-sec-torrent">{{ $vm->torrentRow['name'] }}</h2>
        <div class="nx-setrow">
            <label class="nx-setrow__label" for="name">{{ __('edit.row_torrent_name') }}<span class="text-nxm-danger">*</span></label>
            <div class="nx-setrow__control"><input type="text" id="name" name="name" value="{{ $vm->torrentRow['name'] }}" /></div>
        </div>
        @if ($vm->priceRow !== null)
        <x-settings-row layout="setrow" :label="\App\Support\Locale::trans('label.torrent.price', [], null)"><input type="number" min="0" name="price" value="{{ $vm->priceRow['value'] }}" placeholder="{{ $vm->priceRow['placeholder'] }}" />&nbsp;&nbsp;{{ $vm->priceRow['help'] }}</x-settings-row>
        @endif
        <div class="nx-setrow">
            <span class="nx-setrow__label" id="edit-descr-label">{{ __('edit.row_description') }}<span class="text-nxm-danger">*</span></span>
            <div class="nx-setrow__control" aria-labelledby="edit-descr-label"><livewire:bbcode-editor form="edittorrent" text="descr" :content="$vm->descrContent ?? ''" :required="true" /></div>
        </div>
        @if ($vm->technicalInfoEnabled)
        <x-settings-row layout="setrow" :label="__('functions.text_technical_info')" for="technical_info"><textarea id="technical_info" name="technical_info" rows="8">{{ $vm->torrentRow['technical_info'] ?? '' }}</textarea><br/><b>&middot;</b> {{ __('functions.text_technical_info_help_text') }} <b><a href="https://mediaarea.net/en/MediaInfo" target='_blank' rel="noopener">{{ __('functions.text_technical_info_help_link_mediainfo') }}</a></b>{{ __('functions.text_technical_info_help_text_one_end') }}<br /><b>&middot;</b> {{ __('functions.text_technical_info_help_text_two') }} <b><a href="https://github.com/UniqProject/BDInfo" target='_blank' rel="noopener">{{ __('functions.text_technical_info_help_link_bdinfo') }}</a></b>{{ __('functions.text_technical_info_help_text_two_end') }}</x-settings-row>
        @endif
        <div class="nx-setrow">
            <label class="nx-setrow__label" for="type">{{ __('edit.row_type') }}<span class="text-nxm-danger">*</span></label>
            <div class="nx-setrow__control"><select id="type" name="type" data-mode='{{ $vm->sectionMode }}'>
@foreach ($vm->cats as $subrow)
<option value="{{ $subrow['id'] }}"@if ($subrow['id'] == $vm->torrentRow['category']) selected="selected"@endif>{{ $subrow['name'] }}</option>
@endforeach
            </select></div>
        </div>
        <div class="nx-grouprow nx-setrows" id="editcat_section" data-mode="{{ $vm->sectionMode }}">
            <div class="nx-setrow nx-grouprow" relation="{{ $vm->modeClass }}">
                <span class="nx-setrow__label" id="edit-quality-label">{{ __('edit.row_quality') }}</span>
                <div class="nx-setrow__control" role="group" aria-labelledby="edit-quality-label">{{ $vm->taxonomySelect }}</div>
            </div>
            {{ $vm->customFieldsHtml }}
            {{ $vm->hitAndRunHtml }}
            <div class="nx-setrow nx-grouprow" relation="{{ $vm->modeClass }}">
                <span class="nx-setrow__label" id="edit-tags-label">{{ __('functions.text_tags') }}</span>
                <div class="nx-setrow__control" role="group" aria-labelledby="edit-tags-label">{{ $vm->tagCheckbox }}</div>
            </div>
        </div>
        @if ($vm->showVisibleCheck || $vm->showAnonymousCheck)
        <x-settings-row layout="setrow" :label="__('edit.row_check')">@if ($vm->showVisibleCheck)<input type="hidden" name="visible" value="0" /><label><input id="visible" type="checkbox" name="visible"@if ($vm->visibleChecked) checked="checked"@endif value="1" />{{ __('edit.checkbox_visible') }}</label>@endif
@if ($vm->showVisibleCheck && $vm->showAnonymousCheck)&nbsp;&nbsp;@endif
@if ($vm->showAnonymousCheck)<input type="hidden" name="anonymous" value="0" /><label><input type="checkbox" name="anonymous"@if ($vm->anonymousChecked) checked="checked"@endif value="1" />{{ __('edit.checkbox_anonymous_note') }}</label>@endif</x-settings-row>
        @endif
        @if ($vm->pick !== null && ($vm->pick->promotionOptions !== null || $vm->pick->posStates !== null))
        <x-settings-row layout="setrow" :label="__('edit.row_pick')">@include('components.torrent.edit.pick', ['pick' => $vm->pick])</x-settings-row>
        @endif
    </section>
</div>
<div class="nx-upload__submit"><input id="qr" type="submit" class="nx-btn nx-btn--primary" value="{{ __('edit.submit_edit_it') }}"  data-submit-once /> <input type="reset" class="nx-btn" value="{{ __('edit.submit_revert_changes') }}" /></div>
</form>
@if ($vm->showDeleteForm)
<form method="post" action="/web/torrents/delete">
@csrf
<input type="hidden" name="id" value="{{ $vm->torrentId }}" />
@if ($vm->returnto !== '')
<input type="hidden" name="returnto" value="{{ $vm->returnto }}" />
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
<div class="nx-upload__submit"><input type="submit" class="nx-btn nx-btn--danger" value="{{ __('edit.submit_delete_it') }}"  data-submit-once /></div>
</form>
@endif
@endif
@endsection

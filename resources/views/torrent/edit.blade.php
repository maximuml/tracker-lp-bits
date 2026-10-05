@extends('layouts.app', ['chromeVariant' => 'legacy', 'shell' => 'bare'])

@section('title', $headTitle)

@section('content')
@if (! $canEdit)
<h1 class="text-center">{{ __('legacy/edit.text_cannot_edit_torrent') ?? '' }}</h1>
<p>{{ __('legacy/edit.text_cannot_edit_torrent_note') }} <a href="login.php?returnto={{ $requestUri }}&nowarn=1">{{ __('legacy/edit.text_logged_in') }}</a> {{ __('legacy/edit.text_cannot_edit_torrent_note_end') }}</p>
@else
<form method="post" id="compose" name="edittorrent" action="/takeedit" enctype="multipart/form-data">
<input type="hidden" name="id" value="{{ $torrentId }}" />
@if ($returnto !== '')
<input type="hidden" name="returnto" value="{{ $returnto }}" />
@endif
<div class="nx-fgrid">
<div class="nx-ffull nx-colhead text-center">{{ $torrentRow['name'] }}</div>
<div class="nx-fhead whitespace-nowrap">{{ __('legacy/edit.row_torrent_name') }}<span class="text-nxm-danger">*</span></div><div class="nx-fcell"><input type="text" name="name" value="{{ $torrentRow['name'] }}" /></div>
@if ($priceRow !== null)
<x-settings-row layout="grid" :label="\App\Support\Locale::trans('label.torrent.price', [], null)"><input type="number" min="0" name="price" value="{{ $priceRow['value'] }}" placeholder="{{ $priceRow['placeholder'] }}" />&nbsp;&nbsp;{{ $priceRow['help'] }}</x-settings-row>
@endif
<div class="nx-fhead">{{ __('legacy/edit.row_description') }}<span class="text-nxm-danger">*</span></div><div class="nx-fcell"><livewire:bbcode-editor form="edittorrent" text="descr" :content="$descrContent ?? ''" /></div>
@if ($technicalInfoEnabled)
<x-settings-row layout="grid" :label="__('legacy/functions.text_technical_info')"><textarea name="technical_info" rows="8">{{ $torrentRow['technical_info'] ?? '' }}</textarea><br/><b>&middot;</b> {{ __('legacy/functions.text_technical_info_help_text') }} <b><a href="https://mediaarea.net/en/MediaInfo" target='_blank'>{{ __('legacy/functions.text_technical_info_help_link_mediainfo') }}</a></b>{{ __('legacy/functions.text_technical_info_help_text_one_end') }}<br /><b>&middot;</b> {{ __('legacy/functions.text_technical_info_help_text_two') }} <b><a href="https://github.com/UniqProject/BDInfo" target='_blank'>{{ __('legacy/functions.text_technical_info_help_link_bdinfo') }}</a></b>{{ __('legacy/functions.text_technical_info_help_text_two_end') }}</x-settings-row>
@endif
<div class="nx-fhead whitespace-nowrap">{{ __('legacy/edit.row_type') }}<span class="text-nxm-danger">*</span></div><div class="nx-fcell"><select name="type" data-mode='{{ $sectionMode }}'>
@foreach ($cats as $subrow)
<option value="{{ $subrow['id'] }}"@if ($subrow['id'] == $torrentRow['category']) selected="selected"@endif>{{ $subrow['name'] }}</option>
@endforeach
</select></div>
<div class="nx-grouprow {{ $modeClass }}" relation="{{ $modeClass }}"><div class="nx-fhead whitespace-nowrap">{{ __('legacy/edit.row_quality') }}</div><div class="nx-fcell">{{ $taxonomySelect }}</div></div>
{{ $customFieldsHtml }}
{{ $hitAndRunHtml }}
<div class="nx-grouprow {{ $modeClass }}" relation="{{ $modeClass }}"><div class="nx-fhead whitespace-nowrap">{{ __('legacy/functions.text_tags') }}</div><div class="nx-fcell">{{ $tagCheckbox }}</div></div>
@if ($showVisibleCheck || $showAnonymousCheck)
<x-settings-row layout="grid" :label="__('legacy/edit.row_check')">@if ($showVisibleCheck)<input type="hidden" name="visible" value="0" /><label><input id="visible" type="checkbox" name="visible"@if ($visibleChecked) checked="checked"@endif value="1" />{{ __('legacy/edit.checkbox_visible') }}</label>@endif
@if ($showVisibleCheck && $showAnonymousCheck)&nbsp;&nbsp;@endif
@if ($showAnonymousCheck)<input type="hidden" name="anonymous" value="0" /><label><input type="checkbox" name="anonymous"@if ($anonymousChecked) checked="checked"@endif value="1" />{{ __('legacy/edit.checkbox_anonymous_note') }}</label>@endif</x-settings-row>
@endif
@if ($pick !== null && ($pick->promotionOptions !== null || $pick->posStates !== null))
<x-settings-row layout="grid" :label="__('legacy/edit.row_pick')">@include('torrent.edit._pick', ['pick' => $pick])</x-settings-row>
@endif
<div class="nx-ffull text-center"><input id="qr" type="submit" value="{{ __('legacy/edit.submit_edit_it') ?? '' }}" /> <input type="reset" value="{{ __('legacy/edit.submit_revert_changes') ?? '' }}" /></div>
</div>
</form>
@if ($showDeleteForm)
<br /><br />
<form method="post" action="/delete">
<input type="hidden" name="id" value="{{ $torrentId }}" />
@if ($returnto !== '')
<input type="hidden" name="returnto" value="{{ $returnto }}" />
@endif
<div class="nx-fgrid">
<div class="nx-ffull nx-colhead"><b>{{ __('legacy/edit.text_delete_torrent') }}</b> {{ __('legacy/edit.text_reason') }}</div>
<div class="nx-fhead whitespace-nowrap"><input name="reasontype" type="radio" value="1" />&nbsp;{{ __('legacy/edit.radio_dead') }}</div><div class="nx-fcell">{{ __('legacy/edit.text_dead_note') }}</div>
<div class="nx-fhead whitespace-nowrap"><input name="reasontype" type="radio" value="2" />&nbsp;{{ __('legacy/edit.radio_dupe') }}</div><div class="nx-fcell"><input type="text" name="reason[]" /></div>
<div class="nx-fhead whitespace-nowrap"><input name="reasontype" type="radio" value="3" />&nbsp;{{ __('legacy/edit.radio_nuked') }}</div><div class="nx-fcell"><input type="text" name="reason[]" /></div>
<div class="nx-fhead whitespace-nowrap"><input name="reasontype" type="radio" value="4" />&nbsp;{{ __('legacy/edit.radio_rules') }}</div><div class="nx-fcell"><input type="text" name="reason[]" />{{ __('legacy/edit.text_req') }}</div>
<div class="nx-fhead whitespace-nowrap"><input name="reasontype" type="radio" value="5" checked="checked" />&nbsp;{{ __('legacy/edit.radio_other') }}</div><div class="nx-fcell"><input type="text" name="reason[]" />{{ __('legacy/edit.text_req') }}</div>
<div class="nx-ffull text-center"><input type="submit" value="{{ __('legacy/edit.submit_delete_it') ?? '' }}" /></div>
</div>
</form>
@endif
@endif
@endsection

@extends('layouts.legacy_details')

@section('title', $headTitle)

@section('content')
@if (! $canEdit)
<h1 align="center">{{ __('legacy/edit.text_cannot_edit_torrent') ?? '' }}</h1>
<p>@safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml(sprintf(__('legacy/edit.text_cannot_edit_torrent_note'), e($requestUri))))</p>
@else
<form method="post" id="compose" name="edittorrent" action="/takeedit" enctype="multipart/form-data">
<input type="hidden" name="id" value="{{ $torrentId }}" />
@if ($returnto !== '')
<input type="hidden" name="returnto" value="{{ $returnto }}" />
@endif
<div class="nx-fgrid">
<div class="nx-ffull nx-colhead nx-center">{{ $torrentRow['name'] }}</div>
<div class="nx-fhead nx-nowrap">@safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml((__('legacy/edit.row_torrent_name')).'<font color="red">*</font>'))</div><div class="nx-fcell"><input type="text" style="width: 99%;" name="name" value="{{ $torrentRow['name'] }}" /></div>
@if ($priceRowHtml !== null)
<x-settings-row layout="grid" :label="\App\Support\Locale::trans('label.torrent.price', [], null)">@safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml($priceRowHtml))</x-settings-row>
@endif
<div class="nx-fhead">@safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml((__('legacy/edit.row_description')).'<font color="red">*</font>'))</div><div class="nx-fcell">@safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml($bbcodeEditorHtml))</div>
@if ($technicalInfoEnabled)
<x-settings-row layout="grid" :label="__('legacy/functions.text_technical_info')"><textarea name="technical_info" rows="8" style="width: 99%;">{{ $torrentRow['technical_info'] ?? '' }}</textarea><br/>@safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml(__('legacy/functions.text_technical_info_help_text')))</x-settings-row>
@endif
<div class="nx-fhead nx-nowrap">@safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml((__('legacy/edit.row_type')).'<font color="red">*</font>'))</div><div class="nx-fcell">@safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml($typeSelect))</div>
<div class="nx-grouprow {{ $modeClass }}" relation="{{ $modeClass }}"><div class="nx-fhead nx-nowrap">{{ __('legacy/edit.row_quality') }}</div><div class="nx-fcell">@safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml($taxonomySelect))</div></div>
@safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml($customFieldsHtml))
@safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml($hitAndRunHtml))
<div class="nx-grouprow {{ $modeClass }}" relation="{{ $modeClass }}"><div class="nx-fhead nx-nowrap">{{ __('legacy/functions.text_tags') }}</div><div class="nx-fcell">@safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml($tagCheckbox))</div></div>
@if ($checkRowHtml !== '')
<x-settings-row layout="grid" :label="__('legacy/edit.row_check')">@safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml($checkRowHtml))</x-settings-row>
@endif
@if ($pickContentHtml !== '')
<x-settings-row layout="grid" :label="__('legacy/edit.row_pick')">@safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml($pickContentHtml))</x-settings-row>
@endif
<div class="nx-ffull nx-center"><input id="qr" type="submit" value="{{ __('legacy/edit.submit_edit_it') ?? '' }}" /> <input type="reset" value="{{ __('legacy/edit.submit_revert_changes') ?? '' }}" /></div>
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
<div class="nx-ffull nx-colhead">@safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml(__('legacy/edit.text_delete_torrent')))</div>
<div class="nx-fhead nx-nowrap"><input name="reasontype" type="radio" value="1" />&nbsp;{{ __('legacy/edit.radio_dead') }}</div><div class="nx-fcell">{{ __('legacy/edit.text_dead_note') }}</div>
<div class="nx-fhead nx-nowrap"><input name="reasontype" type="radio" value="2" />&nbsp;{{ __('legacy/edit.radio_dupe') }}</div><div class="nx-fcell"><input type="text" style="width: 200px" name="reason[]" /></div>
<div class="nx-fhead nx-nowrap"><input name="reasontype" type="radio" value="3" />&nbsp;{{ __('legacy/edit.radio_nuked') }}</div><div class="nx-fcell"><input type="text" style="width: 200px" name="reason[]" /></div>
<div class="nx-fhead nx-nowrap"><input name="reasontype" type="radio" value="4" />&nbsp;{{ __('legacy/edit.radio_rules') }}</div><div class="nx-fcell"><input type="text" style="width: 200px" name="reason[]" />{{ __('legacy/edit.text_req') }}</div>
<div class="nx-fhead nx-nowrap"><input name="reasontype" type="radio" value="5" checked="checked" />&nbsp;{{ __('legacy/edit.radio_other') }}</div><div class="nx-fcell"><input type="text" style="width: 200px" name="reason[]" />{{ __('legacy/edit.text_req') }}</div>
<div class="nx-ffull nx-center"><input type="submit" style='height: 25px' value="{{ __('legacy/edit.submit_delete_it') ?? '' }}" /></div>
</div>
</form>
@endif
@endif
@endsection

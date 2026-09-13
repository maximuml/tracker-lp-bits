@extends('layouts.legacy_details')

@section('title', $headTitle)

@section('content')
@if (! $canEdit)
<h1 align="center">{{ $lang_edit['text_cannot_edit_torrent'] ?? '' }}</h1>
<p>@safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml(sprintf($lang_edit['text_cannot_edit_torrent_note'] ?? '', e($requestUri))))</p>
@else
<form method="post" id="compose" name="edittorrent" action="/takeedit" enctype="multipart/form-data">
<input type="hidden" name="id" value="{{ $torrentId }}" />
@if ($returnto !== '')
<input type="hidden" name="returnto" value="{{ $returnto }}" />
@endif
<table border="1" cellspacing="0" cellpadding="5" width="97%">
<tr><td class='colhead' colspan='2' align='center'>{{ $torrentRow['name'] }}</td></tr>
<tr><td class="rowhead nowrap" valign="top" align="right">@safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml(($lang_edit['row_torrent_name'] ?? '').'<font color="red">*</font>'))</td><td class="rowfollow" valign="top" align="left"><input type="text" style="width: 99%;" name="name" value="{{ $torrentRow['name'] }}" /></td></tr>
@if ($priceRowHtml !== null)
<x-settings-row :label="\App\Support\Locale::trans('label.torrent.price', [], null)">@safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml($priceRowHtml))</x-settings-row>
@endif
<tr><td class="rowhead">@safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml(($lang_edit['row_description'] ?? '').'<font color="red">*</font>'))</td><td class="rowfollow">@safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml($bbcodeEditorHtml))</td></tr>
@if ($technicalInfoEnabled)
<x-settings-row :label="$lang_functions['text_technical_info'] ?? ''"><textarea name="technical_info" rows="8" style="width: 99%;">{{ $torrentRow['technical_info'] ?? '' }}</textarea><br/>@safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml($lang_functions['text_technical_info_help_text'] ?? ''))</x-settings-row>
@endif
<tr><td class="rowhead nowrap" valign="top" align="right">@safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml(($lang_edit['row_type'] ?? '').'<font color="red">*</font>'))</td><td class="rowfollow" valign="top" align="left">@safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml($typeSelect))</td></tr>
<tr relation="{{ $modeClass }}" class="{{ $modeClass }}"><td class="rowhead nowrap" valign="top" align="right">@safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml($lang_edit['row_quality'] ?? ''))</td><td class="rowfollow" valign="top" align="left">@safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml($taxonomySelect))</td></tr>
@safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml($customFieldsHtml))
@safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml($hitAndRunHtml))
<tr relation="{{ $modeClass }}" class="{{ $modeClass }}"><td class="rowhead nowrap" valign="top" align="right">@safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml($lang_functions['text_tags'] ?? ''))</td><td class="rowfollow" valign="top" align="left">@safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml($tagCheckbox))</td></tr>
@if ($checkRowHtml !== '')
<x-settings-row :label="$lang_edit['row_check'] ?? ''">@safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml($checkRowHtml))</x-settings-row>
@endif
@if ($pickContentHtml !== '')
<x-settings-row :label="$lang_edit['row_pick'] ?? ''">@safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml($pickContentHtml))</x-settings-row>
@endif
<tr><td class="toolbox" colspan="2" align="center"><input id="qr" type="submit" value="{{ $lang_edit['submit_edit_it'] ?? '' }}" /> <input type="reset" value="{{ $lang_edit['submit_revert_changes'] ?? '' }}" /></td></tr>
</table>
</form>
@if ($showDeleteForm)
<br /><br />
<form method="post" action="/delete">
<input type="hidden" name="id" value="{{ $torrentId }}" />
@if ($returnto !== '')
<input type="hidden" name="returnto" value="{{ $returnto }}" />
@endif
<table border="1" cellspacing="0" cellpadding="5">
<tr><td class="colhead" align="left" style='padding-bottom: 3px' colspan="2">@safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml($lang_edit['text_delete_torrent'] ?? ''))</td></tr>
<tr><td class="rowhead nowrap" valign="top" align="right"><input name="reasontype" type="radio" value="1" />&nbsp;@safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml($lang_edit['radio_dead'] ?? ''))</td><td class="rowfollow" valign="top" align="left">@safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml($lang_edit['text_dead_note'] ?? ''))</td></tr>
<tr><td class="rowhead nowrap" valign="top" align="right"><input name="reasontype" type="radio" value="2" />&nbsp;@safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml($lang_edit['radio_dupe'] ?? ''))</td><td class="rowfollow" valign="top" align="left"><input type="text" style="width: 200px" name="reason[]" /></td></tr>
<tr><td class="rowhead nowrap" valign="top" align="right"><input name="reasontype" type="radio" value="3" />&nbsp;@safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml($lang_edit['radio_nuked'] ?? ''))</td><td class="rowfollow" valign="top" align="left"><input type="text" style="width: 200px" name="reason[]" /></td></tr>
<tr><td class="rowhead nowrap" valign="top" align="right"><input name="reasontype" type="radio" value="4" />&nbsp;@safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml($lang_edit['radio_rules'] ?? ''))</td><td class="rowfollow" valign="top" align="left"><input type="text" style="width: 200px" name="reason[]" />@safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml($lang_edit['text_req'] ?? ''))</td></tr>
<tr><td class="rowhead nowrap" valign="top" align="right"><input name="reasontype" type="radio" value="5" checked="checked" />&nbsp;@safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml($lang_edit['radio_other'] ?? ''))</td><td class="rowfollow" valign="top" align="left"><input type="text" style="width: 200px" name="reason[]" />@safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml($lang_edit['text_req'] ?? ''))</td></tr>
<tr><td class="toolbox" colspan="2" align="center"><input type="submit" style='height: 25px' value="{{ $lang_edit['submit_delete_it'] ?? '' }}" /></td></tr>
</table>
</form>
@endif
@endif
@endsection

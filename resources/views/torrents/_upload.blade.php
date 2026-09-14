	<form id="compose" enctype="multipart/form-data" action="/takeupload" method="post" name="upload">
			<p align="center">@safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml($lang_upload['text_red_star_required'] ?? ''))</p>
			<table border="1" cellspacing="0" cellpadding="5" width="97%">
				<tr>
					<td class='colhead' colspan='2' align='center'>
						{{ $lang_upload['text_tracker_url'] ?? '' }}: &nbsp;&nbsp;&nbsp;&nbsp;<b>{{ $trackerUrl }}</b>
						@unless ($torrentDirWritable)
							<br /><br /><b>ATTENTION</b>: Torrent directory isn't writable. Please contact the administrator about this problem!
						@endunless
						@if (empty($max_torrent_size))
							<br /><br /><b>ATTENTION</b>: Max. Torrent Size not set. Please contact the administrator about this problem!
						@endif
					</td>
				</tr>
				<x-settings-row :label="($lang_upload['row_torrent_file'] ?? '').'<font color=red>*</font>'">
					<input type="file" class="file" id="torrent" name="file" />
				</x-settings-row>
				@if (($altname_main ?? '') === 'yes')
					<x-settings-row :label="$lang_upload['row_torrent_name'] ?? ''">
						<b>{{ $lang_upload['text_english_title'] ?? '' }}</b>&nbsp;<input type="text" style="width: 250px;" name="name" />&nbsp;&nbsp;&nbsp;
<b>{{ $lang_upload['text_chinese_title'] ?? '' }}</b>&nbsp;<input type="text" style="width: 250px" name="cnname"><br /><font class="medium">{{ $lang_upload['text_titles_note'] ?? '' }}</font>
					</x-settings-row>
				@else
					<x-settings-row :label="$lang_upload['row_torrent_name'] ?? ''">
						@safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml($nameInputHtml ?? ''))
					</x-settings-row>
				@endif

				@if ($priceCellHtml !== '')
					<x-settings-row :label="$priceLabel">
						@safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml($priceCellHtml ?? ''))
					</x-settings-row>
				@endif

				<tr>
					<td class="rowhead" style='padding: 3px' valign="top">{{ $lang_upload['row_description'] ?? '' }}<font color="red">*</font></td>
					<td class="rowfollow">@safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml($descrEditorHtml ?? ''))</td>
				</tr>

				@if ($enableTechnicalInfo)
					<x-settings-row :label="$lang_functions['text_technical_info'] ?? ''">
						<textarea name="technical_info" rows="8" style="width: 99%;"></textarea><br/>@safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml($lang_functions['text_technical_info_help_text'] ?? ''))
					</x-settings-row>
				@endif

				<x-settings-row :label="($lang_upload['row_type'] ?? '').'<font color=red>*</font>'">
					<select name="type" id="browsecat" data-mode="{{ $browsecatmode }}">
						<option value="0">{{ $lang_upload['select_choose_one'] ?? '' }}</option>
						@foreach ($cats as $row)
							<option value="{{ $row['id'] }}">{{ $row['name'] }}</option>
						@endforeach
					</select>
				</x-settings-row>

				<tbody id="browsecat_section" data-mode="{{ $browsecatmode }}">
					<x-settings-row :label="$lang_upload['row_quality'] ?? ''" :relation="'mode_'.$browsecatmode">
						@safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml($taxonomySelectHtml ?? ''))
					</x-settings-row>
					@safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml($customFieldsHtml ?? ''))
					@safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml($hitAndRunHtml ?? ''))
					<x-settings-row :label="$lang_functions['text_tags'] ?? ''" :relation="'mode_'.$browsecatmode">
						@safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml($tagsHtml ?? ''))
					</x-settings-row>
				</tbody>

				@if (! empty($offerRows))
					<x-settings-row :label="($lang_upload['row_your_offer'] ?? '').(!$uploadFreely ? '<font color=red>*</font>' : '')">
						<select name="offer">
							<option value="0">{{ $lang_upload['select_choose_one'] ?? '' }}</option>
							@foreach ($offerRows as $offerrow)
								<option value="{{ (int) $offerrow['id'] }}">{{ $offerrow['name'] }}</option>
							@endforeach
						</select>@safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml($lang_upload['text_please_select_offer'] ?? ''))
					</x-settings-row>
				@endif

				@if ($pickCellHtml !== '')
					<x-settings-row :label="$lang_edit['row_pick'] ?? ''">
						@safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml($pickCellHtml ?? ''))
					</x-settings-row>
				@endif

				@if ($canBeAnonymous)
					<x-settings-row :label="$lang_upload['row_show_uploader'] ?? ''">
						<input type="checkbox" name="uplver" value="yes" />{{ $lang_upload['checkbox_hide_uploader_note'] ?? '' }}
					</x-settings-row>
				@endif

				<tr><td class="toolbox" align="center" colspan="2"><b>{{ $lang_upload['text_read_rules'] ?? '' }}</b> <input id="qr" type="submit" class="btn" value="{{ $lang_upload['submit_upload'] ?? '' }}" /></td></tr>
		</table>
	</form>
<script src="js/upload.js" type="text/javascript"></script>

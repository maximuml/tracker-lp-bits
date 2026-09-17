	<form id="compose" enctype="multipart/form-data" action="/takeupload" method="post" name="upload">
			<p align="center">@safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml(__('legacy/upload.text_red_star_required')))</p>
			<div class="nx-fgrid">
					<div class="nx-ffull nx-colhead nx-center">
						{{ __('legacy/upload.text_tracker_url') ?? '' }}: &nbsp;&nbsp;&nbsp;&nbsp;<b>{{ $trackerUrl }}</b>
						@unless ($torrentDirWritable)
							<br /><br /><b>ATTENTION</b>: Torrent directory isn't writable. Please contact the administrator about this problem!
						@endunless
						@if (empty($max_torrent_size))
							<br /><br /><b>ATTENTION</b>: Max. Torrent Size not set. Please contact the administrator about this problem!
						@endif
					</div>
				<x-settings-row layout="grid" :label="\App\Support\Html\SafeHtml::fromTrustedHtml((__('legacy/upload.row_torrent_file')).'<font color=red>*</font>')">
					<input type="file" class="file" id="torrent" name="file" />
				</x-settings-row>
				@if (($altname_main ?? '') === 'yes')
					<x-settings-row layout="grid" :label="__('legacy/upload.row_torrent_name')">
						<b>{{ __('legacy/upload.text_english_title') ?? '' }}</b>&nbsp;<input type="text" style="width: 250px;" name="name" />&nbsp;&nbsp;&nbsp;
<b>{{ __('legacy/upload.text_chinese_title') ?? '' }}</b>&nbsp;<input type="text" style="width: 250px" name="cnname"><br /><font class="medium">{{ __('legacy/upload.text_titles_note') ?? '' }}</font>
					</x-settings-row>
				@else
					<x-settings-row layout="grid" :label="__('legacy/upload.row_torrent_name')">
						@safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml($nameInputHtml ?? ''))
					</x-settings-row>
				@endif

				@if ($priceCellHtml !== '')
					<x-settings-row layout="grid" :label="$priceLabel">
						@safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml($priceCellHtml ?? ''))
					</x-settings-row>
				@endif

				<div class="nx-fhead">{{ __('legacy/upload.row_description') ?? '' }}<font color="red">*</font></div>
				<div class="nx-fcell">@safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml($descrEditorHtml ?? ''))</div>

				@if ($enableTechnicalInfo)
					<x-settings-row layout="grid" :label="__('legacy/functions.text_technical_info')">
						<textarea name="technical_info" rows="8" style="width: 99%;"></textarea><br/>@safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml(__('legacy/functions.text_technical_info_help_text')))
					</x-settings-row>
				@endif

				<x-settings-row layout="grid" :label="\App\Support\Html\SafeHtml::fromTrustedHtml((__('legacy/upload.row_type')).'<font color=red>*</font>')">
					<select name="type" id="browsecat" data-mode="{{ $browsecatmode }}">
						<option value="0">{{ __('legacy/upload.select_choose_one') ?? '' }}</option>
						@foreach ($cats as $row)
							<option value="{{ $row['id'] }}">{{ $row['name'] }}</option>
						@endforeach
					</select>
				</x-settings-row>

				<div class="nx-grouprow" id="browsecat_section" data-mode="{{ $browsecatmode }}">
					<x-settings-row layout="grid" :label="__('legacy/upload.row_quality')" :relation="'mode_'.$browsecatmode">
						@safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml($taxonomySelectHtml ?? ''))
					</x-settings-row>
					@safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml($customFieldsHtml ?? ''))
					@safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml($hitAndRunHtml ?? ''))
					<x-settings-row layout="grid" :label="__('legacy/functions.text_tags')" :relation="'mode_'.$browsecatmode">
						@safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml($tagsHtml ?? ''))
					</x-settings-row>
				</div>

				@if (! empty($offerRows))
					<x-settings-row layout="grid" :label="\App\Support\Html\SafeHtml::fromTrustedHtml((__('legacy/upload.row_your_offer')).(!$uploadFreely ? '<font color=red>*</font>' : ''))">
						<select name="offer">
							<option value="0">{{ __('legacy/upload.select_choose_one') ?? '' }}</option>
							@foreach ($offerRows as $offerrow)
								<option value="{{ (int) $offerrow['id'] }}">{{ $offerrow['name'] }}</option>
							@endforeach
						</select>@safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml(__('legacy/upload.text_please_select_offer')))
					</x-settings-row>
				@endif

				@if ($pickCellHtml !== '')
					<x-settings-row layout="grid" :label="__('legacy/edit.row_pick')">
						@safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml($pickCellHtml ?? ''))
					</x-settings-row>
				@endif

				@if ($canBeAnonymous)
					<x-settings-row layout="grid" :label="__('legacy/upload.row_show_uploader')">
						<input type="checkbox" name="uplver" value="yes" />{{ __('legacy/upload.checkbox_hide_uploader_note') ?? '' }}
					</x-settings-row>
				@endif

				<div class="nx-ffull nx-center"><b>{{ __('legacy/upload.text_read_rules') ?? '' }}</b> <input id="qr" type="submit" class="btn" value="{{ __('legacy/upload.submit_upload') ?? '' }}" /></div>
		</div>
	</form>
<script src="js/upload.js" type="text/javascript"></script>

	<form id="compose" enctype="multipart/form-data" action="/takeupload" method="post" name="upload">
			@csrf
			<p class="nx-center">{{ __('legacy/upload.text_red_star_required') }}<span class="nx-color-red">*</span>{{ __('legacy/upload.text_red_star_required_end') }}</p>
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
				@if (count($uploadErrorList) > 0)
					<div class="nx-ffull">
						<x-alert type="error" :title="__('legacy/upload.error_summary')">
							<ul>
								@foreach ($uploadErrorList as $uploadError)
									<li>@if ($uploadError['anchor'] !== null)<a href="{{ $uploadError['anchor'] }}">{{ $uploadError['message'] }}</a>@else{{ $uploadError['message'] }}@endif</li>
								@endforeach
							</ul>
						</x-alert>
					</div>
				@endif
				<x-settings-row layout="grid" :label="\App\Support\Html\SafeHtml::fromTrustedHtml((__('legacy/upload.row_torrent_file')).'<span class=nx-color-red>*</span>')">
					<input type="file" class="file" id="torrent" name="file" required @error('file') aria-invalid="true" aria-describedby="file-error"@enderror />
					@error('file')<div class="nx-field__error" id="file-error" role="alert">{{ $message }}</div>@enderror
					@if ($errors->any())
						<div class="nx-field__help">{{ __('legacy/upload.reselect_file_note') }}</div>
					@endif
				</x-settings-row>
				@if (($altname_main ?? '') === 'yes')
					<x-settings-row layout="grid" :label="__('legacy/upload.row_torrent_name')">
						<b>{{ __('legacy/upload.text_english_title') ?? '' }}</b>&nbsp;<input type="text" id="name" name="name" value="{{ old('name') }}"@error('name') aria-invalid="true" aria-describedby="name-error"@enderror />&nbsp;&nbsp;
<b>{{ __('legacy/upload.text_chinese_title') ?? '' }}</b>&nbsp;<input type="text" id="cnname" name="cnname" value="{{ old('cnname') }}"@error('cnname') aria-invalid="true" aria-describedby="cnname-error"@enderror><br /><span class="medium">{{ __('legacy/upload.text_titles_note') ?? '' }}</span>
						@error('name')<div class="nx-field__error" id="name-error" role="alert">{{ $message }}</div>@enderror
						@error('cnname')<div class="nx-field__error" id="cnname-error" role="alert">{{ $message }}</div>@enderror
					</x-settings-row>
				@else
					<x-settings-row layout="grid" :label="__('legacy/upload.row_torrent_name')">
						{{ $nameInputHtml ?? '' }}
						@error('name')<div class="nx-field__error" id="name-error" role="alert">{{ $message }}</div>@enderror
					</x-settings-row>
				@endif

				@if ($priceCellHtml !== '')
					<x-settings-row layout="grid" :label="$priceLabel">
						{{ $priceCellHtml ?? '' }}
						@error('price')<div class="nx-field__error" id="price-error" role="alert">{{ $message }}</div>@enderror
					</x-settings-row>
				@endif

				<div class="nx-fhead">{{ __('legacy/upload.row_description') ?? '' }}<span class="nx-color-red">*</span></div>
				<div class="nx-fcell">{{ $descrEditorHtml ?? '' }}@error('descr')<div class="nx-field__error" id="descr-error" role="alert">{{ $message }}</div>@enderror</div>

				@if ($enableTechnicalInfo)
					<x-settings-row layout="grid" :label="__('legacy/functions.text_technical_info')">
						<textarea name="technical_info" id="technical_info" rows="8"@error('technical_info') aria-invalid="true" aria-describedby="technical_info-error"@enderror>{{ old('technical_info') }}</textarea><br/><b>&middot;</b> {{ __('legacy/functions.text_technical_info_help_text') }} <b><a href="https://mediaarea.net/en/MediaInfo" target='_blank'>{{ __('legacy/functions.text_technical_info_help_link_mediainfo') }}</a></b>{{ __('legacy/functions.text_technical_info_help_text_one_end') }}<br /><b>&middot;</b> {{ __('legacy/functions.text_technical_info_help_text_two') }} <b><a href="https://github.com/UniqProject/BDInfo" target='_blank'>{{ __('legacy/functions.text_technical_info_help_link_bdinfo') }}</a></b>{{ __('legacy/functions.text_technical_info_help_text_two_end') }}
						@error('technical_info')<div class="nx-field__error" id="technical_info-error" role="alert">{{ $message }}</div>@enderror
					</x-settings-row>
				@endif

				<x-settings-row layout="grid" :label="\App\Support\Html\SafeHtml::fromTrustedHtml((__('legacy/upload.row_type')).'<span class=nx-color-red>*</span>')">
					<select name="type" id="browsecat" data-mode="{{ $browsecatmode }}" required @error('type') aria-invalid="true" aria-describedby="type-error"@enderror>
						<option value="0">{{ __('legacy/upload.select_choose_one') ?? '' }}</option>
						@foreach ($cats as $row)
							<option value="{{ $row['id'] }}"@selected((string) old('type', '') === (string) $row['id'])>{{ $row['name'] }}</option>
						@endforeach
					</select>
					@error('type')<div class="nx-field__error" id="type-error" role="alert">{{ $message }}</div>@enderror
				</x-settings-row>

				<div class="nx-grouprow" id="browsecat_section" data-mode="{{ $browsecatmode }}">
					<x-settings-row layout="grid" :label="__('legacy/upload.row_quality')" :relation="'mode_'.$browsecatmode">
						{{ $taxonomySelectHtml ?? '' }}
						@foreach ($errors->keys() as $errorKey)
							@if (str_ends_with((string) $errorKey, '_sel'))
								@foreach ($errors->get($errorKey) as $errorMessage)
									<div class="nx-field__error" role="alert">{{ $errorMessage }}</div>
								@endforeach
							@endif
						@endforeach
					</x-settings-row>
					{{ $customFieldsHtml ?? '' }}
					{{ $hitAndRunHtml ?? '' }}
					@error('hr')<div class="nx-ffull"><div class="nx-field__error" id="hr-error" role="alert">{{ $message }}</div></div>@enderror
					<x-settings-row layout="grid" :label="__('legacy/functions.text_tags')" :relation="'mode_'.$browsecatmode">
						{{ $tagsHtml ?? '' }}
						@error('tags')<div class="nx-field__error" id="tags-error" role="alert">{{ $message }}</div>@enderror
					</x-settings-row>
				</div>

				@if (! empty($offerRows))
					<x-settings-row layout="grid" :label="\App\Support\Html\SafeHtml::fromTrustedHtml((__('legacy/upload.row_your_offer')).(!$uploadFreely ? '<span class=nx-color-red>*</span>' : ''))">
						<select name="offer" id="offer"@error('offer') aria-invalid="true" aria-describedby="offer-error"@enderror>
							<option value="0">{{ __('legacy/upload.select_choose_one') ?? '' }}</option>
							@foreach ($offerRows as $offerrow)
								<option value="{{ (int) $offerrow['id'] }}"@selected((string) old('offer', '0') === (string) $offerrow['id'])>{{ $offerrow['name'] }}</option>
							@endforeach
						</select>&nbsp;&nbsp;{{ __('legacy/upload.text_please_select_offer') }}
						@error('offer')<div class="nx-field__error" id="offer-error" role="alert">{{ $message }}</div>@enderror
					</x-settings-row>
				@endif

				@if ($pickCellHtml !== '')
					<x-settings-row layout="grid" :label="__('legacy/edit.row_pick')">
						{{ $pickCellHtml ?? '' }}
						@error('pos_state')<div class="nx-field__error" id="pos_state-error" role="alert">{{ $message }}</div>@enderror
						@error('pos_state_until')<div class="nx-field__error" id="pos_state_until-error" role="alert">{{ $message }}</div>@enderror
					</x-settings-row>
				@endif

				@if ($canBeAnonymous)
					<x-settings-row layout="grid" :label="__('legacy/upload.row_show_uploader')">
						<input type="checkbox" id="uplver" name="uplver" value="yes"@checked(old('uplver') === 'yes')@error('uplver') aria-invalid="true" aria-describedby="uplver-error"@enderror />{{ __('legacy/upload.checkbox_hide_uploader_note') ?? '' }}
						@error('uplver')<div class="nx-field__error" id="uplver-error" role="alert">{{ $message }}</div>@enderror
					</x-settings-row>
				@endif

				@error('upload')<div class="nx-ffull"><div class="nx-field__error" role="alert">{{ $message }}</div></div>@enderror

				<div class="nx-ffull nx-center"><b>{{ __('legacy/upload.text_read_rules') ?? '' }}</b> <input id="qr" type="submit" class="btn" value="{{ __('legacy/upload.submit_upload') ?? '' }}" /></div>
		</div>
	</form>
<script src="js/upload.js" type="text/javascript"></script>

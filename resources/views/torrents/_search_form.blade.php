{{-- Modern torrents search panel (Variant A, ADR 0014). Replaces SearchBox::buildCategoryTable — data arrives in $panelVm. --}}
<form method="get" name="searchbox" action="{{ request()->getPathInfo() }}" class="nxm-searchpanel">
	<livewire:search-panel-toggle>
		<fieldset class="nxm-fieldset">
			<legend>{{ $panelVm->categoryLabel }}</legend>
			@foreach ($panelVm->categoryRows as $cells)
			<div class="nxm-catrow">
				@foreach ($cells as $cell)
					@if ($cell['selectAll'])
					<span class="nxm-catcell"><input name="{{ $cell['checkPrefix'] }}_check" value="{{ $panelVm->selectAllLabel }}" class="btn medium" type="button" data-setchecked="{{ $cell['checkPrefix'] }}" data-setchecked-ctrl="{{ $cell['checkPrefix'] }}_check" data-checkall="{{ $panelVm->selectAllLabel }}" data-uncheckall="{{ $panelVm->unselectAllLabel }}"></span>
					@else
					<span class="nxm-catcell"><input type="checkbox" id="{{ $cell['checkboxName'] }}" name="{{ $cell['checkboxName'] }}" value="1" aria-label="{{ $cell['name'] !== '' ? $cell['name'] : '#'.$cell['id'] }}"@if ($cell['checked']) checked @endif /><a href="{{ $cell['href'] }}" aria-label="{{ $cell['name'] !== '' ? $cell['name'] : '#'.$cell['id'] }}"><img src="pic/cattrans.gif" class="{{ $cell['iconClass'] }}" alt="" aria-hidden="true" title="{{ $cell['name'] }}"@if ($cell['iconStyle'] !== '')@endif /></a></span>
					@endif
				@endforeach
			</div>
			@endforeach
		</fieldset>
		@foreach ($panelVm->taxonomySections as $section)
		<fieldset class="nxm-fieldset">
			<legend>{{ $section['label'] }}</legend>
			@foreach ($section['rows'] as $cells)
			<div class="nxm-catrow">
				@foreach ($cells as $cell)
					@if ($cell['selectAll'])
					<span class="nxm-catcell"><input name="{{ $cell['checkPrefix'] }}_check" value="{{ $panelVm->selectAllLabel }}" class="btn medium" type="button" data-setchecked="{{ $cell['checkPrefix'] }}" data-setchecked-ctrl="{{ $cell['checkPrefix'] }}_check" data-checkall="{{ $panelVm->selectAllLabel }}" data-uncheckall="{{ $panelVm->unselectAllLabel }}"></span>
					@else
					<span class="nxm-catcell"><label><input type="checkbox" id="{{ $cell['checkboxName'] }}" name="{{ $cell['checkboxName'] }}" value="1"@if ($cell['checked']) checked @endif /><a href="{{ $cell['href'] }}">{{ $cell['name'] }}</a></label></span>
					@endif
				@endforeach
			</div>
			@endforeach
		</fieldset>
		@endforeach

		<div class="nxm-filters">
			<div class="nxm-field">
				<label for="f-incldead">{{ __('torrents.text_show_dead_active')}}</label>
				<select class="med" id="f-incldead" name="incldead">
					<option value="0">{{ __('torrents.select_including_dead')}}</option>
					<option value="1"@if ($include_dead == 1) selected="selected"@endif>{{ __('torrents.select_active')}}</option>
					<option value="2"@if ($include_dead == 2) selected="selected"@endif>{{ __('torrents.select_dead')}}</option>
				</select>
			</div>
			<div class="nxm-field">
				<label for="f-spstate">{{ __('torrents.text_show_special_torrents')}}</label>
				<select class="med" id="f-spstate" name="spstate">
					<option value="0">{{ __('torrents.select_all')}}</option>
					@foreach ($panelVm->promotionOptions as $pId => $pLabel)
					<option value="{{ $pId }}"@if ((int) $special_state === $pId) selected="selected"@endif>{{ $pLabel }}</option>
					@endforeach
				</select>
			</div>
			<div class="nxm-field">
				<label for="f-inclbookmarked">{{ __('torrents.text_show_bookmarked')}}</label>
				<select class="med" id="f-inclbookmarked" name="inclbookmarked">
					<option value="0">{{ __('torrents.select_all')}}</option>
					<option value="1"@if ($inclbookmarked == 1) selected="selected"@endif>{{ __('torrents.select_bookmarked')}}</option>
					<option value="2"@if ($inclbookmarked == 2) selected="selected"@endif>{{ __('torrents.select_bookmarked_exclude')}}</option>
				</select>
			</div>
			@if ($showApprovalStatusFilter)
			<div class="nxm-field">
				<label for="f-approval">{{ __('torrents.text_approval_status')}}</label>
				<select class="med" id="f-approval" name="approval_status">
					<option value="">{{ __('torrents.select_all')}}</option>
					@foreach (\App\Models\Torrent::listApprovalStatus(true) as $key => $value)
						<option value="{{ $key }}"@if (isset($approvalStatus) && (string) $approvalStatus === (string) $key) selected="selected"@endif>{{ $value }}</option>
					@endforeach
				</select>
			</div>
			@endif
			<div class="nxm-field">
				<span class="nxm-field__label">{{ __('torrents.size_range')}}</span>
				<span class="nxm-range"><input type="number" min="1" name="size_begin" aria-label="{{ __('torrents.size_range') }} — {{ __('torrents.range_from') }}" value="{{ $filterInput['size_begin'] ?? '' }}"/> ~ <input type="number" min="1" name="size_end" aria-label="{{ __('torrents.size_range') }} — {{ __('torrents.range_to') }}" value="{{ $filterInput['size_end'] ?? '' }}"/></span>
			</div>
			<div class="nxm-field">
				<span class="nxm-field__label">{{ __('torrents.seeders_range')}}</span>
				<span class="nxm-range"><input type="number" min="1" name="seeders_begin" aria-label="{{ __('torrents.seeders_range') }} — {{ __('torrents.range_from') }}" value="{{ $filterInput['seeders_begin'] ?? '' }}"/> ~ <input type="number" min="1" name="seeders_end" aria-label="{{ __('torrents.seeders_range') }} — {{ __('torrents.range_to') }}" value="{{ $filterInput['seeders_end'] ?? '' }}"/></span>
			</div>
			<div class="nxm-field">
				<span class="nxm-field__label">{{ __('torrents.leechers_range')}}</span>
				<span class="nxm-range"><input type="number" min="1" name="leechers_begin" aria-label="{{ __('torrents.leechers_range') }} — {{ __('torrents.range_from') }}" value="{{ $filterInput['leechers_begin'] ?? '' }}"/> ~ <input type="number" min="1" name="leechers_end" aria-label="{{ __('torrents.leechers_range') }} — {{ __('torrents.range_to') }}" value="{{ $filterInput['leechers_end'] ?? '' }}"/></span>
			</div>
			<div class="nxm-field">
				<span class="nxm-field__label">{{ __('torrents.times_completed_range')}}</span>
				<span class="nxm-range"><input type="number" min="1" name="times_completed_begin" aria-label="{{ __('torrents.times_completed_range') }} — {{ __('torrents.range_from') }}" value="{{ $filterInput['times_completed_begin'] ?? '' }}"/> ~ <input type="number" min="1" name="times_completed_end" aria-label="{{ __('torrents.times_completed_range') }} — {{ __('torrents.range_to') }}" value="{{ $filterInput['times_completed_end'] ?? '' }}"/></span>
			</div>
			<div class="nxm-field">
				<span class="nxm-field__label">{{ __('torrents.added_range')}}</span>
				<span class="nxm-range"><x-datetime-input name="added_begin" :value="$filterInput['added_begin'] ?? ''" :aria-label="__('torrents.added_range').' — '.__('torrents.range_from')" /> ~ <x-datetime-input name="added_end" :value="$filterInput['added_end'] ?? ''" :aria-label="__('torrents.added_range').' — '.__('torrents.range_to')" /></span>
			</div>
		</div>

		<div class="nxm-searchline">
			<label for="searchinput">{{ __('torrents.text_search')}}</label>
			<input id="searchinput" name="search" type="text" value="{{ $searchstr_ori }}" autocomplete="off" />
			<script src="{{ \App\Support\AssetAppender::versionedSrc('js/meili_autocomplete.js') }}" type="text/javascript"></script>
			<span>{{ __('torrents.text_in')}}</span>
			<select name="search_area" aria-label="{{ __('torrents.label_search_area') }}">
				<option value="0">{{ __('torrents.select_title')}}</option>
				<option value="1"@if (($filterInput['search_area'] ?? null) == 1) selected="selected"@endif>{{ __('torrents.select_description')}}</option>
				<option value="3"@if (($filterInput['search_area'] ?? null) == 3) selected="selected"@endif>{{ __('torrents.select_uploader')}}</option>
			</select>
			<span>{{ __('torrents.text_with')}}</span>
			<select name="search_mode" aria-label="{{ __('torrents.label_search_mode') }}">
				@foreach ($panelVm->searchModes as $mKey => $mLabel)
				<option value="{{ $mKey }}"@if ((string) ($filterInput['search_mode'] ?? \App\Models\SearchBox::getDefaultSearchMode()) === (string) $mKey) selected="selected"@endif>{{ $mLabel }}</option>
				@endforeach
			</select>
			<span>{{ __('torrents.text_mode')}}</span>
		</div>
		@if ($panelVm->hotSearches !== [])
		<div class="nxm-hotsearches">
			@foreach ($panelVm->hotSearches as $kw)
			<a href="/web/torrents?search={{ rawurlencode($kw) }}&amp;notnewword=1"><u>{{ $kw }}</u></a>
			@endforeach
		</div>
		@endif
		@if ($allTags->isNotEmpty())
		<div class="nxm-tags">
			@foreach ($allTags as $tag)
			<a href="/web/torrents?tag_id={{ $tag->id }}"><span class="nx-tag" title="{{ $tag->description }}">{{ $tag->name }}</span></a>
			@endforeach
		</div>
		@endif
		<div class="nxm-searchpanel__submit"><input type="submit" class="btn" value="{{ __('torrents.submit_go')}}" /></div>
	</livewire:search-panel-toggle>
</form>

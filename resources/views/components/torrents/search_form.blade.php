{{-- Modern torrents search panel (Variant A, ADR 0014). --}}
<form method="get" name="searchbox" action="{{ request()->getPathInfo() }}" class="nxm-searchpanel">
	@if ($panelVm->sortValue !== null && $panelVm->sortType !== null)
	<input type="hidden" name="sort" value="{{ $panelVm->sortValue }}" />
	<input type="hidden" name="type" value="{{ $panelVm->sortType }}" />
	@endif
	<div class="nxm-searchline">
		<label for="searchinput">{{ __('torrents.text_search')}}</label>
		<input id="searchinput" name="search" type="text" value="{{ $searchstr_ori }}" autocomplete="off" />
		<script src="{{ \App\Support\AssetAppender::versionedSrc('js/meili_autocomplete.js') }}" type="text/javascript"></script>
		<select name="search_area" aria-label="{{ __('torrents.label_search_area') }}">
			<option value="0">{{ __('torrents.select_title')}}</option>
			<option value="1"@if (($filterInput['search_area'] ?? null) == 1) selected="selected"@endif>{{ __('torrents.select_description')}}</option>
			<option value="3"@if (($filterInput['search_area'] ?? null) == 3) selected="selected"@endif>{{ __('torrents.select_uploader')}}</option>
		</select>
		<button type="submit" class="nx-btn nx-btn--primary">{{ __('torrents.search_button') }}</button>
	</div>

	<fieldset class="nxm-fieldset nxm-category">
		<legend>{{ $panelVm->categoryLabel }}</legend>
		@foreach ($panelVm->categoryRows as $cells)
		<div class="nxm-chiprow">
			@foreach ($cells as $cell)
				@if ($cell['selectAll'])
				<input type="button" name="{{ $cell['checkPrefix'] }}_check" value="{{ __('torrents.select_all_short') }}" class="nx-btn nx-btn--ghost nxm-filter-all" data-autosubmit data-setchecked="{{ $cell['checkPrefix'] }}" data-setchecked-ctrl="{{ $cell['checkPrefix'] }}_check" data-checkall="{{ __('torrents.select_all_short') }}" data-uncheckall="{{ __('torrents.unselect_all_short') }}" />
				@else
				<label class="nx-chip"><input type="checkbox" id="{{ $cell['checkboxName'] }}" name="{{ $cell['checkboxName'] }}" value="1" class="nx-chip__input" data-autosubmit @checked($cell['checked']) />{{ $cell['name'] !== '' ? $cell['name'] : '#'.$cell['id'] }}</label>
				@endif
			@endforeach
		</div>
		@endforeach
	</fieldset>

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

	<details class="nxm-filters" id="ksearchboxmain" @if ($panelVm->advancedFilterCount > 0) open @endif>
		<summary class="nxm-filters__summary">
			<span aria-hidden="true">{{ __('torrents.filters') }}</span>
			@if ($panelVm->advancedFilterCount > 0)
			<span class="nxm-filters__count" aria-hidden="true">{{ $panelVm->advancedFilterCount }}</span>
			<span class="nx-sr-only">{{ __('torrents.filters_active', ['count' => $panelVm->advancedFilterCount]) }}</span>
			@else
			<span class="nx-sr-only">{{ __('torrents.filters') }}</span>
			@endif
		</summary>
		<div class="nxm-filters__content">
			<div class="nxm-filters__grid">
				<div class="nxm-filters__taxonomy">
					@foreach ($panelVm->taxonomySections as $section)
					<fieldset class="nxm-fieldset">
						<legend>{{ $section['label'] }}</legend>
						@foreach ($section['rows'] as $cells)
						<div class="nxm-chiprow">
							@foreach ($cells as $cell)
								@if ($cell['selectAll'])
								<input type="button" name="{{ $cell['checkPrefix'] }}_check" value="{{ __('torrents.select_all_short') }}" class="nx-btn nx-btn--ghost nxm-filter-all" data-setchecked="{{ $cell['checkPrefix'] }}" data-setchecked-ctrl="{{ $cell['checkPrefix'] }}_check" data-checkall="{{ __('torrents.select_all_short') }}" data-uncheckall="{{ __('torrents.unselect_all_short') }}" />
								@else
								<label class="nx-chip"><input type="checkbox" id="{{ $cell['checkboxName'] }}" name="{{ $cell['checkboxName'] }}" value="1" class="nx-chip__input nxm-taxonomy-chip__input" @checked($cell['checked']) />{{ $cell['name'] !== '' ? $cell['name'] : '#'.$cell['id'] }}</label>
								@endif
							@endforeach
						</div>
						@endforeach
					</fieldset>
					@endforeach
				</div>
				<div class="nxm-filters__controls">
					<fieldset class="nxm-filter-group">
						<legend>{{ __('torrents.status') }}</legend>
						<div class="nxm-status-grid">
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
								<label for="f-search-mode">{{ __('torrents.label_search_mode') }}</label>
								<select id="f-search-mode" name="search_mode">
									@foreach ($panelVm->searchModes as $mKey => $mLabel)
									<option value="{{ $mKey }}"@if ((string) ($filterInput['search_mode'] ?? \App\Models\SearchBox::getDefaultSearchMode()) === (string) $mKey) selected="selected"@endif>{{ $mLabel }}</option>
									@endforeach
								</select>
							</div>
						</div>
					</fieldset>
					<fieldset class="nxm-filter-group">
						<legend>{{ __('torrents.ranges') }}</legend>
						<div class="nxm-range-grid">
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
							<div class="nxm-field nxm-range-field--added">
								<span class="nxm-field__label">{{ __('torrents.added_range')}}</span>
								<span class="nxm-range"><x-datetime-input name="added_begin" :value="$filterInput['added_begin'] ?? ''" :aria-label="__('torrents.added_range').' — '.__('torrents.range_from')" /> ~ <x-datetime-input name="added_end" :value="$filterInput['added_end'] ?? ''" :aria-label="__('torrents.added_range').' — '.__('torrents.range_to')" /></span>
							</div>
						</div>
					</fieldset>
				</div>
			</div>
			<div class="nxm-filters__footer">
				<a href="/web/torrents">{{ __('torrents.reset_filters') }}</a>
				<button type="submit" class="nx-btn nx-btn--primary">{{ __('torrents.apply_filters') }}</button>
			</div>
		</div>
	</details>
</form>

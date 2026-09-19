{{-- Modern torrents search panel (Variant A, ADR 0014). Replaces SearchBox::buildCategoryTable — data arrives in $panelVm. --}}
<form method="get" name="searchbox" action="?" class="nxm-searchpanel">
	<div class="nxm-searchpanel__toggle"><a href="#" data-klappe="searchboxmain"><img class="plus" src="pic/trans.gif" id="picsearchboxmain" alt="Show/Hide" />{{ __('legacy/torrents.text_search_box')}}</a></div>
	<div id="ksearchboxmain" class="nx-hidden nxm-searchpanel__body">
		<fieldset class="nxm-fieldset">
			<legend>{{ $panelVm->categoryLabel }}</legend>
			@foreach ($panelVm->categoryRows as $cells)
			<div class="nxm-catrow">
				@foreach ($cells as $cell)
					@if ($cell['selectAll'])
					<span class="nxm-catcell"><input name="{{ $cell['checkPrefix'] }}_check" value="{{ $panelVm->selectAllLabel }}" class="btn medium" type="button" data-setchecked="{{ $cell['checkPrefix'] }}" data-setchecked-ctrl="{{ $cell['checkPrefix'] }}_check" data-checkall="{{ $panelVm->selectAllLabel }}" data-uncheckall="{{ $panelVm->unselectAllLabel }}"></span>
					@else
					<span class="nxm-catcell"><input type="checkbox" id="{{ $cell['checkboxName'] }}" name="{{ $cell['checkboxName'] }}" value="1"@if ($cell['checked']) checked @endif /><a href="{{ $cell['href'] }}"><img src="pic/cattrans.gif" class="{{ $cell['iconClass'] }}" alt="{{ $cell['name'] }}" title="{{ $cell['name'] }}"@if ($cell['iconStyle'] !== '')@endif /></a></span>
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
				<label for="f-incldead">{{ __('legacy/torrents.text_show_dead_active')}}</label>
				<select class="med" id="f-incldead" name="incldead">
					<option value="0">{{ __('legacy/torrents.select_including_dead')}}</option>
					<option value="1"@if ($include_dead == 1) selected="selected"@endif>{{ __('legacy/torrents.select_active')}}</option>
					<option value="2"@if ($include_dead == 2) selected="selected"@endif>{{ __('legacy/torrents.select_dead')}}</option>
				</select>
			</div>
			<div class="nxm-field">
				<label for="f-spstate">{{ __('legacy/torrents.text_show_special_torrents')}}</label>
				<select class="med" id="f-spstate" name="spstate">
					<option value="0">{{ __('legacy/torrents.select_all')}}</option>
					@foreach ($panelVm->promotionOptions as $pId => $pLabel)
					<option value="{{ $pId }}"@if ((int) $special_state === $pId) selected="selected"@endif>{{ $pLabel }}</option>
					@endforeach
				</select>
			</div>
			<div class="nxm-field">
				<label for="f-inclbookmarked">{{ __('legacy/torrents.text_show_bookmarked')}}</label>
				<select class="med" id="f-inclbookmarked" name="inclbookmarked">
					<option value="0">{{ __('legacy/torrents.select_all')}}</option>
					<option value="1"@if ($inclbookmarked == 1) selected="selected"@endif>{{ __('legacy/torrents.select_bookmarked')}}</option>
					<option value="2"@if ($inclbookmarked == 2) selected="selected"@endif>{{ __('legacy/torrents.select_bookmarked_exclude')}}</option>
				</select>
			</div>
			@if ($showApprovalStatusFilter)
			<div class="nxm-field">
				<label for="f-approval">{{ __('legacy/torrents.text_approval_status')}}</label>
				<select class="med" id="f-approval" name="approval_status">
					<option value="">{{ __('legacy/torrents.select_all')}}</option>
					@foreach (\App\Models\Torrent::listApprovalStatus(true) as $key => $value)
						<option value="{{ $key }}"@if (isset($approvalStatus) && (string) $approvalStatus === (string) $key) selected="selected"@endif>{{ $value }}</option>
					@endforeach
				</select>
			</div>
			@endif
			<div class="nxm-field">
				<label>{{ __('legacy/torrents.size_range')}}</label>
				<span class="nxm-range"><input type="number" min="1" name="size_begin" value="{{ $filterInput['size_begin'] ?? '' }}"/> ~ <input type="number" min="1" name="size_end" value="{{ $filterInput['size_end'] ?? '' }}"/></span>
			</div>
			<div class="nxm-field">
				<label>{{ __('legacy/torrents.seeders_range')}}</label>
				<span class="nxm-range"><input type="number" min="1" name="seeders_begin" value="{{ $filterInput['seeders_begin'] ?? '' }}"/> ~ <input type="number" min="1" name="seeders_end" value="{{ $filterInput['seeders_end'] ?? '' }}"/></span>
			</div>
			<div class="nxm-field">
				<label>{{ __('legacy/torrents.leechers_range')}}</label>
				<span class="nxm-range"><input type="number" min="1" name="leechers_begin" value="{{ $filterInput['leechers_begin'] ?? '' }}"/> ~ <input type="number" min="1" name="leechers_end" value="{{ $filterInput['leechers_end'] ?? '' }}"/></span>
			</div>
			<div class="nxm-field">
				<label>{{ __('legacy/torrents.times_completed_range')}}</label>
				<span class="nxm-range"><input type="number" min="1" name="times_completed_begin" value="{{ $filterInput['times_completed_begin'] ?? '' }}"/> ~ <input type="number" min="1" name="times_completed_end" value="{{ $filterInput['times_completed_end'] ?? '' }}"/></span>
			</div>
			<div class="nxm-field">
				<label>{{ __('legacy/torrents.added_range')}}</label>
				<span class="nxm-range"><x-datetime-input name="added_begin" :value="$filterInput['added_begin'] ?? ''" /> ~ <x-datetime-input name="added_end" :value="$filterInput['added_end'] ?? ''" /></span>
			</div>
		</div>

		<div class="nxm-searchline">
			<label for="searchinput">{{ __('legacy/torrents.text_search')}}</label>
			<input id="searchinput" name="search" type="text" value="{{ $searchstr_ori }}" autocomplete="off" />
			<script src="js/meili_autocomplete.js" type="text/javascript"></script>
			<span>{{ __('legacy/torrents.text_in')}}</span>
			<select name="search_area">
				<option value="0">{{ __('legacy/torrents.select_title')}}</option>
				<option value="1"@if (($filterInput['search_area'] ?? null) == 1) selected="selected"@endif>{{ __('legacy/torrents.select_description')}}</option>
				<option value="3"@if (($filterInput['search_area'] ?? null) == 3) selected="selected"@endif>{{ __('legacy/torrents.select_uploader')}}</option>
			</select>
			<span>{{ __('legacy/torrents.text_with')}}</span>
			<select name="search_mode">
				@foreach ($panelVm->searchModes as $mKey => $mLabel)
				<option value="{{ $mKey }}"@if ((string) ($filterInput['search_mode'] ?? \App\Models\SearchBox::getDefaultSearchMode()) === (string) $mKey) selected="selected"@endif>{{ $mLabel }}</option>
				@endforeach
			</select>
			<span>{{ __('legacy/torrents.text_mode')}}</span>
		</div>
		@if ($panelVm->hotSearches !== [])
		<div class="nxm-hotsearches">
			@foreach ($panelVm->hotSearches as $kw)
			<a href="?search={{ rawurlencode($kw) }}&amp;notnewword=1"><u>{{ $kw }}</u></a>
			@endforeach
		</div>
		@endif
		@if ($allTags->isNotEmpty())
		<div class="nxm-tags">
			@foreach ($allTags as $tag)
			<a href="?tag_id={{ $tag->id }}"><span class="nx-tag" title="{{ $tag->description }}">{{ $tag->name }}</span></a>
			@endforeach
		</div>
		@endif
		<div class="nxm-searchpanel__submit"><input type="submit" class="btn" value="{{ __('legacy/torrents.submit_go')}}" /></div>
	</div>
</form>

<form method="get" name="searchbox" action="?">
	<div class="nx-fgrid">
		<div class="nx-colhead nx-ffull nx-center"><a href="#" data-klappe="searchboxmain"><img class="plus" src="pic/trans.gif" id="picsearchboxmain" alt="Show/Hide" />{{ $lang_torrents['text_search_box'] ?? '' }}</a></div>
		<div id="ksearchboxmain" class="nx-hidden nx-ffull">
			<div class="nx-row">
			<div class="nx-fcell">
                @safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml($categoryTableHtml ?? ''))</div>

			<div class="nx-fcell nx-vam">
				<div class="nx-ffield">
					<font class="medium">{{ $lang_torrents['text_show_dead_active'] ?? '' }}</font>
				</div>
				<div class="nx-ffield">
					<select class="med" name="incldead" style="width: 100px;">
						<option value="0">{{ $lang_torrents['select_including_dead'] ?? '' }}</option>
						<option value="1"@if ($include_dead == 1) selected="selected"@endif>{{ $lang_torrents['select_active'] ?? '' }} </option>
						<option value="2"@if ($include_dead == 2) selected="selected"@endif>{{ $lang_torrents['select_dead'] ?? '' }}</option>
					</select>
				</div>
				<div class="nx-ffield">
					<font class="medium">{{ $lang_torrents['text_show_special_torrents'] ?? '' }}</font>
				</div>
			 	<div class="nx-ffield">
					<select class="med" name="spstate" style="width: 100px;">
						<option value="0">{{ $lang_torrents['select_all'] ?? '' }}</option>
						@safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml(\App\Support\Html\Tag::promotionSelection($special_state, 0)))
					</select>
				</div>
				<div class="nx-ffield">
					<font class="medium">{{ $lang_torrents['text_show_bookmarked'] ?? '' }}</font>
				</div>
			 	<div class="nx-ffield">
					<select class="med" name="inclbookmarked" style="width: 100px;">
						<option value="0">{{ $lang_torrents['select_all'] ?? '' }}</option>
						<option value="1"@if ($inclbookmarked == 1) selected="selected"@endif>{{ $lang_torrents['select_bookmarked'] ?? '' }}</option>
						<option value="2"@if ($inclbookmarked == 2) selected="selected"@endif>{{ $lang_torrents['select_bookmarked_exclude'] ?? '' }}</option>
					</select>
				</div>
                @if ($showApprovalStatusFilter)
                <div class="nx-ffield">
                    <font class="medium">{{ $lang_torrents['text_approval_status'] ?? '' }}</font>
                </div>
                <div class="nx-ffield">
                    <select class="med" name="approval_status" style="width: 100px;">
                        <option value="">{{ $lang_torrents['select_all'] ?? '' }}</option>
                        @foreach (\App\Models\Torrent::listApprovalStatus(true) as $key => $value)
                            <option value="{{ $key }}"@if (isset($approvalStatus) && (string) $approvalStatus === (string) $key) selected="selected"@endif>{{ $value }}</option>
                        @endforeach
                    </select>
                </div>
                @endif
                <div class="nx-ffield nx-nowrap">
                    <font class="medium">{{ $lang_torrents['size_range'] ?? '' }}</font>
                </div>
                <div class="nx-ffield nx-nowrap">
                    <input type="number" min="1" name="size_begin" style="width: {{ $filterInputWidth }}px" value="{{ $filterInput['size_begin'] ?? '' }}"/> ~ <input type="number" min="1" name="size_end" style="width: {{ $filterInputWidth }}px" value="{{ $filterInput['size_end'] ?? '' }}"/>
                </div>

                <div class="nx-ffield nx-nowrap">
                    <font class="medium">{{ $lang_torrents['seeders_range'] ?? '' }}</font>
                </div>
                <div class="nx-ffield nx-nowrap">
                    <input type="number" min="1" name="seeders_begin" style="width: {{ $filterInputWidth }}px" value="{{ $filterInput['seeders_begin'] ?? '' }}"/> ~ <input type="number" min="1" name="seeders_end" style="width: {{ $filterInputWidth }}px" value="{{ $filterInput['seeders_end'] ?? '' }}"/>
                </div>

                <div class="nx-ffield nx-nowrap">
                    <font class="medium">{{ $lang_torrents['leechers_range'] ?? '' }}</font>
                </div>
                <div class="nx-ffield nx-nowrap">
                    <input type="number" min="1" name="leechers_begin" style="width: {{ $filterInputWidth }}px" value="{{ $filterInput['leechers_begin'] ?? '' }}"/> ~ <input type="number" min="1" name="leechers_end" style="width: {{ $filterInputWidth }}px" value="{{ $filterInput['leechers_end'] ?? '' }}"/>
                </div>

                <div class="nx-ffield nx-nowrap">
                    <font class="medium">{{ $lang_torrents['times_completed_range'] ?? '' }}</font>
                </div>
                <div class="nx-ffield nx-nowrap">
                    <input type="number" min="1" name="times_completed_begin" style="width: {{ $filterInputWidth }}px" value="{{ $filterInput['times_completed_begin'] ?? '' }}"/> ~ <input type="number" min="1" name="times_completed_end" style="width: {{ $filterInputWidth }}px" value="{{ $filterInput['times_completed_end'] ?? '' }}"/>
                </div>

                <div class="nx-ffield nx-nowrap">
                    <font class="medium">{{ $lang_torrents['added_range'] ?? '' }}</font>
                </div>
                <div class="nx-ffield nx-nowrap">
                    @safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml(sprintf(
                        '%s ~ %s',
                        \App\Support\Form::datetimepickerInput('added_begin', htmlspecialchars($filterInput['added_begin'] ?? ''), '', ['require_files' => true, 'format' => 'Y-m-d', 'style' => 'width: '.$filterInputWidth.'px']),
                        \App\Support\Form::datetimepickerInput('added_end', htmlspecialchars($filterInput['added_end'] ?? ''), '', ['require_files' => false, 'format' => 'Y-m-d', 'style' => 'width: '.$filterInputWidth.'px']),
                    )))
                </div>

			</div>
			</div>
		</div>
		<div class="nx-fcell nx-center">
			<div class="nx-row nx-center">
				<div class="nx-embedded">
					{{ $lang_torrents['text_search'] ?? '' }}&nbsp;&nbsp;
				</div>
				<div class="nx-embedded">
					<input id="searchinput" name="search" type="text" value="{{ $searchstr_ori }}" autocomplete="off" style="width: 200px"/>
					<script src="js/meili_autocomplete.js" type="text/javascript"></script>
				</div>
				<div class="nx-embedded">
					&nbsp;{{ $lang_torrents['text_in'] ?? '' }}

					<select name="search_area">
						<option value="0">{{ $lang_torrents['select_title'] ?? '' }}</option>
						<option value="1"@if (($filterInput['search_area'] ?? null) == 1) selected="selected"@endif>{{ $lang_torrents['select_description'] ?? '' }}</option>
						<option value="3"@if (($filterInput['search_area'] ?? null) == 3) selected="selected"@endif>{{ $lang_torrents['select_uploader'] ?? '' }}</option>
					</select>

					{{ $lang_torrents['text_with'] ?? '' }}

					<select name="search_mode" style="width: 60px;">
                        @safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml(\App\Models\SearchBox::listSelectModeOptions($filterInput['search_mode'] ?? '')))
					</select>

					{{ $lang_torrents['text_mode'] ?? '' }}
				</div>
			</div>
@safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml($hotSearchHtml ?? ''))
@if ($allTags->isNotEmpty())
    <div class="nx-embedded">@safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml($tagRep->renderSpan($sectiontype, ['*'], true)))</div>
@endif

		</div>
		<div class="nx-fcell nx-center">
			<input type="submit" class="btn" value="{{ $lang_torrents['submit_go'] ?? '' }}" />
		</div>
	</div>
</form>

<form method="get" name="searchbox" action="?">
	<table border="1" class="searchbox" cellspacing="0" cellpadding="5" width="100%">
		<tbody>
		<tr>
		<td class="colhead" align="center" colspan="2"><a href="#" data-klappe="searchboxmain"><img class="plus" src="pic/trans.gif" id="picsearchboxmain" alt="Show/Hide" />{{ $lang_torrents['text_search_box'] ?? '' }}</a></td>
		</tr></tbody>
		<tbody id="ksearchboxmain" class="nx-hidden">
		<tr>
			<td class="rowfollow" align="left">
                @safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml($categoryTableHtml))</td>

			<td class="rowfollow" valign="middle">
				<table>
					<tr>
						<td class="bottom" style="padding: 1px;padding-left: 10px">
							<font class="medium">{{ $lang_torrents['text_show_dead_active'] ?? '' }}</font>
						</td>
				 	</tr>
					<tr>
						<td class="bottom" style="padding: 1px;padding-left: 10px">
							<select class="med" name="incldead" style="width: 100px;">
								<option value="0">{{ $lang_torrents['select_including_dead'] ?? '' }}</option>
								<option value="1"@if ($include_dead == 1) selected="selected"@endif>{{ $lang_torrents['select_active'] ?? '' }} </option>
								<option value="2"@if ($include_dead == 2) selected="selected"@endif>{{ $lang_torrents['select_dead'] ?? '' }}</option>
							</select>
						</td>
				 	</tr>
					<tr>
						<td class="bottom" style="padding: 1px;padding-left: 10px">
							<font class="medium">{{ $lang_torrents['text_show_special_torrents'] ?? '' }}</font>
						</td>
				 	</tr>
				 	<tr>
						<td class="bottom" style="padding: 1px;padding-left: 10px">
							<select class="med" name="spstate" style="width: 100px;">
								<option value="0">{{ $lang_torrents['select_all'] ?? '' }}</option>
								@safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml(\App\Support\Html::promotionSelection($special_state, 0)))
							</select>
						</td>
					</tr>
					<tr>
						<td class="bottom" style="padding: 1px;padding-left: 10px">
							<font class="medium">{{ $lang_torrents['text_show_bookmarked'] ?? '' }}</font>
						</td>
				 	</tr>
				 	<tr>
						<td class="bottom" style="padding: 1px;padding-left: 10px">
							<select class="med" name="inclbookmarked" style="width: 100px;">
								<option value="0">{{ $lang_torrents['select_all'] ?? '' }}</option>
								<option value="1"@if ($inclbookmarked == 1) selected="selected"@endif>{{ $lang_torrents['select_bookmarked'] ?? '' }}</option>
								<option value="2"@if ($inclbookmarked == 2) selected="selected"@endif>{{ $lang_torrents['select_bookmarked_exclude'] ?? '' }}</option>
							</select>
						</td>
					</tr>
                    @if ($showApprovalStatusFilter)
                    <tr>
                        <td class="bottom" style="padding: 1px;padding-left: 10px">
                            <font class="medium">{{ $lang_torrents['text_approval_status'] ?? '' }}</font>
                        </td>
                    </tr>
                    <tr>
                        <td class="bottom" style="padding: 1px;padding-left: 10px">
                            <select class="med" name="approval_status" style="width: 100px;">
                                <option value="">{{ $lang_torrents['select_all'] ?? '' }}</option>
                                @foreach (\App\Models\Torrent::listApprovalStatus(true) as $key => $value)
                                    <option value="{{ $key }}"@if (isset($approvalStatus) && (string) $approvalStatus === (string) $key) selected="selected"@endif>{{ $value }}</option>
                                @endforeach
                            </select>
                        </td>
                    </tr>
                    @endif
                    <tr>
                        <td class="bottom" style="padding: 1px;padding-left: 10px;white-space: nowrap">
                            <font class="medium">{{ $lang_torrents['size_range'] ?? '' }}</font>
                        </td>
                    </tr>
                    <tr>
                        <td class="bottom" style="padding: 1px;padding-left: 10px;white-space: nowrap">
                            <input type="number" min="1" name="size_begin" style="width: {{ $filterInputWidth }}px" value="{{ $filterInput['size_begin'] ?? '' }}"/> ~ <input type="number" min="1" name="size_end" style="width: {{ $filterInputWidth }}px" value="{{ $filterInput['size_end'] ?? '' }}"/>
                        </td>
                    </tr>

                    <tr>
                        <td class="bottom" style="padding: 1px;padding-left: 10px;white-space: nowrap">
                            <font class="medium">{{ $lang_torrents['seeders_range'] ?? '' }}</font>
                        </td>
                    </tr>
                    <tr>
                        <td class="bottom" style="padding: 1px;padding-left: 10px;white-space: nowrap">
                            <input type="number" min="1" name="seeders_begin" style="width: {{ $filterInputWidth }}px" value="{{ $filterInput['seeders_begin'] ?? '' }}"/> ~ <input type="number" min="1" name="seeders_end" style="width: {{ $filterInputWidth }}px" value="{{ $filterInput['seeders_end'] ?? '' }}"/>
                        </td>
                    </tr>

                    <tr>
                        <td class="bottom" style="padding: 1px;padding-left: 10px;white-space: nowrap">
                            <font class="medium">{{ $lang_torrents['leechers_range'] ?? '' }}</font>
                        </td>
                    </tr>
                    <tr>
                        <td class="bottom" style="padding: 1px;padding-left: 10px;white-space: nowrap">
                            <input type="number" min="1" name="leechers_begin" style="width: {{ $filterInputWidth }}px" value="{{ $filterInput['leechers_begin'] ?? '' }}"/> ~ <input type="number" min="1" name="leechers_end" style="width: {{ $filterInputWidth }}px" value="{{ $filterInput['leechers_end'] ?? '' }}"/>
                        </td>
                    </tr>

                    <tr>
                        <td class="bottom" style="padding: 1px;padding-left: 10px;white-space: nowrap">
                            <font class="medium">{{ $lang_torrents['times_completed_range'] ?? '' }}</font>
                        </td>
                    </tr>
                    <tr>
                        <td class="bottom" style="padding: 1px;padding-left: 10px;white-space: nowrap">
                            <input type="number" min="1" name="times_completed_begin" style="width: {{ $filterInputWidth }}px" value="{{ $filterInput['times_completed_begin'] ?? '' }}"/> ~ <input type="number" min="1" name="times_completed_end" style="width: {{ $filterInputWidth }}px" value="{{ $filterInput['times_completed_end'] ?? '' }}"/>
                        </td>
                    </tr>

                    <tr>
                        <td class="bottom" style="padding: 1px;padding-left: 10px;white-space: nowrap">
                            <font class="medium">{{ $lang_torrents['added_range'] ?? '' }}</font>
                        </td>
                    </tr>
                    <tr>
                        <td class="bottom" style="padding: 1px;padding-left: 10px;white-space: nowrap">
                            @safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml(sprintf(
                                '%s ~ %s',
                                \App\Support\Form::datetimepickerInput('added_begin', htmlspecialchars($filterInput['added_begin'] ?? ''), '', ['require_files' => true, 'format' => 'Y-m-d', 'style' => 'width: '.$filterInputWidth.'px']),
                                \App\Support\Form::datetimepickerInput('added_end', htmlspecialchars($filterInput['added_end'] ?? ''), '', ['require_files' => false, 'format' => 'Y-m-d', 'style' => 'width: '.$filterInputWidth.'px']),
                            )))
                        </td>
                    </tr>

				</table>
			</td>
		</tr>
		</tbody>
		<tbody>
		<tr>
			<td class="rowfollow" align="center">
				<table>
					<tr>
						<td class="embedded">
							{{ $lang_torrents['text_search'] ?? '' }}&nbsp;&nbsp;
						</td>
						<td class="embedded">
							<table>
								<tr>
									<td class="embedded">
										<input id="searchinput" name="search" type="text" value="{{ $searchstr_ori }}" autocomplete="off" style="width: 200px"/>
										<script src="js/meili_autocomplete.js" type="text/javascript"></script>
									</td>
								</tr>
							</table>
						</td>
						<td class="embedded">
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
						</td>
					</tr>
@safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml($hotSearchHtml))
@if ($allTags->isNotEmpty())
    <tr><td colspan="3" class="embedded" style="padding-top: 4px">@safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml($tagRep->renderSpan($sectiontype, ['*'], true)))</td></tr>
@endif

				</table>
			</td>
			<td class="rowfollow" align="center">
				<input type="submit" class="btn" value="{{ $lang_torrents['submit_go'] ?? '' }}" />
			</td>
		</tr>
		</tbody>
	</table>
	</form>

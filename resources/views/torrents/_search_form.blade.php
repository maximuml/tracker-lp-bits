@php
$lang_torrents = (array) (\app(\App\Support\Globals::class)->get('lang_torrents') ?? []);
$CURUSER = (array) (\app(\App\Support\CurrentUser::class)->get() ?? []);
$Cache = \app(\App\Support\Cache\LegacyRedisCache::class);
$__server_QUERY_STRING = \App\Support\Input::serverValue('QUERY_STRING');
$searchBoxRightTdStyle = 'padding: 1px;padding-left: 10px;white-space: nowrap';
$sectiontype = $sectiontype ?? 0;
$include_dead = (int) ($include_dead ?? 0);
$special_state = $special_state ?? 0;
$inclbookmarked = (int) ($inclbookmarked ?? 0);
$showApprovalStatusFilter = (bool) ($showApprovalStatusFilter ?? false);
$approvalStatus = $approvalStatus ?? '';
$filterInputWidth = (int) ($filterInputWidth ?? 80);
$searchstr_ori = (string) ($searchstr_ori ?? '');
$allTags = $allTags ?? collect();
$tagRep = $tagRep ?? app(\App\Repositories\TagRepository::class);
$browsecatmode = $browsecatmode ?? 0;
@endphp
<form method="get" name="searchbox" action="?">
	<table border="1" class="searchbox" cellspacing="0" cellpadding="5" width="100%">
		<tbody>
		<tr>
		<td class="colhead" align="center" colspan="2"><a href="javascript: klappe_news('searchboxmain')"><img class="plus" src="pic/trans.gif" id="picsearchboxmain" alt="Show/Hide" />{{ $lang_torrents['text_search_box'] ?? '' }}</a></td>
		</tr></tbody>
		<tbody id="ksearchboxmain" style="display:none">
		<tr>
			<td class="rowfollow" align="left">
                {!! \App\Support\SearchBox::buildCategoryTableWithContext($sectiontype, '1', '?', '?', 0, $__server_QUERY_STRING, ['select_unselect' => true, 'user_notifs' => $CURUSER['notifs'] ?? null]) !!}
			</td>

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
								{!! \App\Support\Html::promotionSelection($special_state, 0) !!}
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
                        <td class="bottom" style="{{ $searchBoxRightTdStyle }}">
                            <font class="medium">{{ $lang_torrents['size_range'] ?? '' }}</font>
                        </td>
                    </tr>
                    <tr>
                        <td class="bottom" style="{{ $searchBoxRightTdStyle }}">
                            <input type="number" min="1" name="size_begin" style="width: {{ $filterInputWidth }}px" value="{{ htmlspecialchars(\request()->query('size_begin') ?? '') }}"/> ~ <input type="number" min="1" name="size_end" style="width: {{ $filterInputWidth }}px" value="{{ htmlspecialchars(\request()->query('size_end') ?? '') }}"/>
                        </td>
                    </tr>

                    <tr>
                        <td class="bottom" style="{{ $searchBoxRightTdStyle }}">
                            <font class="medium">{{ $lang_torrents['seeders_range'] ?? '' }}</font>
                        </td>
                    </tr>
                    <tr>
                        <td class="bottom" style="{{ $searchBoxRightTdStyle }}">
                            <input type="number" min="1" name="seeders_begin" style="width: {{ $filterInputWidth }}px" value="{{ htmlspecialchars(\request()->query('seeders_begin') ?? '') }}"/> ~ <input type="number" min="1" name="seeders_end" style="width: {{ $filterInputWidth }}px" value="{{ htmlspecialchars(\request()->query('seeders_end') ?? '') }}"/>
                        </td>
                    </tr>

                    <tr>
                        <td class="bottom" style="{{ $searchBoxRightTdStyle }}">
                            <font class="medium">{{ $lang_torrents['leechers_range'] ?? '' }}</font>
                        </td>
                    </tr>
                    <tr>
                        <td class="bottom" style="{{ $searchBoxRightTdStyle }}">
                            <input type="number" min="1" name="leechers_begin" style="width: {{ $filterInputWidth }}px" value="{{ htmlspecialchars(\request()->query('leechers_begin') ?? '') }}"/> ~ <input type="number" min="1" name="leechers_end" style="width: {{ $filterInputWidth }}px" value="{{ htmlspecialchars(\request()->query('leechers_end') ?? '') }}"/>
                        </td>
                    </tr>

                    <tr>
                        <td class="bottom" style="{{ $searchBoxRightTdStyle }}">
                            <font class="medium">{{ $lang_torrents['times_completed_range'] ?? '' }}</font>
                        </td>
                    </tr>
                    <tr>
                        <td class="bottom" style="{{ $searchBoxRightTdStyle }}">
                            <input type="number" min="1" name="times_completed_begin" style="width: {{ $filterInputWidth }}px" value="{{ htmlspecialchars(\request()->query('times_completed_begin') ?? '') }}"/> ~ <input type="number" min="1" name="times_completed_end" style="width: {{ $filterInputWidth }}px" value="{{ htmlspecialchars(\request()->query('times_completed_end') ?? '') }}"/>
                        </td>
                    </tr>

                    <tr>
                        <td class="bottom" style="{{ $searchBoxRightTdStyle }}">
                            <font class="medium">{{ $lang_torrents['added_range'] ?? '' }}</font>
                        </td>
                    </tr>
                    <tr>
                        <td class="bottom" style="{{ $searchBoxRightTdStyle }}">
                            {!! sprintf(
                                '%s ~ %s',
                                \App\Support\Form::datetimepickerInput('added_begin', htmlspecialchars(\request()->query('added_begin') ?? ''), '', ['require_files' => true, 'format' => 'Y-m-d', 'style' => 'width: '.$filterInputWidth.'px']),
                                \App\Support\Form::datetimepickerInput('added_end', htmlspecialchars(\request()->query('added_end') ?? ''), '', ['require_files' => false, 'format' => 'Y-m-d', 'style' => 'width: '.$filterInputWidth.'px']),
                            ) !!}
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
										<input id="searchinput" name="search" type="text" value="{{ $searchstr_ori }}" autocomplete="off" style="width: 200px" oninput="meiliSuggestInput(this.value)" onkeydown="meiliSuggestKey(event)"/>
										<script src="js/meili_autocomplete.js" type="text/javascript"></script>
									</td>
								</tr>
							</table>
						</td>
						<td class="embedded">
							{{ '&nbsp;'.($lang_torrents['text_in'] ?? '') }}

							<select name="search_area">
								<option value="0">{{ $lang_torrents['select_title'] ?? '' }}</option>
								<option value="1"@if (\request()->query('search_area') !== null && \request()->query('search_area') == 1) selected="selected"@endif>{{ $lang_torrents['select_description'] ?? '' }}</option>
								<option value="3"@if (\request()->query('search_area') !== null && \request()->query('search_area') == 3) selected="selected"@endif>{{ $lang_torrents['select_uploader'] ?? '' }}</option>
							</select>

							{{ $lang_torrents['text_with'] ?? '' }}

							<select name="search_mode" style="width: 60px;">
                                {!! \App\Models\SearchBox::listSelectModeOptions(\request()->query('search_mode') ?? '') !!}
							</select>

							{{ $lang_torrents['text_mode'] ?? '' }}
						</td>
					</tr>
@php
    $Cache->new_page('hot_search', 3670, true);
    if (! $Cache->get_page()) {
        \app(\App\Repositories\TorrentListingRepository::class)->cleanupSuggest();
        $searchres = \app(\App\Repositories\TorrentListingRepository::class)->getHotSearch();
        $hotcount = 0;
        $hotsearch = '';
        foreach ($searchres as $searchrow) {
            $hotsearch .= '<a href="'.htmlspecialchars('?search='.rawurlencode($searchrow['keywords']).'&notnewword=1').'"><u>'.htmlspecialchars($searchrow['keywords']).'</u></a>&nbsp;&nbsp;';
            $hotcount += mb_strlen($searchrow['keywords'], 'UTF-8');
            if ($hotcount > 60) {
                break;
            }
        }
        $Cache->add_whole_row();
        if ($hotsearch) {
            echo '<tr><td class="embedded" colspan="3">&nbsp;&nbsp;'.$hotsearch.'</td></tr>';
        }
        $Cache->end_whole_row();
        $Cache->cache_page();
    }
    echo $Cache->next_row();
@endphp

@if ($allTags->isNotEmpty())
    <tr><td colspan="3" class="embedded" style="padding-top: 4px">{!! $tagRep->renderSpan($sectiontype, ['*'], true) !!}</td></tr>
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

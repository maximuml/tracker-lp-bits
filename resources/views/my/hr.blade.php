@extends('layouts.app')

@section('title', ($userInfo->username ?? '') . ' - H&R')

@section('content')
<div class="nx-hr">
    <h1>{{ ($userInfo->username ?? '') . ' - H&R' }}</h1>

    <nav class="nx-hr__status" aria-label="{{ __('myhr.status_nav') }}">
        @foreach ($headerFilters as $headerFilter)
            <a class="nx-hr__status-link" href="{{ request()->getPathInfo() }}?{{ $headerFilter['query'] }}" @if ($headerFilter['active']) aria-current="page" @endif>
                <span class="nx-hr__status-label">{{ $headerFilter['text'] }}</span>
                <span class="nx-hr__status-count">{{ number_format($page->statusCounts[$headerFilter['status']] ?? 0) }}</span>
                <span class="nx-hr__status-subline">
                    @if ($headerFilter['status'] === \App\Enums\HitAndRunStatus::UNREACHED->value && $page->banUserWhenCountsReach !== null)
                        {{ __('myhr.status_ban_at', ['count' => number_format($page->banUserWhenCountsReach)]) }}
                    @else
                        {{ __('myhr.status_subline_'.$headerFilter['status']) }}
                    @endif
                </span>
            </a>
        @endforeach
    </nav>

    @if ($page->isEnabled)
        <aside class="nx-hr__rules" aria-label="{{ __('myhr.rules_label') }}">
            <p class="nx-hr__rule">
                {{ __('myhr.rule_after_download') }}
                @if ($page->seedTimeMinimum !== null)
                    {{ __('myhr.rule_seed', ['hours' => number_format($page->seedTimeMinimum)]) }}
                @endif
                @if ($page->ignoreWhenRatioReach !== null)
                    {{ $page->seedTimeMinimum !== null ? __('myhr.rule_or_ratio', ['ratio' => number_format($page->ignoreWhenRatioReach, 1)]) : __('myhr.rule_ratio', ['ratio' => number_format($page->ignoreWhenRatioReach, 1)]) }}
                @endif
                @if ($page->inspectTime !== null)
                    {{ __('myhr.rule_within_days', ['days' => number_format($page->inspectTime / 24)]) }}
                @endif
            </p>
            <p class="nx-hr__rule">{{ __('myhr.rule_remove', ['bonus' => number_format($page->price)]) }}</p>
        </aside>
    @endif

    <form id="filterForm" class="nx-hr__search" action="{{ request()->getPathInfo() }}" method="get">
        <div class="nx-hr__search-field">
            <label for="q">{{ __('myhr.search_label') }}</label>
            <input id="q" type="number" name="q" value="{{ $q ?? '' }}" min="1" step="1">
        </div>
        <input type="hidden" name="status" value="{{ $status }}">
        @if (request()->query->has('userid'))
            <input type="hidden" name="userid" value="{{ $userid }}">
        @endif
        <div class="nx-hr__search-actions">
            <button class="nx-btn nx-btn--primary" type="submit">{{ __('myhr.search_submit') }}</button>
            <a class="nx-hr__reset" href="{{ request()->getPathInfo() }}?{{ $queryString }}">{{ __('myhr.reset') }}</a>
        </div>
        <p class="nx-hr__count">
            <span>{{ number_format($rescount) }} H&R</span>
            <span>{{ __('myhr.balance', ['balance' => number_format($page->balance)]) }}</span>
        </p>
    </form>

    <x-data-table id="hr-table" class="nx-hr__table" :caption="($userInfo->username ?? '') . ' - H&R'" :caption-hidden="true" :headers="[
        __('myhr.th_torrent_name'),
        __('myhr.th_up_down'),
        __('myhr.th_share_ratio'),
        __('myhr.th_seeding'),
        __('myhr.th_status'),
        __('myhr.th_action'),
    ]">
        @forelse ($page->rows as $row)
            <tr class="nx-hr__row">
                <td class="nx-hr__torrent" data-label="{{ __('myhr.th_torrent_name') }}">
                    <a class="nx-hr__torrent-link" href="/web/details/{{ $row->torrentId }}">{{ $row->torrentName !== '' ? $row->torrentName : '—' }}</a>
                    <p class="nx-hr__meta">{{ __('myhr.row_meta', ['id' => $row->id, 'completed' => $row->completedAt ?? '—']) }}</p>
                    @if ($row->comment !== '')
                        <div class="nx-hr__comment"><x-nl2br :text="$row->comment" /></div>
                    @endif
                </td>
                <td class="nx-hr__traffic" data-label="{{ __('myhr.th_up_down') }}">
                    <strong>{{ $row->uploaded }}</strong>
                    <span>{{ $row->downloaded }}</span>
                </td>
                <td data-label="{{ __('myhr.th_share_ratio') }}">
                    <span class="nx-hr__ratio {{ $row->ratioLow ? 'nx-hr__ratio--low' : '' }}">{{ $row->ratio }}</span>
                </td>
                <td class="nx-hr__seeding" data-label="{{ __('myhr.th_seeding') }}">
                    @if ($row->seedDone !== null && $row->seedRequired !== null)
                        <span class="nx-hr__seed-hours">{{ number_format(min($row->seedDone, $row->seedRequired) / 3600) }} h of {{ number_format($row->seedRequired / 3600) }} h</span>
                        <progress class="nx-hr__progress" max="{{ $row->seedRequired }}" value="{{ min($row->seedDone, $row->seedRequired) }}" aria-label="{{ __('myhr.progress_label', ['done' => number_format(min($row->seedDone, $row->seedRequired) / 3600), 'required' => number_format($row->seedRequired / 3600)]) }}"></progress>
                        <span class="nx-hr__seed-remaining">
                            {{ __('myhr.seed_remaining', ['hours' => number_format(ceil(max(0, $row->seedRequired - $row->seedDone) / 3600))]) }}
                            @if ($page->ignoreWhenRatioReach !== null)
                                {{ __('myhr.seed_or_ratio', ['ratio' => number_format($page->ignoreWhenRatioReach, 1)]) }}
                            @endif
                        </span>
                    @else
                        <span class="nx-hr__empty-value">—</span>
                    @endif
                </td>
                <td class="nx-hr__status-cell" data-label="{{ __('myhr.th_status') }}">
                    <span class="nx-hr__badge {{ $row->status === \App\Enums\HitAndRunStatus::UNREACHED->value ? 'nx-hr__badge--unreached' : '' }}">{{ $row->statusText }}</span>
                    @if ($row->status === \App\Enums\HitAndRunStatus::INSPECTING->value && $row->inspectEndsIn !== null)
                        <span class="nx-hr__ends-in">{{ __('myhr.status_ends_in', ['duration' => $row->inspectEndsIn]) }}</span>
                    @endif
                </td>
                <td class="nx-hr__action" data-label="{{ __('myhr.th_action') }}">
                    @if ($row->canRemove)
                        <input type="button" class="remove-hr nx-btn{{ $row->removeDisabled ? '' : ' nx-btn--primary' }}" value="{{ __('myhr.action_remove_price', ['bonus' => number_format($page->price)]) }}" data-id="{{ $row->id }}" @disabled($row->removeDisabled)>
                        @if ($row->removeDisabled && $row->pointsShort !== null)
                            <span class="nx-hr__need">{{ __('myhr.points_needed', ['count' => number_format($row->pointsShort)]) }}</span>
                        @endif
                    @else
                        <span class="nx-hr__empty-value">—</span>
                    @endif
                </td>
            </tr>
        @empty
            <tr class="nx-hr__empty-row">
                <td colspan="6">{{ __('myhr.empty') }}</td>
            </tr>
        @endforelse
    </x-data-table>
    {{ $pagerbottom ?? '' }}
</div>
@endsection

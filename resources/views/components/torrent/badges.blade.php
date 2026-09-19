@props(['set'])
@if ($set->paid)<x-torrent.paid-icon />@endif
@if ($set->promotion !== null)<x-torrent.promotion :badge="$set->promotion" />@endif
@if ($set->hitAndRun)<img class="hitandrun" src="pic/trans.gif" alt="H&R" title="H&R" />@endif
@if ($set->approval !== null)<span title="{{ $set->approval->title }}">{{ $set->approval->icon }}</span>@endif

@props(['set'])
@if ($set->paid)<x-torrent.paid-icon />@endif
@if ($set->promotion !== null)<x-torrent.promotion :badge="$set->promotion" />@endif
@if ($set->hitAndRun)<i class="hitandrun" role="img" aria-label="{{ __('label.hit_and_run.label') }}" title="{{ __('label.hit_and_run.label') }}"></i>@endif
@if ($set->approval !== null)<span title="{{ $set->approval->title }}">{{ $set->approval->icon }}</span>@endif

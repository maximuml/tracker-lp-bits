@extends('layouts.app')

@section('title', $headTitle)

@section('content')
@if (empty($requestFlags['cmtpage']))
@if (! empty($requestFlags['uploaded']))
<h1 class="text-center">{{ __('details.text_successfully_uploaded') ?? '' }}</h1>
<p>{{ __('details.text_you_should') }}<b>{{ __('details.text_redownload') }}</b>{{ __('details.text_redownload_torrent_note') }}</p>
@elseif (! empty($requestFlags['edited']))
<h1 class="text-center">{{ __('details.text_successfully_edited') ?? '' }}</h1>
@if (! empty($requestFlags['returnto']))
<p><b>{{ __('details.text_go_back') ?? '' }}<a href="{{ $requestFlags['returnto'] }}">{{ __('details.text_whence_you_came') ?? '' }}</a></b></p>
@endif
@elseif (! empty($requestFlags['existed']))
<h1 class="text-center">{{ __('details.torrent_existed') ?? '' }}</h1>
@if (! empty($requestFlags['returnto']))
<p><b>{{ __('details.text_go_back') ?? '' }}<a href="{{ $requestFlags['returnto'] }}">{{ __('details.text_whence_you_came') ?? '' }}</a></b></p>
@endif
@endif

<h1 class="text-center" id="top">{{ $details->title->name }}@if ($details->title->banned) <b>(<span class="striking">{{ __('functions.text_banned') }}</span>)</b>@endif@if ($details->title->badges->paid)<x-torrent.paid-icon :size="20" />@endif@if ($details->title->badges->promotion !== null)&nbsp;&nbsp;&nbsp;<x-torrent.promotion :badge="$details->title->badges->promotion" />@endif@if ($details->title->badges->hitAndRun)<img class="hitandrun" src="pic/trans.gif" alt="H&R" title="H&R" />@endif@if ($details->title->badges->approval !== null)<span title="{{ $details->title->badges->approval->title }}">{{ $details->title->badges->approval->icon }}</span>@endif</h1>

@if ($details->denyBanner !== null)
@include('torrent.details._deny_banner', ['banner' => $details->denyBanner])
@endif

<x-data-table :caption="$details->title->name" captionHidden class="w-[97%] nxm-kv">
@if ($details->downloadAllowed)
<tr><td class="w-[1%] whitespace-nowrap align-top px-2.5 py-1.5 text-right font-semibold text-nxm-text-dim">{{ __('details.row_download') }}</td><td class="align-top px-2.5 py-1.5"><a class="index" href="/download?id={{ $torrentId }}">{{ ($torrentNamePrefix ?? '').'.'.$details->saveAs }}.torrent</a>&nbsp;&nbsp;<livewire:bookmark-icon :torrent-id="$torrentId" />&nbsp;&nbsp;&nbsp;{{ __('details.row_upped_by') }}&nbsp;@if ($details->owner->anonymous)<i>{{ __('details.text_anonymous') }}</i>@if ($details->owner->showUsername) ({{ $details->owner->username }})@endif@elseif ($details->owner->username !== null){{ $details->owner->username }}@else<i></i>@endif{{ $details->uploadTimePrefix }}{{ $details->uploadTime }}</td></tr>
@else
<x-settings-row :label="__('details.row_download')">{{ __('details.text_downloading_not_allowed') ?? '' }}</x-settings-row>
@endif
@if (! $tagHtml->isEmpty())
<x-settings-row :label="__('details.row_tags')">{{ $tagHtml }}</x-settings-row>
@endif
<x-settings-row :label="__('details.row_basic_info')"><b>{{ __('details.text_size') }}</b>{{ \App\Support\Format::size((float) $torrentRow['size']) }}&nbsp;&nbsp;&nbsp;<b>{{ __('details.row_type') }}:</b>&nbsp;{{ $torrentRow['cat_name'] }}@foreach ($details->taxonomy as $entry)&nbsp;&nbsp;&nbsp;<b>{{ $entry->label }}: </b>{{ $entry->value }}@endforeach</x-settings-row>
<x-settings-row :label="__('details.row_action')">@include('torrent.details._actions')</x-settings-row>
<x-settings-row :label="__('details.torrent_dl_url')"><a title="{{ __('details.torrent_dl_url_notice') ?? '' }}" href="{{ $downloadUrl }}">{{ __('details.torrent_dl_url_text') }}</a></x-settings-row>
{{ $customFieldsHtml }}
@if (! $technicalInfoResult->isEmpty())
<x-settings-row :label="__('functions.text_technical_info')">{{ $technicalInfoResult }}</x-settings-row>
@endif
@if (count($screenshots ?? []) > 0)
<x-settings-row :label="__('details.row_screenshots')">
    <div class="nxm-screens">
    @foreach ($screenshots as $shot)
        <a class="nxm-screens__item" href="{{ $shot }}" target="_blank" rel="noopener"><img src="{{ $shot }}" alt="{{ $details->title->name }}" loading="lazy" /></a>
    @endforeach
    </div>
</x-settings-row>
@endif
@if ($showDescription)
<livewire:descr-row :descr-raw="$descrRaw" :show-or-hide-title="$details->showOrHideTitle" />
@endif
<x-settings-row :label="__('details.row_torrent_info')"><div class="nxm-detail-cells">@if ($details->info->numFiles !== null)<div><b>{{ __('details.text_num_files') }}</b>{{ $details->info->numFiles }}{{ __('details.text_files') }}<br /></div>@endif<div><b>{{ __('details.row_info_hash') }}:</b>&nbsp;{{ $details->info->infoHash }}</div>@if ($details->info->showStructure)<div><b>{{ __('details.text_torrent_structure') }}</b><a href="/web/torrent_info?id={{ $details->info->torrentId }}">{{ __('details.text_torrent_info_note') }}</a></div>@endif</div><livewire:torrent-file-list :torrent-id="$details->info->torrentId" /></x-settings-row>
<x-settings-row :label="__('details.row_hot_meter')"><div class="nxm-detail-cells"><div><b>{{ __('details.text_views') }}</b>{{ $details->hotMeter->views }}</div><div><b>{{ __('details.text_hits') }}</b>{{ $details->hotMeter->hits }}</div><div><b>{{ __('details.text_snatched') }}</b><a href="/web/viewsnatches?id={{ $details->hotMeter->torrentId }}"><b>{{ $details->hotMeter->timesCompleted }}{{ $details->hotMeter->snatchesPre }}</b>{{ $details->hotMeter->snatchesPost }}</a></div><div><b>{{ $details->hotMeter->lastSeederLabel }}</b>{{ $details->hotMeter->lastSeeder }}</div></div></x-settings-row>
<livewire:peer-list :torrent-id="$details->peers->torrentId" :seeders="$details->peers->seeders" :leechers="$details->peers->leechers" :open-on-load="$requestFlags['dllist'] ?? false" />
<x-settings-row :label="__('details.magic_value_award')"><livewire:magic-section :torrent-id="$torrentId" /></x-settings-row>
<x-settings-row :label="__('details.row_thanks_by')"><livewire:thanks-section :torrent-id="$torrentId" /></x-settings-row>
</x-data-table>
@else
<h1 id="top">{{ __('details.text_comments_for') ?? '' }}<a href="/web/details/{{ $torrentId }}">{{ $torrentRow['name'] }}</a></h1>
@endif

@include('torrent._comments')
@endsection

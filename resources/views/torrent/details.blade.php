@extends('layouts.app')

@section('title', $headTitle)

@section('content')
@if (empty($requestFlags['cmtpage']))
@if (! empty($requestFlags['uploaded']))
<h1 class="text-center">{{ __('legacy/details.text_successfully_uploaded') ?? '' }}</h1>
<p>{{ __('legacy/details.text_you_should') }}<b>{{ __('legacy/details.text_redownload') }}</b>{{ __('legacy/details.text_redownload_torrent_note') }}</p>
@elseif (! empty($requestFlags['edited']))
<h1 class="text-center">{{ __('legacy/details.text_successfully_edited') ?? '' }}</h1>
@if (! empty($requestFlags['returnto']))
<p><b>{{ __('legacy/details.text_go_back') ?? '' }}<a href="{{ $requestFlags['returnto'] }}">{{ __('legacy/details.text_whence_you_came') ?? '' }}</a></b></p>
@endif
@elseif (! empty($requestFlags['existed']))
<h1 class="text-center">{{ __('legacy/details.torrent_existed') ?? '' }}</h1>
@if (! empty($requestFlags['returnto']))
<p><b>{{ __('legacy/details.text_go_back') ?? '' }}<a href="{{ $requestFlags['returnto'] }}">{{ __('legacy/details.text_whence_you_came') ?? '' }}</a></b></p>
@endif
@endif

<h1 class="text-center" id="top">{{ $details->title->name }}@if ($details->title->banned) <b>(<span class="striking">{{ __('legacy/functions.text_banned') }}</span>)</b>@endif@if ($details->title->badges->paid)<x-torrent.paid-icon :size="20" />@endif@if ($details->title->badges->promotion !== null)&nbsp;&nbsp;&nbsp;<x-torrent.promotion :badge="$details->title->badges->promotion" />@endif@if ($details->title->badges->hitAndRun)<img class="hitandrun" src="pic/trans.gif" alt="H&R" title="H&R" />@endif@if ($details->title->badges->approval !== null)<span title="{{ $details->title->badges->approval->title }}">{{ $details->title->badges->approval->icon }}</span>@endif</h1>

@if ($details->denyBanner !== null)
@include('torrent.details._deny_banner', ['banner' => $details->denyBanner])
@endif

<x-data-table :caption="$details->title->name" captionHidden class="w-[97%] nxm-kv">
@if ($details->downloadAllowed)
<tr><td class="w-[1%] whitespace-nowrap align-top px-2.5 py-1.5 text-right font-semibold text-nxm-text-dim">{{ __('legacy/details.row_download') }}</td><td class="align-top px-2.5 py-1.5"><a class="index" href="download.php?id={{ $torrentId }}">{{ ($torrentNamePrefix ?? '').'.'.$details->saveAs }}.torrent</a>&nbsp;&nbsp;<a id="bookmark0" href="#" data-bookmark-torrent="{{ $torrentId }}" data-bookmark-counter="0">{{ $details->bookmark }}</a>&nbsp;&nbsp;&nbsp;{{ __('legacy/details.row_upped_by') }}&nbsp;@if ($details->owner->anonymous)<i>{{ __('legacy/details.text_anonymous') }}</i>@if ($details->owner->showUsername) ({{ $details->owner->username }})@endif@elseif ($details->owner->username !== null){{ $details->owner->username }}@else<i></i>@endif{{ $details->uploadTimePrefix }}{{ $details->uploadTime }}</td></tr>
@else
<x-settings-row :label="__('legacy/details.row_download')">{{ __('legacy/details.text_downloading_not_allowed') ?? '' }}</x-settings-row>
@endif
@if (! $tagHtml->isEmpty())
<x-settings-row :label="__('legacy/details.row_tags')">{{ $tagHtml }}</x-settings-row>
@endif
<x-settings-row :label="__('legacy/details.row_basic_info')"><b>{{ __('legacy/details.text_size') }}</b>{{ \App\Support\Format::size((float) $torrentRow['size']) }}&nbsp;&nbsp;&nbsp;<b>{{ __('legacy/details.row_type') }}:</b>&nbsp;{{ $torrentRow['cat_name'] }}@foreach ($details->taxonomy as $entry)&nbsp;&nbsp;&nbsp;<b>{{ $entry->label }}: </b>{{ $entry->value }}@endforeach</x-settings-row>
<x-settings-row :label="__('legacy/details.row_action')">@include('torrent.details._actions')</x-settings-row>
<x-settings-row :label="__('legacy/details.torrent_dl_url')"><a title="{{ __('legacy/details.torrent_dl_url_notice') ?? '' }}" href="{{ $downloadUrl }}">{{ __('legacy/details.torrent_dl_url_text') }}</a></x-settings-row>
{{ $customFieldsHtml }}
@if (! $technicalInfoResult->isEmpty())
<x-settings-row :label="__('legacy/functions.text_technical_info')">{{ $technicalInfoResult }}</x-settings-row>
@endif
@if (count($screenshots ?? []) > 0)
<x-settings-row :label="__('legacy/details.row_screenshots')">
    <div class="nxm-screens">
    @foreach ($screenshots as $shot)
        <a class="nxm-screens__item" href="{{ $shot }}" target="_blank" rel="noopener"><img src="{{ $shot }}" alt="{{ $details->title->name }}" loading="lazy" /></a>
    @endforeach
    </div>
</x-settings-row>
@endif
@if ($showDescription)
<tr><td class="whitespace-nowrap align-top px-2.5 py-1.5 text-right font-semibold text-nxm-text-dim"><a href="#" data-klappe="descr"><span class="whitespace-nowrap"><img class="minus" src="pic/trans.gif" alt="Show/Hide" id="picdescr" title="{{ $details->showOrHideTitle }}" /> {{ __('legacy/details.row_description') }}</span></a></td><td class="align-top px-2.5 py-1.5"><div id='kdescr'>{{ $descr }}</div></td></tr>
@endif
<x-settings-row :label="__('legacy/details.row_torrent_info')"><div class="nxm-detail-cells">@if ($details->info->numFiles !== null)<div><b>{{ __('legacy/details.text_num_files') }}</b>{{ $details->info->numFiles }}{{ __('legacy/details.text_files') }}<br /><span id="showfl"><a href="#" data-filelist="{{ $details->info->torrentId }}">{{ __('legacy/details.text_see_full_list') }}</a></span><span id="hidefl" class="nx-hidden"><a href="#" data-filelist="{{ $details->info->torrentId }}" data-filelist-mode="hide">{{ __('legacy/details.text_hide_list') }}</a></span></div>@endif<div><b>{{ __('legacy/details.row_info_hash') }}:</b>&nbsp;{{ $details->info->infoHash }}</div>@if ($details->info->showStructure)<div><b>{{ __('legacy/details.text_torrent_structure') }}</b><a href="torrent_info.php?id={{ $details->info->torrentId }}">{{ __('legacy/details.text_torrent_info_note') }}</a></div>@endif</div><span id="filelist"></span></x-settings-row>
<x-settings-row :label="__('legacy/details.row_hot_meter')"><div class="nxm-detail-cells"><div><b>{{ __('legacy/details.text_views') }}</b>{{ $details->hotMeter->views }}</div><div><b>{{ __('legacy/details.text_hits') }}</b>{{ $details->hotMeter->hits }}</div><div><b>{{ __('legacy/details.text_snatched') }}</b><a href="viewsnatches.php?id={{ $details->hotMeter->torrentId }}"><b>{{ $details->hotMeter->timesCompleted }}{{ $details->hotMeter->snatchesPre }}</b>{{ $details->hotMeter->snatchesPost }}</a></div><div><b>{{ $details->hotMeter->lastSeederLabel }}</b>{{ $details->hotMeter->lastSeeder }}</div></div></x-settings-row>
<tr><td class="whitespace-nowrap align-top px-2.5 py-1.5 text-right font-semibold text-nxm-text-dim"><span id="seeders"></span><span id="leechers"></span>{{ __('legacy/details.row_peers') }}<br /><span id="showpeer"><a href="#" data-peerlist="{{ $details->peers->torrentId }}" class="sublink">{{ __('legacy/details.text_see_full_list') }}</a></span><span id="hidepeer" class="nx-hidden"><a href="#" data-peerlist="{{ $details->peers->torrentId }}" data-peerlist-mode="hide" class="sublink">{{ __('legacy/details.text_hide_list') }}</a></span></td><td class="align-top px-2.5 py-1.5"><div id="peercount"><b>{{ $details->peers->seeders }}{{ __('legacy/details.text_seeders') }}{{ \App\Support\Strings::addS($details->peers->seeders) }}</b> | <b>{{ $details->peers->leechers }}{{ __('legacy/details.text_leechers') }}{{ \App\Support\Strings::addS($details->peers->leechers) }}</b></div><div id="peerlist"></div></td></tr>
<x-settings-row :label="__('legacy/details.magic_value_award')">@include('torrent.details._magic', ['magic' => $details->magic])</x-settings-row>
<x-settings-row :label="__('legacy/details.row_thanks_by')">@include('torrent.details._thanks', ['thanks' => $details->thanks])</x-settings-row>
</x-data-table>
@else
<h1 id="top">{{ __('legacy/details.text_comments_for') ?? '' }}<a href="details.php?id={{ $torrentId }}">{{ $torrentRow['name'] }}</a></h1>
@endif

@include('torrent._comments')
@endsection

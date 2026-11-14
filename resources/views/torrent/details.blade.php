@extends('layouts.app', ['chromeVariant' => 'modern'])

@section('title', $vm->headTitle)

@section('content')
@if (empty($vm->requestFlags['cmtpage']))
@if (! empty($vm->requestFlags['uploaded']))
<h2 class="text-center">{{ __('details.text_successfully_uploaded') ?? '' }}</h2>
<p>{{ __('details.text_you_should') }}<b>{{ __('details.text_redownload') }}</b>{{ __('details.text_redownload_torrent_note') }}</p>
@elseif (! empty($vm->requestFlags['edited']))
<h2 class="text-center">{{ __('details.text_successfully_edited') ?? '' }}</h2>
@if (! empty($vm->requestFlags['returnto']))
<p><b>{{ __('details.text_go_back') ?? '' }}<a href="{{ $vm->requestFlags['returnto'] }}">{{ __('details.text_whence_you_came') ?? '' }}</a></b></p>
@endif
@elseif (! empty($vm->requestFlags['existed']))
<h2 class="text-center">{{ __('details.torrent_existed') ?? '' }}</h2>
@if (! empty($vm->requestFlags['returnto']))
<p><b>{{ __('details.text_go_back') ?? '' }}<a href="{{ $vm->requestFlags['returnto'] }}">{{ __('details.text_whence_you_came') ?? '' }}</a></b></p>
@endif
@endif

<h1 class="text-center" id="top">{{ $vm->details->title->name }}@if ($vm->details->title->banned) <b>(<span class="striking">{{ __('functions.text_banned') }}</span>)</b>@endif@if ($vm->details->title->badges->paid)<x-torrent.paid-icon :size="20" />@endif@if ($vm->details->title->badges->promotion !== null)&nbsp;&nbsp;&nbsp;<x-torrent.promotion :badge="$vm->details->title->badges->promotion" />@endif@if ($vm->details->title->badges->hitAndRun)<i class="hitandrun" role="img" aria-label="{{ __('label.hit_and_run.label') }}" title="{{ __('label.hit_and_run.label') }}"></i>@endif@if ($vm->details->title->badges->approval !== null)<span title="{{ $vm->details->title->badges->approval->title }}">{{ $vm->details->title->badges->approval->icon }}</span>@endif</h1>

@if ($vm->details->denyBanner !== null)
@include('components.torrent.details.deny_banner', ['banner' => $vm->details->denyBanner])
@endif

<section class="nx-ucard nx-setcard" aria-labelledby="dt-sec-download">
<h2 class="nx-setcard__title" id="dt-sec-download">{{ __('details.section_download_actions') }}</h2>
<x-data-table :caption="$vm->details->title->name" captionHidden class="nxm-kv">
@if ($vm->details->downloadAllowed)
<tr><td class="w-[1%] whitespace-nowrap align-top px-2.5 py-1.5 text-right font-semibold text-nxm-text-dim">{{ __('details.row_download') }}</td><td class="align-top px-2.5 py-1.5"><a class="index" href="/download?id={{ $vm->torrentId }}">{{ ($vm->torrentNamePrefix ?? '').'.'.$vm->details->saveAs }}.torrent</a>&nbsp;&nbsp;<x-torrent.bookmark :torrent-id="$vm->torrentId" :bookmarked="$vm->torrentBookmarked" />&nbsp;&nbsp;&nbsp;{{ __('details.row_upped_by') }}&nbsp;@if ($vm->details->owner->anonymous)<i>{{ __('details.text_anonymous') }}</i>@if ($vm->details->owner->showUsername) ({{ $vm->details->owner->username }})@endif@elseif ($vm->details->owner->username !== null){{ $vm->details->owner->username }}@else<i></i>@endif{{ $vm->details->uploadTimePrefix }}{{ $vm->details->uploadTime }}</td></tr>
@else
<x-settings-row :label="__('details.row_download')">{{ __('details.text_downloading_not_allowed') ?? '' }}</x-settings-row>
@endif
<x-settings-row :label="__('details.row_action')">@include('components.torrent.details.actions', ['details' => $vm->details])</x-settings-row>
@if ($vm->details->downloadAllowed)
<x-settings-row :label="__('details.torrent_dl_url')"><button type="button" class="nx-btn nx-btn--sm" data-copy-value="{{ $vm->downloadUrl }}" data-copy-done="{{ __('details.torrent_dl_url_copied') }}" data-copy-fail="{{ __('details.torrent_dl_url_copy_failed') }}" title="{{ __('details.torrent_dl_url_notice') }}">{{ __('details.torrent_dl_url_copy') }}</button><span class="nx-sr-only" role="status" data-copy-live></span></x-settings-row>
@endif
</x-data-table>
</section>

<section class="nx-ucard nx-setcard" aria-labelledby="dt-sec-info">
<h2 class="nx-setcard__title" id="dt-sec-info">{{ __('details.section_info') }}</h2>
<x-data-table :caption="$vm->details->title->name" captionHidden class="nxm-kv">
<x-settings-row :label="__('details.row_basic_info')"><b>{{ __('details.text_size') }}</b>{{ \App\Support\Format::size((float) $vm->torrentRow['size']) }}&nbsp;&nbsp;&nbsp;<b>{{ __('details.row_type') }}:</b>&nbsp;{{ $vm->torrentRow['cat_name'] }}@foreach ($vm->details->taxonomy as $entry)&nbsp;&nbsp;&nbsp;<b>{{ $entry->label }}: </b>{{ $entry->value }}@endforeach</x-settings-row>
@if (! $vm->tagHtml->isEmpty())
<x-settings-row :label="__('details.row_tags')">{{ $vm->tagHtml }}</x-settings-row>
@endif
{{ $vm->customFieldsHtml }}
@if ($vm->technicalInfoResult !== null)
<x-settings-row :label="__('functions.text_technical_info')">{{ $vm->technicalInfoResult }}</x-settings-row>
@endif
@if (count($vm->screenshots ?? []) > 0)
<x-settings-row :label="__('details.row_screenshots')">
    <div class="nxm-screens">
    @foreach ($vm->screenshots as $shot)
        <a class="nxm-screens__item" href="{{ $shot }}" target="_blank" rel="noopener"><img src="{{ $shot }}" alt="{{ $vm->details->title->name }}" loading="lazy" data-zoomable /></a>
    @endforeach
    </div>
</x-settings-row>
@endif
@if ($vm->showDescription)
<livewire:descr-row :descr-raw="$vm->descrRaw" :show-or-hide-title="$vm->details->showOrHideTitle" />
@endif
</x-data-table>
</section>

<section class="nx-ucard nx-setcard" aria-labelledby="dt-sec-files">
<h2 class="nx-setcard__title" id="dt-sec-files">{{ __('details.section_files_peers') }}</h2>
<x-data-table :caption="$vm->details->title->name" captionHidden class="nxm-kv">
<x-settings-row :label="__('details.row_torrent_info')"><div class="nxm-detail-cells">@if ($vm->details->info->numFiles !== null)<div><b>{{ __('details.text_num_files') }}</b>{{ $vm->details->info->numFiles }}{{ __('details.text_files') }}<br /></div>@endif<div><b>{{ __('details.row_info_hash') }}:</b>&nbsp;{{ $vm->details->info->infoHash }}</div>@if ($vm->details->info->showStructure)<div><b>{{ __('details.text_torrent_structure') }}</b><a href="/web/torrent_info?id={{ $vm->details->info->torrentId }}">{{ __('details.text_torrent_info_note') }}</a></div>@endif</div><livewire:torrent-file-list :torrent-id="$vm->details->info->torrentId" /></x-settings-row>
<livewire:peer-list :torrent-id="$vm->details->peers->torrentId" :seeders="$vm->details->peers->seeders" :leechers="$vm->details->peers->leechers" :open-on-load="$vm->requestFlags['dllist'] ?? false" />
</x-data-table>
</section>

<section class="nx-ucard nx-setcard" aria-labelledby="dt-sec-community">
<h2 class="nx-setcard__title" id="dt-sec-community">{{ __('details.section_community') }}</h2>
<x-data-table :caption="$vm->details->title->name" captionHidden class="nxm-kv">
<x-settings-row :label="__('details.row_hot_meter')"><div class="nxm-detail-cells"><div><b>{{ __('details.text_views') }}</b>{{ $vm->details->hotMeter->views }}</div><div><b>{{ __('details.text_hits') }}</b>{{ $vm->details->hotMeter->hits }}</div><div><b>{{ __('details.text_snatched') }}</b><a href="/web/viewsnatches?id={{ $vm->details->hotMeter->torrentId }}"><b>{{ $vm->details->hotMeter->timesCompleted }}{{ $vm->details->hotMeter->snatchesPre }}</b>{{ $vm->details->hotMeter->snatchesPost }}</a></div><div><b>{{ $vm->details->hotMeter->lastSeederLabel }}</b>{{ $vm->details->hotMeter->lastSeeder }}</div></div></x-settings-row>
<x-settings-row :label="__('details.magic_value_award')"><livewire:magic-section :torrent-id="$vm->torrentId" /></x-settings-row>
<x-settings-row :label="__('details.row_thanks_by')"><livewire:thanks-section :torrent-id="$vm->torrentId" /></x-settings-row>
</x-data-table>
</section>
@else
<h2 id="top">{{ __('details.text_comments_for') ?? '' }}<a href="/web/details/{{ $vm->torrentId }}">{{ $vm->torrentRow['name'] }}</a></h2>
@endif

@include('components.torrent.comments', ['commentCount' => $vm->commentCount, 'commentPager' => $vm->commentPager, 'commentsEnabled' => $vm->commentsEnabled, 'id' => $vm->id])
@endsection

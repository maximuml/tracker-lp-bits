@extends('layouts.modern')

@section('title', $headTitle)

@section('content')
@if (empty($requestFlags['cmtpage']))
@if (! empty($requestFlags['uploaded']))
<h1 align="center">{{ __('legacy/details.text_successfully_uploaded') ?? '' }}</h1>
<p>{{ \App\Support\Html\SafeHtml::fromUntrustedHtml(__('legacy/details.text_redownload_torrent_note')) }}</p>
@elseif (! empty($requestFlags['edited']))
<h1 align="center">{{ __('legacy/details.text_successfully_edited') ?? '' }}</h1>
@if (! empty($requestFlags['returnto']))
<p><b>{{ __('legacy/details.text_go_back') ?? '' }}<a href="{{ $requestFlags['returnto'] }}">{{ __('legacy/details.text_whence_you_came') ?? '' }}</a></b></p>
@endif
@elseif (! empty($requestFlags['existed']))
<h1 align="center">{{ __('legacy/details.torrent_existed') ?? '' }}</h1>
@if (! empty($requestFlags['returnto']))
<p><b>{{ __('legacy/details.text_go_back') ?? '' }}<a href="{{ $requestFlags['returnto'] }}">{{ __('legacy/details.text_whence_you_came') ?? '' }}</a></b></p>
@endif
@endif

<h1 align="center" id="top">{{ $torrentTopHtml }}</h1>

@if ($denyBannerHtml !== '')
{{ $denyBannerHtml }}
@endif

<table data-nx="data" width="97%" cellspacing="0" cellpadding="5">
@if ($downloadAllowed)
<tr><td class="rowhead" width="13%">{{ __('legacy/details.row_download') }}</td><td class="rowfollow" width="87%" align="left"><a class="index" href="download.php?id={{ $torrentId }}">{{ ($torrentNamePrefix ?? '').'.'.$torrentRow['save_as'] }}.torrent</a>&nbsp;&nbsp;<a id="bookmark0" href="#" data-bookmark-torrent="{{ $torrentRow['id'] }}" data-bookmark-counter="0">{{ $bookmarkMarkup }}</a>&nbsp;&nbsp;&nbsp;{{ __('legacy/details.row_upped_by') }}&nbsp;{{ $uprow }}{{ $uploadTime }}</td></tr>
@else
<x-settings-row :label="__('legacy/details.row_download')">{{ __('legacy/details.text_downloading_not_allowed') ?? '' }}</x-settings-row>
@endif
@if ($tagHtml !== '')
<x-settings-row :label="__('legacy/details.row_tags')">{{ $tagHtml }}</x-settings-row>
@endif
<x-settings-row :label="__('legacy/details.row_basic_info')"><b>{{ __('legacy/details.text_size') }}</b>{{ \App\Support\Format::size((float) $torrentRow['size']) }}&nbsp;&nbsp;&nbsp;<b>{{ __('legacy/details.row_type') }}:</b>&nbsp;{{ $torrentRow['cat_name'] }}{{ $taxonomyRendered }}</x-settings-row>
<x-settings-row :label="__('legacy/details.row_action')">{{ $actionsHtml }}</x-settings-row>
<x-settings-row :label="__('legacy/details.torrent_dl_url')"><a title="{{ __('legacy/details.torrent_dl_url_notice') ?? '' }}" href="{{ $downloadUrl }}">{{ __('legacy/details.torrent_dl_url_text') }}</a></x-settings-row>
{{ $customFieldsHtml }}
@if (! empty($technicalInfoResult))
<x-settings-row :label="__('legacy/functions.text_technical_info')">{{ $technicalInfoResult }}</x-settings-row>
@endif
@if ($showDescription)
<x-settings-row :label="$descrHeadHtml"><div id='kdescr'>{{ $descr }}</div></x-settings-row>
@endif
<x-settings-row :label="__('legacy/details.row_torrent_info')">{{ $torrentInfoRowHtml }}</x-settings-row>
<x-settings-row :label="__('legacy/details.row_hot_meter')">{{ $hotMeterHtml }}</x-settings-row>
<x-settings-row :label="$peersHeadHtml">{{ $peersBodyHtml }}</x-settings-row>
<x-settings-row :label="__('legacy/details.magic_value_award')">{{ $magicRowHtml }}</x-settings-row>
<x-settings-row :label="__('legacy/details.row_thanks_by')">{{ $thanksRowHtml }}</x-settings-row>
</table>
@else
<h1 id="top">{{ __('legacy/details.text_comments_for') ?? '' }}<a href="details.php?id={{ $torrentId }}">{{ $torrentRow['name'] }}</a></h1>
@endif

@include('torrent._comments')
@endsection

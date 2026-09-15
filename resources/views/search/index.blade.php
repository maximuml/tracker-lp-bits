@extends('layouts.legacy')

@section('title', \App\Support\Locale::trans('search.global_search', [], null))

@section('content')
<div class="nx-main nx-embedded nx-w-97">
@if (! empty($hasResults))
    @safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml($pagertop ?? ''))
    @safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml(\App\Support\TorrentTable::render($rows ?? [])))
    @safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml($pagerbottom ?? ''))
@elseif (($search ?? '') !== '')
    @safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml(\App\Support\Frame::stdMessage(($lang_torrents['std_search_results_for'] ?? '').($searchstr_ori ?? '').'"', $lang_torrents['std_try_again'] ?? '', false)))
@endif
</div>
@endsection

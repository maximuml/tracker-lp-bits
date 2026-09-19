@if ($hasData)
    <br/><div class="nx-flex-between"><div>{{ $summaryHtml }}</div><div></div></div>
    {{ $pagertop }}@if ($userTorrentListVm !== null)@include('getusertorrentlistajax._table')@endif{{ $pagerbottom }}
@else
    {{ __('legacy/getusertorrentlistajax.text_no_record') }}
@endif

@if ($hasData)
    <br/><div class="nx-flex-between"><div><b>{{ $summaryCount }}</b>{{ $summaryText }}</div><div></div></div>
    {{ $pagertop }}@if ($userTorrentListVm !== null)@include('getusertorrentlistajax._table')@endif{{ $pagerbottom }}
@else
    {{ __('getusertorrentlistajax.text_no_record') }}
@endif

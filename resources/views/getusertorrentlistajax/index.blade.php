@if ($hasData)
    <br/><div class="nx-flex-between"><div><b>{{ $summaryCount }}</b>{{ $summaryText }}</div><div></div></div>
    @if (($pager ?? []) !== [])<x-pagination :page="$pager['page']" :pages="$pager['pages']" :href="$pager['href']" :prev-label="$pager['prevLabel']" :next-label="$pager['nextLabel']" :zero-based="$pager['zeroBased']" label="Pagination top" />@endif@if ($userTorrentListVm !== null)@include('components.getusertorrentlistajax.table')@endif@if (($pager ?? []) !== [])<x-pagination :page="$pager['page']" :pages="$pager['pages']" :href="$pager['href']" :prev-label="$pager['prevLabel']" :next-label="$pager['nextLabel']" :zero-based="$pager['zeroBased']" label="Pagination bottom" />@endif
@else
    {{ __('getusertorrentlistajax.text_no_record') }}
@endif

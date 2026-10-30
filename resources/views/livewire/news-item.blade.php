<div class="contents">
    <a href="#" wire:click.prevent="toggle">@if($leadingBreak)<br />@endif<i class="{{ $open ? 'minus' : 'plus' }}" role="img" aria-label="Show/Hide" title="{{ $showHideTitle }}"></i>&nbsp;{{ date('Y.m.d', strtotime($added)) }} - <b>{{ $title }}</b></a>
    <div @if(!$open) class="nx-hidden" @endif> {{ \App\Support\Format::formatComment($body, 0) }} </div>
    @if($canManage)
    &nbsp; [<a class="faqlink" href="/web/news?action=edit&amp;newsid={{ $itemId }}"><b>{{ $editLabel }}</b></a>]
    <form method="post" action="/web/news/delete" class="nx-inline">@csrf<input type="hidden" name="newsid" value="{{ $itemId }}" /><input type="hidden" name="sure" value="1" /><button type="submit" class="faqlink"><b>{{ $deleteLabel }}</b></button></form>
    @endif
</div>

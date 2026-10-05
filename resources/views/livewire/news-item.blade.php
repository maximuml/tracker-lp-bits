<div class="contents">
    <a href="#" wire:click.prevent="toggle">@if($leadingBreak)<br />@endif<img class="{{ $open ? 'minus' : 'plus' }}" src="pic/trans.gif" alt="Show/Hide" title="{{ $showHideTitle }}" />&nbsp;{{ date('Y.m.d', strtotime($added)) }} - <b>{{ $title }}</b></a>
    <div @if(!$open) class="nx-hidden" @endif> {{ \App\Support\Format::formatComment($body, 0) }} </div>
    &nbsp; [<a class="faqlink" href="news.php?action=edit&amp;newsid={{ $itemId }}"><b>{{ $editLabel }}</b></a>]
    <form method="post" action="/news" class="inline">@csrf<input type="hidden" name="action" value="delete" /><input type="hidden" name="newsid" value="{{ $itemId }}" /><input type="hidden" name="sure" value="1" /><button type="submit" class="faqlink"><b>{{ $deleteLabel }}</b></button></form>
</div>

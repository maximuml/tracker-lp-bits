@if($news['show'])
<h2>{{ $news['title'] }}
    @if($news['canManage'])
        - <font class="small">[<a class="altlink" href="news.php"><b>{{ $news['manageLink'] }}</b></a>]</font>
    @endif
</h2>
@if(count($news['items']) > 0)
<table width="100%"><tr><td class="text"><div style="margin-left: 16pt;">
@php $news_flag = 0; @endphp
@foreach($news['items'] as $newsItem)
    @if($news_flag < 1)
        <a href="javascript: klappe_news('a{{ $newsItem['id'] }}')"><img class="minus" src="pic/trans.gif" id="pica{{ $newsItem['id'] }}" alt="Show/Hide" title="{{ $news['showHideTitle'] }}" />&nbsp;{{ date('Y.m.d', strtotime($newsItem['added'])) }} - <b>{{ $newsItem['title'] }}</b></a>
        <div id="ka{{ $newsItem['id'] }}" style="display: block;"> {{ \App\Support\Format::formatComment($newsItem['body'], 0) }} </div>
        @php $news_flag++; @endphp
    @else
        <a href="javascript: klappe_news('a{{ $newsItem['id'] }}')"><br /><img class="plus" src="pic/trans.gif" id="pica{{ $newsItem['id'] }}" alt="Show/Hide" title="{{ $news['showHideTitle'] }}" />&nbsp;{{ date('Y.m.d', strtotime($newsItem['added'])) }} - <b>{{ $newsItem['title'] }}</b></a>
        <div id="ka{{ $newsItem['id'] }}" style="display: none;"> {{ \App\Support\Format::formatComment($newsItem['body'], 0) }} </div>
    @endif
    &nbsp; [<a class="faqlink" href="news.php?action=edit&amp;newsid={{ $newsItem['id'] }}"><b>{{ $news['editLabel'] }}</b></a>]
    [<a class="faqlink" href="news.php?action=delete&amp;newsid={{ $newsItem['id'] }}"><b>{{ $news['deleteLabel'] }}</b></a>]
@endforeach
</div></td></tr></table>
@endif
@endif

@if($polls['show'])
<h2>{{ $polls['title'] }}
    @if($polls['canManage'])
        <font class="small"> - [<a class="altlink" href="makepoll.php?returnto=main"><b>{{ $polls['newLabel'] }}</b></a>]
        @if($polls['exists'])
             - [<a class="altlink" href="makepoll.php?action=edit&amp;pollid={{ $polls['pollId'] }}&amp;returnto=main"><b>{{ $polls['editLabel'] }}</b></a>]
             - [<a class="altlink" href="log.php?action=poll&amp;do=delete&amp;pollid={{ $polls['pollId'] }}&amp;returnto=main"><b>{{ $polls['deleteLabel'] }}</b></a>]
             - [<a class="altlink" href="polloverview.php?id={{ $polls['pollId'] }}"><b>{{ $polls['detailLabel'] }}</b></a>]
        @endif
        </font>
    @endif
</h2>
@if($polls['exists'])
<table width="100%"><tr><td class="text" align="center">
<table width="59%" class="main" border="1" cellspacing="0" cellpadding="5"><tr><td class="text" align="left">
<p align="center"><b>{{ $polls['question'] }}</b></p>
@if($polls['hasVoted'])
    <table class="main" width="100%" border="0" cellspacing="0" cellpadding="0">
    @foreach($polls['bars'] as $bar)
        <tr><td width="1%" class="embedded nowrap">{{ $bar['option'] }}&nbsp;&nbsp;</td><td width="99%" class="embedded nowrap"><img class="bar_end" src="pic/trans.gif" alt="" /><img class="{{ $bar['selected'] ? 'sltbar' : 'unsltbar' }}" src="pic/trans.gif" style="width: {{ $bar['width'] }}px;" alt="" /><img class="bar_end" src="pic/trans.gif" alt="" /> {{ $bar['percent'] }}%</td></tr>
    @endforeach
    </table>
    <p align="center">{{ $polls['votesLabel'] }} {{ $polls['totalVotes'] }}</p>
    @if($polls['canLog'])
        <p align="center"><a href="log.php?action=poll">{{ $polls['previousPollsLabel'] }}</a></p>
    @endif
@else
    <form method="post" action="index.php">
    <input type="hidden" name="_token" value="{{ csrf_token() }}" />
    @foreach($polls['options'] as $i => $option)
        <input type="radio" name="choice" value="{{ $i }}">{{ $option }}<br />
    @endforeach
    <br />
    <input type="radio" name="choice" value="255">{{ $polls['blankVoteLabel'] }}<br />
    <p align="center"><input type="submit" class="btn" value="{{ $polls['submitVoteLabel'] }}" /></p>
    </form>
@endif
</td></tr></table>
</td></tr></table>
@endif
@endif

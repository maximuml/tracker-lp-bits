@if($forumPosts['show'])
<h2>{{ $forumPosts['title'] }}</h2>
<table width="100%" border="1" cellspacing="0" cellpadding="5"><tr><td class="colhead" width="100%" align="left">{{ $forumPosts['colTopicTitle'] }}</td><td class="colhead" align="center">{{ $forumPosts['colView'] }}</td><td class="colhead" align="center">{{ $forumPosts['colAuthor'] }}</td><td class="colhead" align="left">{{ $forumPosts['colPostedAt'] }}</td></tr>
@foreach($forumPosts['items'] as $postsx)
<tr><td><a href="forums.php?action=viewtopic&amp;topicid={{ $postsx['tid'] }}&amp;page=p{{ $postsx['pid'] }}#pid{{ $postsx['pid'] }}"><b>{{ htmlspecialchars($postsx['subject']) }}</b></a><br />{{ $forumPosts['textIn'] }}<a href="forums.php?action=viewforum&amp;forumid={{ $postsx['forumid'] }}">{{ htmlspecialchars($postsx['name']) }}</a></td><td align="center">{{ $postsx['views'] }}</td><td align="center">{!! \App\Support\UserDisplay::username($postsx['userpost']) !!}</td><td>{{ \App\Support\Time::format($postsx['added']) }}</td></tr>
@endforeach
</table>
@endif

@if($forumPosts['show'])
<h2>{{ $forumPosts['title'] }}</h2>
<table data-nx="data" width="100%" border="1" cellspacing="0" cellpadding="5"><tr><th class="colhead" width="100%" align="left" scope="col">{{ $forumPosts['colTopicTitle'] }}</th><th class="colhead" align="center" scope="col">{{ $forumPosts['colView'] }}</th><th class="colhead" align="center" scope="col">{{ $forumPosts['colAuthor'] }}</th><th class="colhead" align="left" scope="col">{{ $forumPosts['colPostedAt'] }}</th></tr>
@foreach($forumPosts['items'] as $postsx)
<tr><td><a href="forums.php?action=viewtopic&amp;topicid={{ $postsx['tid'] }}&amp;page=p{{ $postsx['pid'] }}#pid{{ $postsx['pid'] }}"><b>{{ $postsx['subject'] }}</b></a><br />{{ $forumPosts['textIn'] }}<a href="forums.php?action=viewforum&amp;forumid={{ $postsx['forumid'] }}">{{ $postsx['name'] }}</a></td><td align="center">{{ $postsx['views'] }}</td><td align="center">{{ \App\Support\UserDisplay::username($postsx['userpost']) }}</td><td><x-time :value="$postsx['added']" /></td></tr>
@endforeach
</table>
@endif

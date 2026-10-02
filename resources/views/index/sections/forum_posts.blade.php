@if($forumPosts->show)
<section class="nx-idx-card">
<h2>{{ $forumPosts->title }}</h2>
@if(count($forumPosts->items) === 0)
<x-empty-state :title="__('legacy/index.text_no_topics')" />
@else
<table data-nx="data"><caption class="nx-sr-only">{{ $forumPosts->title }}</caption><tr><th class="colhead nx-w-99p nx-align-left" scope="col">{{ $forumPosts->colTopicTitle }}</th><th class="colhead" scope="col">{{ $forumPosts->colView }}</th><th class="colhead" scope="col">{{ $forumPosts->colAuthor }}</th><th class="colhead nx-align-left" scope="col">{{ $forumPosts->colPostedAt }}</th></tr>
@foreach($forumPosts->items as $postsx)
<tr><td><a href="forums.php?action=viewtopic&amp;topicid={{ $postsx->tid }}&amp;page=p{{ $postsx->pid }}#pid{{ $postsx->pid }}"><b>{{ $postsx->subject }}</b></a><br />{{ $forumPosts->textIn }}<a href="forums.php?action=viewforum&amp;forumid={{ $postsx->forumid }}">{{ $postsx->name }}</a></td><td class="nx-center">{{ $postsx->views }}</td><td class="nx-center">{{ \App\Support\UserDisplay::username($postsx->userpost) }}</td><td><x-time :value="$postsx->added" /></td></tr>
@endforeach
</table>
@endif
</section>
@endif

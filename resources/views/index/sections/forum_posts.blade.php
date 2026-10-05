@if($forumPosts->show)
<section class="nx-idx-card">
<h2>{{ $forumPosts->title }}</h2>
@if(count($forumPosts->items) === 0)
<x-empty-state :title="__('legacy/index.text_no_topics')" />
@else
<x-data-table :caption="$forumPosts->title" captionHidden>
    <x-slot:head>
        <thead>
            <tr><th class="w-[99%] text-left" scope="col">{{ $forumPosts->colTopicTitle }}</th><th scope="col">{{ $forumPosts->colView }}</th><th scope="col">{{ $forumPosts->colAuthor }}</th><th class="text-left" scope="col">{{ $forumPosts->colPostedAt }}</th></tr>
        </thead>
    </x-slot:head>
@foreach($forumPosts->items as $postsx)
<tr><td><a href="forums.php?action=viewtopic&amp;topicid={{ $postsx->tid }}&amp;page=p{{ $postsx->pid }}#pid{{ $postsx->pid }}"><b>{{ $postsx->subject }}</b></a><br />{{ $forumPosts->textIn }}<a href="forums.php?action=viewforum&amp;forumid={{ $postsx->forumid }}">{{ $postsx->name }}</a></td><td class="text-center">{{ $postsx->views }}</td><td class="text-center">{{ \App\Support\UserDisplay::username($postsx->userpost) }}</td><td><x-time :value="$postsx->added" /></td></tr>
@endforeach
</x-data-table>
@endif
</section>
@endif

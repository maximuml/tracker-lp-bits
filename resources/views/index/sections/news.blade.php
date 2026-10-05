@if($news->show)
<section class="nx-idx-card">
<h2>{{ $news->title }}
    @if($news->canManage)
        - <span class="small">[<a class="altlink" href="/web/news"><b>{{ $news->manageLink }}</b></a>]</span>
    @endif
</h2>
@if(count($news->items) === 0)
<x-empty-state :title="__('legacy/index.text_no_news')" />
@else
<div class="p-[10pt]"><div>
@foreach($news->items as $newsItem)
    <livewire:news-item :item-id="$newsItem->id" :added="$newsItem->added" :title="$newsItem->title" :body="$newsItem->body" :edit-label="$news->editLabel" :delete-label="$news->deleteLabel" :show-hide-title="$news->showHideTitle" :leading-break="! $loop->first" :open="$loop->first" :key="'news-item-'.$newsItem->id" />
@endforeach
</div></div>
@endif
</section>
@endif

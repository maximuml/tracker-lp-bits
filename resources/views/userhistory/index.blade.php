@extends('layouts.app')

@section('title', $vm->title ?? 'User history')

@section('content')
@if ($vm->action === 'viewposts')
<x-page :title="__('userhistory.text_posts_history_for')" :subtitle="$vm->subject" width="list">
    @if (($vm->postcount ?? 0) > ($vm->perpage ?? 15))
        <x-pagination :page="$vm->pager['page']" :pages="$vm->pager['pages']" :href="$vm->pager['href']" :prev-label="$vm->pager['prevLabel']" :next-label="$vm->pager['nextLabel']" :zero-based="$vm->pager['zeroBased']" label="Pagination top" />
    @endif
    <x-frame :center="false">
    @if (empty($vm->items))
        <x-empty-state :title="__('userhistory.std_no_posts_found')" />
    @else
    @foreach ($vm->items ?? [] as $item)
        <p class="sub">
        {{ $item['added'] }}&nbsp;--&nbsp;<b>{{ __('userhistory.text_forum') }}&nbsp;</b>
        <a href="/forums?action=viewforum&forumid={{ $item['forumid'] }}">{{ $item['forumname'] }}</a>
        &nbsp;--&nbsp;<b>{{ __('userhistory.text_topic') }}&nbsp;</b>
        <a href="/forums?action=viewtopic&topicid={{ $item['topicid'] }}">{{ $item['topicname'] }}</a>
        &nbsp;--&nbsp;<b>{{ __('userhistory.text_post') }}&nbsp;</b>
        <a href="/forums?action=viewtopic&topicid={{ $item['topicid'] }}&page=p{{ $item['postid'] }}#pid{{ $item['postid'] }}">#{{ $item['postid'] }}</a>
        @if ($item['isNew']) &nbsp;<b>(<span class="new">{{ __('userhistory.text_new')}}</span>)</b>@endif
        </p>
        <br />
        <x-data-table :caption="__('userhistory.text_posts_history_for') . $vm->subjectName" captionHidden class="nx-main">
        <tr class="align-top"><td class="comment">{{ $item['bodyHtml'] }}</td></tr>
        </x-data-table>
        <br />
    @endforeach
    @endif
    </x-frame>
    @if (($vm->postcount ?? 0) > ($vm->perpage ?? 15))
        <x-pagination :page="$vm->pager['page']" :pages="$vm->pager['pages']" :href="$vm->pager['href']" :prev-label="$vm->pager['prevLabel']" :next-label="$vm->pager['nextLabel']" :zero-based="$vm->pager['zeroBased']" label="Pagination bottom" />
    @endif
</x-page>
@elseif ($vm->action === 'viewcomments')
<x-page :title="__('userhistory.text_comments_history_for')" :subtitle="$vm->subject" width="list">
    @if (($vm->commentcount ?? 0) > ($vm->perpage ?? 15))
        <x-pagination :page="$vm->pager['page']" :pages="$vm->pager['pages']" :href="$vm->pager['href']" :prev-label="$vm->pager['prevLabel']" :next-label="$vm->pager['nextLabel']" :zero-based="$vm->pager['zeroBased']" label="Pagination top" />
    @endif
    <x-frame :center="false">
    @if (empty($vm->items))
        <x-empty-state :title="__('userhistory.std_no_comments_found')" />
    @else
    @foreach ($vm->items ?? [] as $item)
        <p class="sub">
        {{ $item['added'] }}&nbsp;---&nbsp;<b>{{ __('userhistory.text_torrent') }}&nbsp;</b>
        @if ($item['torrentName'] !== '')
            <a href="/web/details/{{ $item['torrentid'] }}?tocomm=1&hit=1">{{ $item['torrentName'] }}</a>
        @else
            [Deleted]
        @endif
        &nbsp;---&nbsp;<b>{{ __('userhistory.text_comment') }}&nbsp;</b>#<a href="/web/details/{{ $item['torrentid'] }}?tocomm=1&hit=1{{ $item['pageUrl'] }}">{{ $item['commentid'] }}</a>
        </p>
        <br />
        <x-data-table :caption="__('userhistory.text_comments_history_for') . $vm->subjectName" captionHidden class="nx-main">
        <tr class="align-top"><td class="comment">{{ $item['bodyHtml'] }}</td></tr>
        </x-data-table>
        <br />
    @endforeach
    @endif
    </x-frame>
    @if (($vm->commentcount ?? 0) > ($vm->perpage ?? 15))
        <x-pagination :page="$vm->pager['page']" :pages="$vm->pager['pages']" :href="$vm->pager['href']" :prev-label="$vm->pager['prevLabel']" :next-label="$vm->pager['nextLabel']" :zero-based="$vm->pager['zeroBased']" label="Pagination bottom" />
    @endif
</x-page>
@endif
@endsection

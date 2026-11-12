@extends('layouts.app')

@section('title', $title ?? 'User history')

@section('content')
@if ($action === 'viewposts')
<x-page :title="__('userhistory.text_posts_history_for')" :subtitle="$subject" width="list">
    @if (($postcount ?? 0) > ($perpage ?? 15))
        <x-pagination :page="$pager['page']" :pages="$pager['pages']" :href="$pager['href']" :prev-label="$pager['prevLabel']" :next-label="$pager['nextLabel']" :zero-based="$pager['zeroBased']" label="Pagination top" />
    @endif
    <x-frame :center="false">
    @if (empty($items))
        <x-empty-state :title="__('userhistory.std_no_posts_found')" />
    @else
    @foreach ($items ?? [] as $item)
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
        <x-data-table :caption="__('userhistory.text_posts_history_for') . $subjectName" captionHidden class="nx-main">
        <tr class="align-top"><td class="comment">{{ $item['bodyHtml'] }}</td></tr>
        </x-data-table>
        <br />
    @endforeach
    @endif
    </x-frame>
    @if (($postcount ?? 0) > ($perpage ?? 15))
        <x-pagination :page="$pager['page']" :pages="$pager['pages']" :href="$pager['href']" :prev-label="$pager['prevLabel']" :next-label="$pager['nextLabel']" :zero-based="$pager['zeroBased']" label="Pagination bottom" />
    @endif
</x-page>
@elseif ($action === 'viewcomments')
<x-page :title="__('userhistory.text_comments_history_for')" :subtitle="$subject" width="list">
    @if (($commentcount ?? 0) > ($perpage ?? 15))
        <x-pagination :page="$pager['page']" :pages="$pager['pages']" :href="$pager['href']" :prev-label="$pager['prevLabel']" :next-label="$pager['nextLabel']" :zero-based="$pager['zeroBased']" label="Pagination top" />
    @endif
    <x-frame :center="false">
    @if (empty($items))
        <x-empty-state :title="__('userhistory.std_no_comments_found')" />
    @else
    @foreach ($items ?? [] as $item)
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
        <x-data-table :caption="__('userhistory.text_comments_history_for') . $subjectName" captionHidden class="nx-main">
        <tr class="align-top"><td class="comment">{{ $item['bodyHtml'] }}</td></tr>
        </x-data-table>
        <br />
    @endforeach
    @endif
    </x-frame>
    @if (($commentcount ?? 0) > ($perpage ?? 15))
        <x-pagination :page="$pager['page']" :pages="$pager['pages']" :href="$pager['href']" :prev-label="$pager['prevLabel']" :next-label="$pager['nextLabel']" :zero-based="$pager['zeroBased']" label="Pagination bottom" />
    @endif
</x-page>
@endif
@endsection

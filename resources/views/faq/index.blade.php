@extends('layouts.legacy')

@section('title', $lang_faq['head_faq'] ?? '')

@section('content')
@if (! empty($faqCategories))
    @php \App\Support\Html::beginFrame($lang_faq['text_welcome_to'].$SITENAME." - ".$SLOGAN); @endphp
    {!! $lang_faq['text_welcome_content_one'] !!}
    {!! sprintf($lang_faq['text_welcome_content_two'], $SITENAME, $SITENAME) !!}
    @php \App\Support\Html::endFrame(); @endphp

    @php \App\Support\Html::beginFrame("<span id=\"top\">".$lang_faq['text_contents']."</span>"); @endphp
    <ul>
    @foreach ($faqCategories as $id => $temp)
        @if ($faqCategories[$id]['flag'] == "1")
            <li><a href="#id{{ $faqCategories[$id]['link_id'] }}"><b>{{ $faqCategories[$id]['title'] }}</b></a>
            <ul>
            @if (isset($faqCategories[$id]['items']))
                @foreach ($faqCategories[$id]['items'] as $id2 => $tempItem)
                    @if ($faqCategories[$id]['items'][$id2]['flag'] == "1")
                        <li><a href="#id{{ $faqCategories[$id]['items'][$id2]['link_id'] }}" class="faqlink">{{ $faqCategories[$id]['items'][$id2]['question'] }}</a></li>
                    @elseif ($faqCategories[$id]['items'][$id2]['flag'] == "2")
                        <li><a href="#id{{ $faqCategories[$id]['items'][$id2]['link_id'] }}" class="faqlink">{{ $faqCategories[$id]['items'][$id2]['question'] }}</a> <img class="faq_updated" src="pic/trans.gif" alt="Updated" /></li>
                    @elseif ($faqCategories[$id]['items'][$id2]['flag'] == "3")
                        <li><a href="#id{{ $faqCategories[$id]['items'][$id2]['link_id'] }}" class="faqlink">{{ $faqCategories[$id]['items'][$id2]['question'] }}</a> <img class="faq_new" src="pic/trans.gif" alt="New" /></li>
                    @endif
                @endforeach
            @endif
            </ul></li>
            <br />
        @endif
    @endforeach
    </ul>
    @php \App\Support\Html::endFrame(); @endphp

    @foreach ($faqCategories as $id => $temp)
        @if ($faqCategories[$id]['flag'] == "1")
            @php
            $frame = $faqCategories[$id]['title'] ." - <a href=\"#top\"><img class=\"top\" src=\"pic/trans.gif\" alt=\"Top\" title=\"Top\" /></a>";
            \App\Support\Html::beginFrame($frame);
            @endphp
            <span id="id{{ $faqCategories[$id]['link_id'] }}"></span>
            @if (isset($faqCategories[$id]['items']))
                @foreach ($faqCategories[$id]['items'] as $id2 => $tempItem)
                    @if ($faqCategories[$id]['items'][$id2]['flag'] != "0")
                        <br /><span id="id{{ $faqCategories[$id]['items'][$id2]['link_id'] }}"><b>{{ $faqCategories[$id]['items'][$id2]['question'] }}</b></span><br />
                        <br />{!! strip_tags($faqCategories[$id]['items'][$id2]['answer'], '<a><b><i><u><s><br><p><div><span><ul><ol><li><img><font><pre><code><hr><table><tr><td><th><strong><em><h1><h2><h3><h4><h5><h6><blockquote>') !!}<br /><br />
                    @endif
                @endforeach
            @endif
            @php \App\Support\Html::endFrame(); @endphp
        @endif
    @endforeach
@endif
@endsection

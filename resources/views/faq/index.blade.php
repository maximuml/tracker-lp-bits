@extends('layouts.legacy')

@section('title', __('legacy/faq.head_faq'))

@section('content')
@if (! empty($faqCategories))
    {{ \App\Support\Frame::open(__('legacy/faq.text_welcome_to').$SITENAME." - ".$SLOGAN, false, 10, '100%', 'left') }}
    @safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml(__('legacy/faq.text_welcome_content_one')))
    @safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml(sprintf(__('legacy/faq.text_welcome_content_two'), $SITENAME, $SITENAME)))
    {{ \App\Support\Frame::close() }}

    {{ \App\Support\Frame::open("<span id=\"top\">".(__('legacy/faq.text_contents'))."</span>", false, 10, '100%', 'left') }}
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
        @endif
    @endforeach
    </ul>
    <br />
    {{ \App\Support\Frame::close() }}

    @foreach ($faqCategories as $id => $temp)
        @if ($faqCategories[$id]['flag'] == "1")
            {{ \App\Support\Frame::open($faqCategories[$id]['title'] ." - <a href=\"#top\"><img class=\"top\" src=\"pic/trans.gif\" alt=\"Top\" title=\"Top\" /></a>", false, 10, '100%', 'left') }}
            <span id="id{{ $faqCategories[$id]['link_id'] }}"></span>
            @if (isset($faqCategories[$id]['items']))
                @foreach ($faqCategories[$id]['items'] as $id2 => $tempItem)
                    @if ($faqCategories[$id]['items'][$id2]['flag'] != "0")
                        <br /><span id="id{{ $faqCategories[$id]['items'][$id2]['link_id'] }}"><b>{{ $faqCategories[$id]['items'][$id2]['question'] }}</b></span><br />
                        <br />@safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml($faqCategories[$id]['items'][$id2]['answerHtml'] ?? ''))<br /><br />
                    @endif
                @endforeach
            @endif
            {{ \App\Support\Frame::close() }}
        @endif
    @endforeach
@endif
@endsection

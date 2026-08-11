@extends('layouts.legacy')

@section('title', $lang_faq['head_faq'] ?? '')

@section('content')
@php
$Cache->new_page('faq', 900, true);
$showFaq = ! $Cache->get_page();
$faq_categ = [];
if ($showFaq) {
    $Cache->add_whole_row();
    $lang_id = \App\Support\Locale::guestIdWithContext();
    $is_rulelang = \Nexus\Database\NexusDB::table('language')->where('id', $lang_id)->value('rule_lang');
    if (! $is_rulelang) {
        $lang_id = 6; // English
    }

    $res = \App\Models\Faq::query()->where('type', 'categ')->where('lang_id', $lang_id)->orderBy('order')->get()->toArray();
    foreach ($res as $arr) {
        $faq_categ[$arr['link_id']]['title'] = $arr['question'];
        $faq_categ[$arr['link_id']]['flag'] = $arr['flag'];
        $faq_categ[$arr['link_id']]['link_id'] = $arr['link_id'];
    }

    $res = \App\Models\Faq::query()->where('type', 'item')->where('lang_id', $lang_id)->get()->toArray();
    foreach ($res as $arr) {
        $faq_categ[$arr['categ']]['items'][$arr['id']]['question'] = $arr['question'];
        $faq_categ[$arr['categ']]['items'][$arr['id']]['answer'] = $arr['answer'];
        $faq_categ[$arr['categ']]['items'][$arr['id']]['flag'] = $arr['flag'];
        $faq_categ[$arr['categ']]['items'][$arr['id']]['link_id'] = $arr['link_id'];
    }
}
@endphp

@if ($showFaq && ! empty($faq_categ))
    @php \App\Support\Html::beginFrame($lang_faq['text_welcome_to'].$SITENAME." - ".$SLOGAN); @endphp
    {!! sprintf($lang_faq['text_welcome_content_one'].sprintf($lang_faq['text_welcome_content_two'], \App\Models\Setting::getSiteName(), \App\Models\Setting::getSiteName())) !!}
    @php \App\Support\Html::endFrame(); @endphp

    @php \App\Support\Html::beginFrame("<span id=\"top\">".$lang_faq['text_contents']."</span>"); @endphp
    <ul>
    @foreach ($faq_categ as $id => $temp)
        @if ($faq_categ[$id]['flag'] == "1")
            <li><a href="#id{{ $faq_categ[$id]['link_id'] }}"><b>{{ $faq_categ[$id]['title'] }}</b></a>
            <ul>
            @if (isset($faq_categ[$id]['items']))
                @foreach ($faq_categ[$id]['items'] as $id2 => $tempItem)
                    @if ($faq_categ[$id]['items'][$id2]['flag'] == "1")
                        <li><a href="#id{{ $faq_categ[$id]['items'][$id2]['link_id'] }}" class="faqlink">{{ $faq_categ[$id]['items'][$id2]['question'] }}</a></li>
                    @elseif ($faq_categ[$id]['items'][$id2]['flag'] == "2")
                        <li><a href="#id{{ $faq_categ[$id]['items'][$id2]['link_id'] }}" class="faqlink">{{ $faq_categ[$id]['items'][$id2]['question'] }}</a> <img class="faq_updated" src="pic/trans.gif" alt="Updated" /></li>
                    @elseif ($faq_categ[$id]['items'][$id2]['flag'] == "3")
                        <li><a href="#id{{ $faq_categ[$id]['items'][$id2]['link_id'] }}" class="faqlink">{{ $faq_categ[$id]['items'][$id2]['question'] }}</a> <img class="faq_new" src="pic/trans.gif" alt="New" /></li>
                    @endif
                @endforeach
            @endif
            </ul></li>
            <br />
        @endif
    @endforeach
    </ul>
    @php \App\Support\Html::endFrame(); @endphp

    @foreach ($faq_categ as $id => $temp)
        @if ($faq_categ[$id]['flag'] == "1")
            @php
            $frame = $faq_categ[$id]['title'] ." - <a href=\"#top\"><img class=\"top\" src=\"pic/trans.gif\" alt=\"Top\" title=\"Top\" /></a>";
            \App\Support\Html::beginFrame($frame);
            @endphp
            <span id="id{{ $faq_categ[$id]['link_id'] }}"></span>
            @if (isset($faq_categ[$id]['items']))
                @foreach ($faq_categ[$id]['items'] as $id2 => $tempItem)
                    @if ($faq_categ[$id]['items'][$id2]['flag'] != "0")
                        <br /><span id="id{{ $faq_categ[$id]['items'][$id2]['link_id'] }}"><b>{{ $faq_categ[$id]['items'][$id2]['question'] }}</b></span><br />
                        <br />{!! $faq_categ[$id]['items'][$id2]['answer'] !!}<br /><br />
                    @endif
                @endforeach
            @endif
            @php \App\Support\Html::endFrame(); @endphp
        @endif
    @endforeach

    @php
    $Cache->end_whole_row();
    $Cache->cache_page();
    @endphp
@endif
{!! $Cache->next_row() !!}
@endsection

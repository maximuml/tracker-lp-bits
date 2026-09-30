@extends('layouts.app', ['chromeVariant' => 'legacy'])

@section('title', __('legacy/faq.head_faq'))

@section('content')
@if (! empty($faqCategories))
    <x-frame :caption="__('legacy/faq.text_welcome_to').$SITENAME.' - '.$SLOGAN" :center="false">
    {{ __('legacy/faq.text_welcome_content_one') }} <a class="faqlink" href="contactstaff.php">{{ __('legacy/faq.text_contact') }}</a> {{ __('legacy/faq.text_welcome_content_one_end') }}<br /><br />{{ __('legacy/faq.text_welcome_content_one_two') }}
    {{ sprintf(__('legacy/faq.text_welcome_content_two'), $SITENAME) }} <a class="faqlink" href="rules.php">{{ __('legacy/faq.text_rules') }}</a>{{ __('legacy/faq.text_welcome_content_two_two') }}<br /><br />{{ sprintf(__('legacy/faq.text_welcome_content_two_three'), $SITENAME) }} <a class="faqlink" href="useragreement.php">{{ __('legacy/faq.text_user_agreement') }}</a>.
    </x-frame>

    <x-frame :center="false"><x-slot:caption><span id="top">{{ __('legacy/faq.text_contents') }}</span></x-slot>
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
                        <li><a href="#id{{ $faqCategories[$id]['items'][$id2]['link_id'] }}" class="faqlink">{{ $faqCategories[$id]['items'][$id2]['question'] }}</a> <span class="nx-faq-badge nx-faq-badge--updated">Updated</span></li>
                    @elseif ($faqCategories[$id]['items'][$id2]['flag'] == "3")
                        <li><a href="#id{{ $faqCategories[$id]['items'][$id2]['link_id'] }}" class="faqlink">{{ $faqCategories[$id]['items'][$id2]['question'] }}</a> <span class="nx-faq-badge nx-faq-badge--new">New</span></li>
                    @endif
                @endforeach
            @endif
            </ul></li>
        @endif
    @endforeach
    </ul>
    <br />
    </x-frame>

    @foreach ($faqCategories as $id => $temp)
        @if ($faqCategories[$id]['flag'] == "1")
            <x-frame :center="false"><x-slot:caption>{{ $faqCategories[$id]['title'] }} - <a href="#top"><img class="top" src="pic/trans.gif" alt="Top" title="Top" /></a></x-slot>
            <span id="id{{ $faqCategories[$id]['link_id'] }}"></span>
            @if (isset($faqCategories[$id]['items']))
                @foreach ($faqCategories[$id]['items'] as $id2 => $tempItem)
                    @if ($faqCategories[$id]['items'][$id2]['flag'] != "0")
                        <br /><span id="id{{ $faqCategories[$id]['items'][$id2]['link_id'] }}"><b>{{ $faqCategories[$id]['items'][$id2]['question'] }}</b></span><br />
                        <br />{{ $faqCategories[$id]['items'][$id2]['answerHtml'] ?? '' }}<br /><br />
                    @endif
                @endforeach
            @endif
            </x-frame>
        @endif
    @endforeach
@endif
@endsection

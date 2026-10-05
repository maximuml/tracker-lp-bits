@extends('layouts.app', ['chromeVariant' => 'legacy'])

@section('title', __('legacy/faq.head_faq'))

@section('content')
<h1 class="nx-sr-only">{{ __('legacy/faq.head_faq') }}</h1>
@if (! empty($faqCategories))
    <x-frame :caption="__('legacy/faq.text_welcome_to').$SITENAME.' - '.$SLOGAN" :center="false">
    {{ __('legacy/faq.text_welcome_content_one') }} <a class="faqlink" href="/web/contactstaff">{{ __('legacy/faq.text_contact') }}</a> {{ __('legacy/faq.text_welcome_content_one_end') }}<br /><br />{{ __('legacy/faq.text_welcome_content_one_two') }}
    {{ sprintf(__('legacy/faq.text_welcome_content_two'), $SITENAME) }} <a class="faqlink" href="/web/rules">{{ __('legacy/faq.text_rules') }}</a>{{ __('legacy/faq.text_welcome_content_two_two') }}<br /><br />{{ sprintf(__('legacy/faq.text_welcome_content_two_three'), $SITENAME) }} <a class="faqlink" href="/web/useragreement">{{ __('legacy/faq.text_user_agreement') }}</a>.
    </x-frame>

    <x-frame :center="false"><x-slot:caption><span id="top">{{ __('legacy/faq.text_contents') }}</span></x-slot>
    <p class="nx-faq__search">
        <input type="search" class="nx-faq__search-input" data-faq-search placeholder="{{ __('legacy/faq.text_search_faq') }}" aria-label="{{ __('legacy/faq.text_search_faq') }}" />
        <span class="nx-faq__search-count" data-faq-count hidden></span>
    </p>
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
    </x-frame>

    @foreach ($faqCategories as $id => $temp)
        @if ($faqCategories[$id]['flag'] == "1")
            <x-frame :center="false"><x-slot:caption>{{ $faqCategories[$id]['title'] }} - <a href="#top"><img class="top" src="pic/trans.gif" alt="Top" title="Top" /></a></x-slot>
            <div class="nx-faq" id="id{{ $faqCategories[$id]['link_id'] }}">
            @if (isset($faqCategories[$id]['items']))
                @foreach ($faqCategories[$id]['items'] as $id2 => $tempItem)
                    @if ($faqCategories[$id]['items'][$id2]['flag'] != "0")
                        <details class="nx-faq__item" id="id{{ $faqCategories[$id]['items'][$id2]['link_id'] }}">
                            <summary>
                                <span>{{ $faqCategories[$id]['items'][$id2]['question'] }}</span>@if ($faqCategories[$id]['items'][$id2]['flag'] == "2") <span class="nx-faq-badge nx-faq-badge--updated">Updated</span>@elseif ($faqCategories[$id]['items'][$id2]['flag'] == "3") <span class="nx-faq-badge nx-faq-badge--new">New</span>@endif
                            </summary>
                            <div class="nx-faq__answer">{{ $faqCategories[$id]['items'][$id2]['answerHtml'] ?? '' }}</div>
                        </details>
                    @endif
                @endforeach
            @endif
            </div>
            </x-frame>
        @endif
    @endforeach
@endif
@endsection

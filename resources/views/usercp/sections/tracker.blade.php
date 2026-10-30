@include('components.usercp.sections.menu', ['selected' => 'tracker'])

@if ($type === 'saved')
<x-alert type="success">{{ __('usercp.text_settings_saved') }}</x-alert>
@endif

<form method=post action="/web/usercp/tracker" id="{{ $tracker->formId }}">
    <div class="nx-setcards">
        <section class="nx-ucard nx-setcard nx-setcard--wide" aria-labelledby="ucp-sec-catalog">
            <h2 class="nx-setcard__title" id="ucp-sec-catalog">{{ __('usercp.section_catalog_defaults') }}</h2>
            <p class="nx-setcard__hint">{{ __('usercp.text_catalog_defaults_hint') }}</p>
            @include('components.usercp.category-chips', ['vm' => $tracker->categoriesTable])

            <div class="nx-setrow" role="group" aria-labelledby="ucp-g-catalog-status">
                <div class="nx-setrow__label" id="ucp-g-catalog-status">{{ __('usercp.text_additional_selection') }}</div>
                <div class="nx-setrow__control nx-setstatus">
                    <div class="nx-setstatus__item">
                        <label for="usercp-incldead">{{ __('usercp.text_show_dead_active') }}</label>
                        <select name="incldead" id="usercp-incldead" aria-label="{{ __('usercp.text_show_dead_active') }}">
                            <option value="0"@if ($tracker->incldead === 0) selected @endif>{{ __('usercp.select_including_dead') }}</option>
                            <option value="1"@if ($tracker->incldead === 1) selected @endif>{{ __('usercp.select_active') }}</option>
                            <option value="2"@if ($tracker->incldead === 2) selected @endif>{{ __('usercp.select_dead') }}</option>
                        </select>
                    </div>
                    <div class="nx-setstatus__item">
                        <label for="usercp-spstate">{{ __('usercp.text_show_special_torrents') }}</label>
                        <select name="spstate" id="usercp-spstate" aria-label="{{ __('usercp.text_show_special_torrents') }}">
                            <option value="0"@if ($tracker->specialState === 0) selected @endif>{{ __('usercp.select_all') }}</option>
                            {{ $tracker->promotionOptionsHtml }}
                        </select>
                    </div>
                    <div class="nx-setstatus__item">
                        <label for="usercp-inclbookmarked">{{ __('usercp.text_show_bookmarked') }}</label>
                        <select name="inclbookmarked" id="usercp-inclbookmarked" aria-label="{{ __('usercp.text_show_bookmarked') }}">
                            <option value="0"@if ($tracker->inclbookmarked === 0) selected @endif>{{ __('usercp.select_all') }}</option>
                            <option value="1"@if ($tracker->inclbookmarked === 1) selected @endif>{{ __('usercp.select_bookmarked') }}</option>
                            <option value="2"@if ($tracker->inclbookmarked === 2) selected @endif>{{ __('usercp.select_bookmarked_exclude') }}</option>
                        </select>
                    </div>
                </div>
            </div>
        </section>

        <section class="nx-ucard nx-setcard" aria-labelledby="ucp-sec-appearance">
            <h2 class="nx-setcard__title" id="ucp-sec-appearance">{{ __('usercp.section_appearance') }}</h2>
            <div class="nx-setrow">
                <label class="nx-setrow__label" for="usercp-stylesheet">{{ __('usercp.row_stylesheet') }}</label>
                <div class="nx-setrow__control">
                    <select name="stylesheet" id="usercp-stylesheet" aria-label="{{ __('usercp.row_stylesheet') }}">
                        @foreach ($tracker->stylesheetOptions as $id => $name)
                            <option value="{{ $id }}"@if ((string) $tracker->currentStylesheet === (string) $id) selected @endif>{{ $name }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
            <div class="nx-setrow">
                <label class="nx-setrow__label" for="usercp-theme">{{ __('usercp.row_theme') }}</label>
                <div class="nx-setrow__control">
                    <select name="theme" id="usercp-theme" aria-label="{{ __('usercp.row_theme') }}">
                        @foreach ($tracker->themeOptions as $value => $name)
                            <option value="{{ $value }}"@if ((string) $tracker->currentTheme === (string) $value) selected @endif>{{ $name }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
            <div class="nx-setrow">
                <label class="nx-setrow__label" for="usercp-fontsize">{{ __('usercp.row_font_size') }}</label>
                <div class="nx-setrow__control">
                    <select name="fontsize" id="usercp-fontsize" aria-label="{{ __('usercp.row_font_size') }}">
                        <option value="small"@if ($tracker->fontsize === 'small') selected @endif>{{ __('usercp.select_small') }}</option>
                        <option value="medium"@if ($tracker->fontsize === 'medium') selected @endif>{{ __('usercp.select_medium') }}</option>
                        <option value="large"@if ($tracker->fontsize === 'large') selected @endif>{{ __('usercp.select_large') }}</option>
                    </select>
                </div>
            </div>
            <div class="nx-setrow">
                <label class="nx-setrow__label" for="usercp-sitelanguage">{{ __('usercp.row_site_language') }}</label>
                <div class="nx-setrow__control">
                    <select name="sitelanguage" id="usercp-sitelanguage" aria-label="{{ __('usercp.row_site_language') }}">
                        @foreach ($tracker->langOptions as $id => $name)
                            <option value="{{ $id }}"@if ($tracker->currentLangId === (int) $id) selected @endif>{{ $name }}</option>
                        @endforeach
                    </select>
                    <p class="nx-setrow__hint">{{ __('usercp.text_translation_note') }} <a href="/web/aboutnexus#translation"><b>{{ __('usercp.text_translation_link') }}</b></a>.</p>
                </div>
            </div>
        </section>

        <section class="nx-ucard nx-setcard" aria-labelledby="ucp-sec-messages">
            <h2 class="nx-setcard__title" id="ucp-sec-messages">{{ __('usercp.section_messages') }}</h2>
            @if ($tracker->showEmailNotify)
                <div class="nx-setrow" role="group" aria-labelledby="ucp-g-email-notification">
                    <div class="nx-setrow__label" id="ucp-g-email-notification">{{ __('usercp.row_email_notification') }}</div>
                    <div class="nx-setrow__control">
                        <label><input type="checkbox" name="pmnotif" value="yes"@if ($tracker->pmnotif) checked @endif> {{ __('usercp.checkbox_notification_received_pm') }}</label>
                        <label><input type="checkbox" name="emailnotif" value="yes"@if ($tracker->emailnotif) checked @endif> {{ __('usercp.checkbox_notification_default_categories') }}</label>
                    </div>
                </div>
            @endif
            <div class="nx-setrow">
                <label class="nx-setrow__label" for="usercp-pmnum">{{ __('usercp.row_pm_boxes') }}</label>
                <div class="nx-setrow__control">
                    <label>{{ __('usercp.text_show') }} <input type="text" name="pmnum" id="usercp-pmnum" size="5" value="{{ $tracker->pmnum }}"> {{ __('usercp.text_pms_per_page') }}</label>
                </div>
            </div>
            @if ($tracker->showShoutbox)
                <div class="nx-setrow" role="group" aria-labelledby="ucp-g-shoutbox">
                    <div class="nx-setrow__label" id="ucp-g-shoutbox">{{ __('usercp.row_shoutbox') }}</div>
                    <div class="nx-setrow__control">
                        <label>{{ __('usercp.text_show_last') }} <input type="text" name="sbnum" id="usercp-sbnum" size="5" value="{{ $tracker->sbnum }}"> {{ __('usercp.text_messages_at_shoutbox') }}</label>
                        <label>{{ __('usercp.text_refresh_shoutbox_every') }} <input type="text" name="sbrefresh" id="usercp-sbrefresh" size="5" value="{{ $tracker->sbrefresh }}"> {{ __('usercp.text_seconds') }}</label>
                    </div>
                </div>
            @endif
        </section>

        <section class="nx-ucard nx-setcard nx-setcard--wide" aria-labelledby="ucp-sec-torrent-list">
            <h2 class="nx-setcard__title" id="ucp-sec-torrent-list">{{ __('usercp.section_torrent_list') }}</h2>
            <p class="nx-setcard__hint nx-setcard__hint--warning"><strong>{{ __('usercp.text_warning') }}:</strong> {{ __('usercp.text_browse_setting_warning') }}</p>
            <div class="nx-setrow">
                <label class="nx-setrow__label" for="usercp-torrentsperpage">{{ \App\Support\Html\SafeHtml::fromUntrustedHtml(__('usercp.row_torrent_page')) }}</label>
                <div class="nx-setrow__control">
                    <label>{{ __('usercp.text_show') }} <input type="text" size="5" name="torrentsperpage" id="usercp-torrentsperpage" value="{{ $tracker->torrentsperpage }}"> {{ __('usercp.text_torrents_per_page') }}</label>
                    <p class="nx-setrow__hint">{{ __('usercp.text_zero_equals_default') }}</p>
                </div>
            </div>
            <div class="nx-setrow" role="group" aria-labelledby="ucp-g-timetype">
                <div class="nx-setrow__label" id="ucp-g-timetype">{{ __('usercp.row_time_type') }}</div>
                <div class="nx-setrow__control">
                    <label><input type="radio" name="timetype" id="usercp-timetype-timeadded" value="timeadded"@if ($tracker->timetype === 'timeadded') checked @endif> {{ __('usercp.text_time_added') }}</label>
                    <label><input type="radio" name="timetype" id="usercp-timetype-timealive" value="timealive"@if ($tracker->timetype === 'timealive') checked @endif> {{ __('usercp.text_time_elapsed') }}</label>
                </div>
            </div>
            @if ($tracker->showTooltipSetting)
                <div class="nx-setrow" role="group" aria-labelledby="ucp-g-tooltip">
                    <div class="nx-setrow__label" id="ucp-g-tooltip">{{ __('usercp.text_tooltip_type') }}</div>
                    <div class="nx-setrow__control">
                        <label><input type="radio" name="tooltip" id="usercp-tooltip-off" value="off"@if ($tracker->tooltip === 'off') checked @endif> {{ __('usercp.text_off') }}</label>
                    </div>
                </div>
            @endif
            <div class="nx-setrow" role="group" aria-labelledby="ucp-g-special-torrents">
                <div class="nx-setrow__label" id="ucp-g-special-torrents">{{ __('usercp.text_append_words_to_torrents') }}</div>
                <div class="nx-setrow__control">
                    <label><input type="checkbox" name="appendsticky" id="usercp-appendsticky" value="yes"@if ($tracker->appendsticky) checked @endif> {{ __('usercp.text_append_sticky') }}<i class="sticky" role="img" aria-label="sticky"></i> {{ __('usercp.text_append_sticky_tail') }}</label>
                    <label><input type="checkbox" name="appendnew" id="usercp-appendnew" value="yes"@if ($tracker->appendnew) checked @endif> {{ __('usercp.text_append_new') }}</label>
                    <label><input type="checkbox" name="appendpicked" id="usercp-appendpicked" value="yes"@if ($tracker->appendpicked) checked @endif> {{ __('usercp.text_append_picked') }}</label>
                </div>
            </div>
            <div class="nx-setrow" role="group" aria-labelledby="ucp-g-promotion">
                <div class="nx-setrow__label" id="ucp-g-promotion">{{ __('usercp.text_torrents_on_promotion') }}</div>
                <div class="nx-setrow__control">
                    <label><input type="radio" name="appendpromotion" id="usercp-appendpromotion-highlight" value="highlight"@if ($tracker->appendpromotion === 'highlight') checked @endif> {{ __('usercp.text_highlight') }}</label>
                    <label><input type="radio" name="appendpromotion" id="usercp-appendpromotion-word" value="word"@if ($tracker->appendpromotion === 'word') checked @endif> {{ __('usercp.text_append_words') }}</label>
                    <label><input type="radio" name="appendpromotion" id="usercp-appendpromotion-icon" value="icon"@if ($tracker->appendpromotion === 'icon') checked @endif> {{ __('usercp.text_append_icon') }} <span class="nx-promo nx-promo--free">{{ __('functions.text_free') }}</span></label>
                    <label><input type="radio" name="appendpromotion" id="usercp-appendpromotion-off" value="off"@if ($tracker->appendpromotion === 'off') checked @endif> {{ __('usercp.text_no_mark') }}</label>
                </div>
            </div>
        </section>

        <section class="nx-ucard nx-setcard nx-setcard--wide" aria-labelledby="ucp-sec-torrent-page">
            <h2 class="nx-setcard__title" id="ucp-sec-torrent-page">{{ __('usercp.section_torrent_page') }}</h2>
            <div class="nx-setrow" role="group" aria-labelledby="ucp-g-torrent-detail">
                <div class="nx-setrow__label" id="ucp-g-torrent-detail">{{ __('usercp.row_torrent_detail') }}</div>
                <div class="nx-setrow__control">
                    <label><input type="checkbox" name="showdescription" id="usercp-showdescription" value="yes"@if ($tracker->showdescription) checked @endif> {{ __('usercp.text_show_description') }}</label>
                </div>
            </div>
            <div class="nx-setrow" role="group" aria-labelledby="ucp-g-discuss">
                <div class="nx-setrow__label" id="ucp-g-discuss">{{ __('usercp.row_discuss') }}</div>
                <div class="nx-setrow__control">
                    <label><input type="checkbox" name="showcomment" id="usercp-showcomment" value="yes"@if ($tracker->showcomment) checked @endif> {{ __('usercp.text_show_comments') }}</label>
                </div>
            </div>
            <div class="nx-setrow" role="group" aria-labelledby="ucp-g-action-icons">
                <div class="nx-setrow__label" id="ucp-g-action-icons">{{ __('usercp.text_show_action_icons') }}</div>
                <div class="nx-setrow__control">
                    <label><input type="checkbox" name="dlicon" id="usercp-dlicon" value="yes"@if ($tracker->dlicon) checked @endif> {{ __('usercp.text_show_download_icon') }} <i class="download" role="img" aria-label="Download"></i></label>
                    <label><input type="checkbox" name="bmicon" id="usercp-bmicon" value="yes"@if ($tracker->bmicon) checked @endif> {{ __('usercp.text_show_bookmark_icon') }} <i class="bookmark" role="img" aria-label="Bookmark"></i></label>
                </div>
            </div>
            <div class="nx-setrow" role="group" aria-labelledby="ucp-g-comments-reviews">
                <div class="nx-setrow__label" id="ucp-g-comments-reviews">{{ __('usercp.text_comments_reviews') }}</div>
                <div class="nx-setrow__control">
                    <label><input type="checkbox" name="showcomnum" id="usercp-showcomnum" value="yes"@if ($tracker->showcomnum) checked @endif> {{ \App\Support\Html\SafeHtml::fromUntrustedHtml(__('usercp.text_show_comment_number')) }}</label>
                    @if ($tracker->showTooltipSetting)
                        <label class="nx-setrow__inline-control">
                            <select name="showlastcom" id="usercp-showlastcom" aria-label="{{ trim(strip_tags(__('usercp.text_last_comment_on_tooltip'))) }}">
                                <option value="yes"@if ($tracker->showlastcom) selected @endif>{{ __('usercp.select_with') }}</option>
                                <option value="no"@if (! $tracker->showlastcom) selected @endif>{{ __('usercp.select_without') }}</option>
                            </select>
                            {{ \App\Support\Html\SafeHtml::fromUntrustedHtml(__('usercp.text_last_comment_on_tooltip')) }}
                        </label>
                    @endif
                </div>
            </div>
        </section>
    </div>
    <div class="nx-savebar">
        <button type="submit" class="nx-btn nx-btn--primary" data-submit-once>{{ __('usercp.submit_save') }}</button>
    </div>
</form>

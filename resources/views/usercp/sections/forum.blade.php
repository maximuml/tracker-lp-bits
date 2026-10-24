@include('components.usercp.sections.menu', ['selected' => 'forum'])

@if ($type === 'saved')
    <x-alert type="success">{{ __('usercp.text_settings_saved') }}</x-alert>
@endif

<form method=post action="/web/usercp/forum" id="{{ $forum->formId }}">
    <div class="nx-setcards">
        <section class="nx-ucard nx-setcard" aria-labelledby="ucp-sec-forum-pages">
            <h2 class="nx-setcard__title" id="ucp-sec-forum-pages">{{ __('usercp.section_forum_pages') }}</h2>
            <div class="nx-setrow">
                <label class="nx-setrow__label" for="usercp-topicsperpage">@safeHtml(\App\Support\Html\SafeHtml::fromUntrustedHtml(__('usercp.row_topics_per_page')))</label>
                <div class="nx-setrow__control">
                    <input type="text" name="topicsperpage" id="usercp-topicsperpage" value="{{ $forum->topicsPerPage }}">
                    <p class="nx-setrow__hint">{{ __('usercp.text_zero_equals_default') }}</p>
                </div>
            </div>
            <div class="nx-setrow">
                <label class="nx-setrow__label" for="usercp-postsperpage">@safeHtml(\App\Support\Html\SafeHtml::fromUntrustedHtml(__('usercp.row_posts_per_page')))</label>
                <div class="nx-setrow__control">
                    <input type="text" name="postsperpage" id="usercp-postsperpage" value="{{ $forum->postsPerPage }}">
                    <p class="nx-setrow__hint">{{ __('usercp.text_zero_equals_default') }}</p>
                </div>
            </div>
            <div class="nx-setrow" role="group" aria-labelledby="ucp-g-clicktopic">
                <div class="nx-setrow__label" id="ucp-g-clicktopic">{{ __('usercp.row_click_on_topic') }}</div>
                <div class="nx-setrow__control">
                    <label><input type="radio" name="clicktopic" value="firstpage" @checked($forum->clicktopic === 'firstpage')>{{ __('usercp.text_go_to_first_page') }}</label>
                    <label><input type="radio" name="clicktopic" value="lastpage" @checked($forum->clicktopic === 'lastpage')>{{ __('usercp.text_go_to_last_page') }}</label>
                </div>
            </div>
        </section>

        <section class="nx-ucard nx-setcard" aria-labelledby="ucp-sec-forum-display">
            <h2 class="nx-setcard__title" id="ucp-sec-forum-display">{{ __('usercp.section_forum_display') }}</h2>
            <div class="nx-setrow" role="group" aria-labelledby="ucp-g-avatars">
                <div class="nx-setrow__label" id="ucp-g-avatars">@safeHtml(\App\Support\Html\SafeHtml::fromUntrustedHtml(__('usercp.row_view_avatars')))</div>
                <div class="nx-setrow__control">
                    <label><input type="checkbox" name="avatars" value="yes" @checked($forum->avatars)> {{ __('usercp.checkbox_low_bandwidth_note') }}</label>
                </div>
            </div>
            <div class="nx-setrow" role="group" aria-labelledby="ucp-g-signatures">
                <div class="nx-setrow__label" id="ucp-g-signatures">@safeHtml(\App\Support\Html\SafeHtml::fromUntrustedHtml(__('usercp.row_view_signatures')))</div>
                <div class="nx-setrow__control">
                    <label><input type="checkbox" name="signatures" value="yes" @checked($forum->signatures)> {{ __('usercp.checkbox_low_bandwidth_note') }}</label>
                </div>
            </div>
            @if ($forum->showTooltipSetting)
                <div class="nx-setrow" role="group" aria-labelledby="ucp-g-ttlastpost">
                    <div class="nx-setrow__label" id="ucp-g-ttlastpost">{{ __('usercp.row_tooltip_last_post') }}</div>
                    <div class="nx-setrow__control">
                        <label><input type="checkbox" name="ttlastpost" value="yes" @checked($forum->showLastPost)> {{ __('usercp.checkbox_last_post_note') }}</label>
                    </div>
                </div>
            @endif
        </section>

        <section class="nx-ucard nx-setcard nx-setcard--wide" aria-labelledby="ucp-sec-signature">
            <h2 class="nx-setcard__title" id="ucp-sec-signature">{{ __('usercp.section_signature') }}</h2>
            <div class="nx-setrow">
                <label class="nx-setrow__label" for="usercp-signature">{{ __('usercp.row_forum_signature') }}</label>
                <div class="nx-setrow__control">
                    <textarea name="signature" id="usercp-signature" rows="10">{{ $forum->signature }}</textarea>
                    <p class="nx-setrow__hint">{{ __('usercp.text_signature_note') }} <a class="faqlink" href="/web/tags" target="_new">{{ __('usercp.text_bb_codes') }}</a>{{ __('usercp.text_signature_note_tail') }}</p>
                </div>
            </div>
        </section>
    </div>
    <div class="nx-savebar">
        <button type="submit" class="nx-btn nx-btn--primary" data-submit-once>{{ __('usercp.submit_save') }}</button>
    </div>
</form>

@include('components.usercp.sections.menu', ['selected' => 'personal'])

@if ($type === 'saved')
    <x-alert type="success">{{ __('usercp.text_settings_saved') }}</x-alert>
@endif

<form method=post action="/web/usercp/personal" id="{{ $personal->formId }}">
    <div class="nx-setcards">
        <section class="nx-ucard nx-setcard" aria-labelledby="ucp-sec-account">
            <h2 class="nx-setcard__title" id="ucp-sec-account">{{ __('usercp.section_account') }}</h2>
            <div class="nx-setrow">
                <label class="nx-setrow__label" for="usercp-parked">@safeHtml(\App\Support\Html\SafeHtml::fromUntrustedHtml(__('usercp.row_account_parked')))</label>
                <div class="nx-setrow__control">
                    <label><input type="checkbox" name="parked" id="usercp-parked" value="yes" @checked($personal->parked)>{{ __('usercp.checkbox_pack_my_account') }}</label>
                    <p class="nx-setrow__hint">{{ __('usercp.text_note_plain') }}: {{ __('usercp.text_account_pack_note') }}</p>
                </div>
            </div>
            <div class="nx-setrow">
                <label class="nx-setrow__label" for="usercp-tracker-url">{{ __('usercp.row_tracker_url') }}</label>
                <div class="nx-setrow__control">
                    <select name="tracker_url_id" id="usercp-tracker-url">
                        @foreach ($personal->trackerUrlOptions as $id => $url)
                            <option value="{{ $id }}" @selected($personal->trackerUrlId === (string) $id)>{{ $url }}</option>
                        @endforeach
                    </select>
                    <p class="nx-setrow__hint">{{ __('usercp.text_note') }} {{ __('usercp.row_tracker_url_help') }}</p>
                </div>
            </div>
        </section>

        <section class="nx-ucard nx-setcard" aria-labelledby="ucp-sec-profile">
            <h2 class="nx-setcard__title" id="ucp-sec-profile">{{ __('usercp.section_profile') }}</h2>
            <div class="nx-setrow" role="group" aria-labelledby="ucp-g-gender">
                <div class="nx-setrow__label" id="ucp-g-gender">{{ __('usercp.row_gender') }}</div>
                <div class="nx-setrow__control">
                    <label><input type="radio" name="gender" value="N/A" @checked($personal->gender === 'N/A')>{{ __('usercp.radio_not_available') }}</label>
                    <label><input type="radio" name="gender" value="Male" @checked($personal->gender === 'Male')>{{ __('usercp.radio_male') }}</label>
                    <label><input type="radio" name="gender" value="Female" @checked($personal->gender === 'Female')>{{ __('usercp.radio_female') }}</label>
                </div>
            </div>
            <div class="nx-setrow">
                <label class="nx-setrow__label" for="usercp-country">{{ __('usercp.row_country') }}</label>
                <div class="nx-setrow__control">
                    <select name="country" id="usercp-country">
                        @foreach ($personal->countryOptions as $id => $country)
                            <option value="{{ $id }}" @selected($personal->country === (string) $id)>{{ $country }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
            <div class="nx-setrow" role="group" aria-labelledby="ucp-g-acceptpms">
                <div class="nx-setrow__label" id="ucp-g-acceptpms">@safeHtml(\App\Support\Html\SafeHtml::fromUntrustedHtml(__('usercp.text_accept_pms')))</div>
                <div class="nx-setrow__control">
                    <label><input type="radio" name="acceptpms" value="yes" @checked($personal->acceptpms === 'yes')>{{ __('usercp.radio_all_except_blocks') }}</label>
                    <label><input type="radio" name="acceptpms" value="friends" @checked($personal->acceptpms === 'friends')>{{ __('usercp.radio_friends_only') }}</label>
                    <label><input type="radio" name="acceptpms" value="no" @checked($personal->acceptpms === 'no')>{{ __('usercp.radio_staff_only') }}</label>
                </div>
            </div>
            <div class="nx-setrow" role="group" aria-labelledby="ucp-g-pms">
                <div class="nx-setrow__label" id="ucp-g-pms">{{ __('usercp.row_pms') }}</div>
                <div class="nx-setrow__control">
                    <label><input type="checkbox" name="deletepms" @checked($personal->deletepms)> @safeHtml(\App\Support\Html\SafeHtml::fromUntrustedHtml(__('usercp.checkbox_delete_pms')))</label>
                    <label><input type="checkbox" name="savepms" @checked($personal->savepms)> @safeHtml(\App\Support\Html\SafeHtml::fromUntrustedHtml(__('usercp.checkbox_save_pms')))</label>
                    <label><input type="checkbox" name="commentpm" value="yes" @checked($personal->commentpm)> @safeHtml(\App\Support\Html\SafeHtml::fromUntrustedHtml(__('usercp.checkbox_pm_on_comments')))</label>
                    @foreach ($personal->notifCheckboxes as $cb)
                        <label><input type="checkbox" name="{{ $cb['name'] }}" value="yes" @checked($cb['checked'])> @safeHtml(\App\Support\Html\SafeHtml::fromUntrustedHtml($cb['label']))</label>
                    @endforeach
                </div>
            </div>
        </section>

        <section class="nx-ucard nx-setcard" aria-labelledby="ucp-sec-avatar">
            <h2 class="nx-setcard__title" id="ucp-sec-avatar">{{ __('usercp.section_avatar') }}</h2>
            <div class="nx-setrow">
                <label class="nx-setrow__label" for="usercp-savatar">{{ __('usercp.select_choose_avatar') }}</label>
                <div class="nx-setrow__control">
                    <img id="avatarimg" name="avatarimg" src="{{ $personal->avatar !== '' ? $personal->avatar : $personal->defaultAvatarUrl }}" alt="{{ __('usercp.section_avatar') }}" class="nx-usercp-avatar">
                    <select name="savatar" id="usercp-savatar">
                        <option value="{{ $personal->avatar }}">{{ __('usercp.select_choose_avatar') }}</option>
                        <option value="{{ $personal->defaultAvatarUrl }}">{{ __('usercp.select_nothing') }}</option>
                        @foreach ($personal->bitbucketOptions as $url => $name)
                            <option value="{{ $url }}">{{ $name }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
            <div class="nx-setrow">
                <label class="nx-setrow__label" for="usercp-avatar">@safeHtml(\App\Support\Html\SafeHtml::fromUntrustedHtml(__('usercp.row_avatar_url')))</label>
                <div class="nx-setrow__control">
                    <input type="text" name="avatar" id="usercp-avatar" value="{{ $personal->avatar }}">
                    <p class="nx-setrow__hint">{{ __('usercp.text_avatar_note') }}@if ($personal->enableBitbucket) {{ __('usercp.text_bitbucket_note') }} <a class="faqlink" href="/web/bitbucket-upload">{{ __('usercp.text_bitbucket') }}</a>.@endif</p>
                </div>
            </div>
        </section>

        <section class="nx-ucard nx-setcard nx-setcard--wide" aria-labelledby="ucp-sec-about">
            <h2 class="nx-setcard__title" id="ucp-sec-about">{{ __('usercp.section_about') }}</h2>
            <div class="nx-setrow">
                <label class="nx-setrow__label" for="usercp-info">{{ __('usercp.row_info') }}</label>
                <div class="nx-setrow__control">
                    <textarea name="info" id="usercp-info" rows="10">{{ $personal->info }}</textarea>
                    <p class="nx-setrow__hint">{{ __('usercp.text_info_note') }} <a class="faqlink" href="/web/tags" target="_new">{{ __('usercp.text_bb_codes') }}</a>.</p>
                </div>
            </div>
        </section>
    </div>
    <div class="nx-savebar">
        <button type="submit" class="nx-btn nx-btn--primary" data-submit-once>{{ __('usercp.submit_save') }}</button>
    </div>
</form>

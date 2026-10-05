@include('usercp.sections._menu', ['selected' => 'personal'])

<form method=post action="/web/usercp/personal" id="{{ $personal->formId }}">
<div class="nx-fgrid nx-fgrid--flat">
@if ($type === 'saved')
<div class="nx-ffull text-center"><span class="text-nxm-danger"><b>{{ __('legacy/usercp.text_saved')}}</b></span></div>
@endif
<x-settings-row-small layout="grid" :label="\App\Support\Html\SafeHtml::fromUntrustedHtml(__('legacy/usercp.row_account_parked'))"><input type="checkbox" name="parked"@if ($personal->parked) checked @endif value="yes">{{ __('legacy/usercp.checkbox_pack_my_account') }}<br /><span class="small text-[10px]"><b>{{ __('legacy/usercp.text_note_plain') }}</b>:{{ __('legacy/usercp.text_account_pack_note') }}</span></x-settings-row-small>
<x-settings-row-small layout="grid" :label="__('legacy/usercp.row_pms')">{{ \App\Support\Html\SafeHtml::fromUntrustedHtml(__('legacy/usercp.text_accept_pms')) }}<input type="radio" name="acceptpms"@if ($personal->acceptpms === 'yes') checked @endif value="yes">{{ __('legacy/usercp.radio_all_except_blocks') }}<input type="radio" name="acceptpms"@if ($personal->acceptpms === 'friends') checked @endif value="friends">{{ __('legacy/usercp.radio_friends_only') }}<input type="radio" name="acceptpms"@if ($personal->acceptpms === 'no') checked @endif value="no">{{ __('legacy/usercp.radio_staff_only') }}<br /><input type="checkbox" name="deletepms"@if ($personal->deletepms) checked @endif> {{ \App\Support\Html\SafeHtml::fromUntrustedHtml(__('legacy/usercp.checkbox_delete_pms')) }}<br /><input type="checkbox" name="savepms"@if ($personal->savepms) checked @endif> {{ \App\Support\Html\SafeHtml::fromUntrustedHtml(__('legacy/usercp.checkbox_save_pms')) }}<br /><input type="checkbox" name="commentpm"@if ($personal->commentpm) checked @endif value="yes"> {{ \App\Support\Html\SafeHtml::fromUntrustedHtml(__('legacy/usercp.checkbox_pm_on_comments')) }}@foreach ($personal->notifCheckboxes as $cb)<br /><input type="checkbox" name="{{ $cb['name'] }}"@if ($cb['checked']) checked @endif value="yes" /> {{ \App\Support\Html\SafeHtml::fromUntrustedHtml($cb['label']) }}@endforeach</x-settings-row-small>
<x-settings-radios layout="grid" :label="__('legacy/usercp.row_gender')" name="gender" :options="['N/A' => __('legacy/usercp.radio_not_available'), 'Male' => __('legacy/usercp.radio_male'), 'Female' => __('legacy/usercp.radio_female')]" :selected="$personal->gender" />
<x-settings-row-small layout="grid" :label="__('legacy/usercp.row_tracker_url')"><select name="tracker_url_id">
@foreach ($personal->trackerUrlOptions as $id => $url)
<option value="{{ $id }}"@if ($personal->trackerUrlId === (string) $id) selected @endif>{{ $url }}</option>
@endforeach
</select><br /><span class="small text-[10px]"><b>{{ __('legacy/usercp.text_note') }}</b> {{ __('legacy/usercp.row_tracker_url_help') }}</span></x-settings-row-small>
<x-settings-select layout="grid" :label="__('legacy/usercp.row_country')" name="country" :options="$personal->countryOptions" :selected="$personal->country" />
<x-settings-row-small layout="grid" :label="\App\Support\Html\SafeHtml::fromUntrustedHtml(__('legacy/usercp.row_avatar_url'))"><img src="{{ $personal->avatar !== '' ? $personal->avatar : $personal->defaultAvatarUrl }}" name="avatarimg"><br />
  <select name="savatar">
  <option value="{{ $personal->avatar }}">{{ __('legacy/usercp.select_choose_avatar') }}</option>
  <option value="{{ $personal->defaultAvatarUrl }}">{{ __('legacy/usercp.select_nothing') }}</option>
  @foreach ($personal->bitbucketOptions as $url => $name)
  <option value="{{ $url }}">{{ $name }}</option>
  @endforeach
  </select><input type="text" name="avatar" value="{{ $personal->avatar }}"><br />
{{ __('legacy/usercp.text_avatar_note') }}@if ($personal->enableBitbucket){{ __('legacy/usercp.text_bitbucket_note') }}<a class="faqlink" href="bitbucket-upload.php">{{ __('legacy/usercp.text_bitbucket') }}</a>.@endif</x-settings-row-small>
<x-settings-row-small layout="grid" :label="__('legacy/usercp.row_info')"><textarea name="info" rows="10">{{ $personal->info }}</textarea><br />{{ __('legacy/usercp.text_info_note') }}<a class="faqlink" href="tags.php" target="_new">{{ __('legacy/usercp.text_bb_codes') }}</a>.</x-settings-row-small>
<div class="nx-fhead">{{ __('legacy/usercp.row_save_settings')}}</div><div class="nx-fcell"><input type=submit value="{{ __('legacy/usercp.submit_save_settings')}}"></div>
</div></form>

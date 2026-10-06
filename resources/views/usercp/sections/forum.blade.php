@include('usercp.sections._menu', ['selected' => 'forum'])

<form method=post action="/web/usercp/forum" id="{{ $forum->formId }}">
<div class="nx-fgrid nx-fgrid--flat">
@if ($type === 'saved')
<div class="nx-ffull text-center"><span class="text-nxm-danger"><b>{{ __('legacy/usercp.text_saved')}}</b></span></div>
@endif
<x-settings-text layout="grid" :label="\App\Support\Html\SafeHtml::fromUntrustedHtml(__('legacy/usercp.row_topics_per_page'))" name="topicsperpage" :value="$forum->topicsPerPage" :size="10" :note="__('legacy/usercp.text_zero_equals_default')" />
<x-settings-text layout="grid" :label="\App\Support\Html\SafeHtml::fromUntrustedHtml(__('legacy/usercp.row_posts_per_page'))" name="postsperpage" :value="$forum->postsPerPage" :size="10" :note="__('legacy/usercp.text_zero_equals_default')" />
<x-settings-row-small layout="grid" :label="\App\Support\Html\SafeHtml::fromUntrustedHtml(__('legacy/usercp.row_view_avatars'))"><input type="checkbox" name="avatars"@if ($forum->avatars) checked @endif value="yes">{{ __('legacy/usercp.checkbox_low_bandwidth_note') }}</x-settings-row-small>
<x-settings-row-small layout="grid" :label="\App\Support\Html\SafeHtml::fromUntrustedHtml(__('legacy/usercp.row_view_signatures'))"><input type="checkbox" name="signatures"@if ($forum->signatures) checked @endif value="yes">{{ __('legacy/usercp.checkbox_low_bandwidth_note') }}</x-settings-row-small>
@if ($forum->showTooltipSetting)
<x-settings-row-small layout="grid" :label="__('legacy/usercp.row_tooltip_last_post')"><input type="checkbox" name="ttlastpost"@if ($forum->showLastPost) checked @endif value="yes">{{ __('legacy/usercp.checkbox_last_post_note') }}</x-settings-row-small>
@endif
<x-settings-radios layout="grid" :label="__('legacy/usercp.row_click_on_topic')" name="clicktopic" :options="['firstpage' => __('legacy/usercp.text_go_to_first_page'), 'lastpage' => __('legacy/usercp.text_go_to_last_page')]" :selected="$forum->clicktopic" />
<x-settings-row-small layout="grid" :label="__('legacy/usercp.row_forum_signature')"><textarea name="signature" rows="10">{{ $forum->signature }}</textarea><br />{{ __('legacy/usercp.text_signature_note') }}<a class="faqlink" href="/web/tags" target="_new">{{ __('legacy/usercp.text_bb_codes') }}</a>{{ __('legacy/usercp.text_signature_note_tail') }}</x-settings-row-small>
<div class="nx-fhead">{{ __('legacy/usercp.row_save_settings')}}</div><div class="nx-fcell"><input type=submit value="{{ __('legacy/usercp.submit_save_settings')}}"></div>
</div></form>

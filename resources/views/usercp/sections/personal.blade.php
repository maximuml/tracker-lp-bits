@php
/** @var array<string, mixed> $personal */
/** @var array<string, mixed> $lang */
/** @var array<string, mixed> $curUser */
/** @var string $type */
$lang_usercp = $lang;
$CURUSER = $curUser;
$CONTENT_WIDTH = $contentWidth;
$BASEURL = $personal['baseUrl'];
$enablebitbucket_main = $personal['enableBitbucket'] ? 'yes' : 'no';
@endphp
@include('usercp.sections._menu', ['selected' => 'personal'])

@php
$formId = 'form'.\App\Support\Strings::randomCode(6);
@endphp
<form method=post action=usercp.php id="{{ $formId }}"><input type=hidden name=action value=personal><input type=hidden name=type value=save>
<table border=0 cellspacing=0 cellpadding=5 width={{ $CONTENT_WIDTH }}>
@if ($type === 'saved')
<tr><td colspan=2 class="heading" valign="top" align="center"><font color=red>{{ $lang_usercp['text_saved'] ?? 'Saved' }}</font></td></tr>
@endif
@php
\App\Support\Html::trSmall($lang_usercp['row_account_parked'] ?? 'Account parked', '<input type=checkbox name=parked'.($CURUSER['parked'] ?? '' === 'yes' ? ' checked' : '').' value=yes>'.htmlspecialchars($lang_usercp['checkbox_pack_my_account'] ?? '').'<br /><font class=small size=1>'.htmlspecialchars($lang_usercp['text_account_pack_note'] ?? '').'</font>', 1);

$pmY = htmlspecialchars($lang_usercp['text_accept_pms'] ?? '').'<input type=radio name=acceptpms'.(($CURUSER['acceptpms'] ?? '') === 'yes' ? ' checked' : '').' value=yes>'.htmlspecialchars($lang_usercp['radio_all_except_blocks'] ?? '').'<input type=radio name=acceptpms'.(($CURUSER['acceptpms'] ?? '') === 'friends' ? ' checked' : '').' value=friends>'.htmlspecialchars($lang_usercp['radio_friends_only'] ?? '').'<input type=radio name=acceptpms'.(($CURUSER['acceptpms'] ?? '') === 'no' ? ' checked' : '').' value=no>'.htmlspecialchars($lang_usercp['radio_staff_only'] ?? '')
    .'<br /><input type=checkbox name=deletepms'.(($CURUSER['deletepms'] ?? '') === 'yes' ? ' checked' : '').'> '.htmlspecialchars($lang_usercp['checkbox_delete_pms'] ?? '')
    .'<br /><input type=checkbox name=savepms'.(($CURUSER['savepms'] ?? '') === 'yes' ? ' checked' : '').'> '.htmlspecialchars($lang_usercp['checkbox_save_pms'] ?? '')
    .'<br /><input type=checkbox name=commentpm'.(($CURUSER['commentpm'] ?? '') === 'yes' ? ' checked' : '').' value=yes> '.htmlspecialchars($lang_usercp['checkbox_pm_on_comments'] ?? '');
$notifs = (string) ($CURUSER['notifs'] ?? '');
foreach ($personal['notificationOptions'] as $option):
    $pmY .= sprintf('<br /><input type="checkbox" name="notifs[%s]"%s value="yes" /> %s', $option, (is_null($CURUSER['notifs'] ?? null) || str_contains($notifs, "[{$option}]") ? ' checked' : ''), htmlspecialchars($lang_usercp["checkbox_pm_on_{$option}"] ?? $option));
endforeach;
\App\Support\Html::trSmall($lang_usercp['row_pms'] ?? 'PMs', $pmY, 1);

\App\Support\Html::trSmall($lang_usercp['row_gender'] ?? 'Gender', '<input type=radio name=gender'.(($CURUSER['gender'] ?? '') === 'N/A' ? ' checked' : '').' value=N/A>'.htmlspecialchars($lang_usercp['radio_not_available'] ?? '').'
<input type=radio name=gender'.(($CURUSER['gender'] ?? '') === 'Male' ? ' checked' : '').' value=Male>'.htmlspecialchars($lang_usercp['radio_male'] ?? '').'<input type=radio name=gender'.(($CURUSER['gender'] ?? '') === 'Female' ? ' checked' : '').' value=Female>'.htmlspecialchars($lang_usercp['radio_female'] ?? ''), 1);

\App\Support\Html::trSmall($lang_usercp['row_tracker_url'] ?? 'Tracker URL', "<select name=tracker_url_id>\n".$personal['trackerUrlOptions']."\n</select>".'<br /><font class=small size=1>'.htmlspecialchars($lang_usercp['row_tracker_url_help'] ?? '').'</font>', 1);
\App\Support\Html::trSmall($lang_usercp['row_country'] ?? 'Country', "<select name=country>\n".'<option value=0>---- '.htmlspecialchars($personal['selectNoneLabel'])." ----</option>\n".$personal['countryOptions']."\n</select>", 1);

$avatarCell = '<img src='.($CURUSER['avatar'] ?? '' ? "'".htmlspecialchars((string) $CURUSER['avatar'])."'" : "'".htmlspecialchars($personal['defaultAvatarUrl'])."'")." name='avatarimg'><br />
  <select name=savatar OnChange=\"document.forms[0].avatarimg.src=this.value;this.form.avatar.value=this.value;\">
  <option value='".htmlspecialchars((string) ($CURUSER['avatar'] ?? ''))."'>".htmlspecialchars($personal['selectChooseAvatar'])."</option>
  <option value='".htmlspecialchars($personal['defaultAvatarUrl'])."'>".htmlspecialchars($personal['selectNothing'])."</option>
  ".$personal['bitbucketOptions']."
  </select><input type=text name=avatar style=\"width: 400px\" value=\"".htmlspecialchars((string) ($CURUSER['avatar'] ?? '')).
  "\"><br />\n".htmlspecialchars($lang_usercp['text_avatar_note'] ?? '').($enablebitbucket_main === 'yes' ? htmlspecialchars($lang_usercp['text_bitbucket_note'] ?? '') : '');
\App\Support\Html::trSmall($lang_usercp['row_avatar_url'] ?? 'Avatar URL', $avatarCell, 1);

\App\Support\Html::tr($lang_usercp['row_info'] ?? 'Info', '<textarea name="info" style="width:700px" rows="10" >'.htmlspecialchars((string) ($CURUSER['info'] ?? '')).'</textarea><br />'.htmlspecialchars($lang_usercp['text_info_note'] ?? ''), 1);
@endphp
<tr><td class="rowhead" valign="top" align="right">{{ $lang_usercp['row_save_settings'] ?? 'Save' }}</td><td class="rowfollow" valign="top" align=left><input type=submit value="{{ $lang_usercp['submit_save_settings'] ?? 'Save' }}"></td></tr>
</table></form>

@php
/** @var array<string, mixed> $security */
/** @var array<string, mixed> $lang */
/** @var array<string, mixed> $curUser */
/** @var string $type */
$lang_usercp = $lang;
$CURUSER = $curUser;
$CONTENT_WIDTH = $contentWidth;
$sec = $security;
@endphp
@include('usercp.sections._menu', ['selected' => 'security'])

@php
$formId = 'security';
$formType = $sec['isConfirm'] ? 'confirm' : 'save';
@endphp
<form method=post action=usercp.php id="{{ $formId }}"><input type=hidden name=action value=security><input type=hidden name=type value={{ $formType }}>
<table border=0 cellspacing=0 cellpadding=5 width={{ $CONTENT_WIDTH }}>
@if ($sec['isConfirm'])
@php
$ch = $sec['confirmHidden'];
if ($ch['resetpasskey'] === '1') { echo '<input type="hidden" name="resetpasskey" value="1">'; }
if ($ch['resetauthkey'] === '1') { echo '<input type="hidden" name="resetauthkey" value="1">'; }
@endphp
<input type="hidden" name="email" value="{{ $ch['email'] }}">
<input type="hidden" name="chpassword" value="{{ $ch['chpassword'] }}">
<input type="hidden" name="privacy" value="{{ $ch['privacy'] }}">
<input type="hidden" name="two_step_secret" value="{{ $ch['two_step_secret'] }}">
<input type="hidden" name="two_step_code" value="{{ $ch['two_step_code'] }}">
<tr><td class="rowhead nowrap" valign="top" align="right" width=1%>{{ $lang_usercp['row_security_check'] ?? 'Check' }}</td><td valign="top" align="left" width="99%"><input type=password class=oldpassword style="width: 200px"><br /><font class=small>{{ $lang_usercp['text_security_check_note'] ?? '' }}</font></td></tr>
<input type=hidden name=username value="{{ htmlspecialchars((string) ($CURUSER['username'] ?? '')) }}">
<input type=hidden name=response>
{!! $sec['confirmHtml'] !!}
<tr><td class="rowhead" valign="top" align="right">{{ $lang_usercp['row_save_settings'] ?? 'Save' }}</td><td class="rowfollow" valign="top" align=left><input type=button value="{{ $lang_usercp['submit_save_settings'] ?? 'Save' }}"></td></tr>
</table></form>
@php
\App\Support\Form::passwordChallengeJs('security', 'username', 'oldpassword');
@endphp
@else
@if ($type === 'saved')
@php
$savedMsg = htmlspecialchars($lang_usercp['text_saved'] ?? '');
if ($sec['savedFlags']['mail']) { $savedMsg .= ' '.htmlspecialchars($lang_usercp['std_confirmation_email_sent'] ?? ''); }
if ($sec['savedFlags']['passkey']) { $savedMsg .= ' '.htmlspecialchars($lang_usercp['std_passkey_reset'] ?? ''); }
if ($sec['savedFlags']['password']) { $savedMsg .= ' '.htmlspecialchars($lang_usercp['std_password_changed'] ?? ''); }
if ($sec['savedFlags']['privacy']) { $savedMsg .= ' '.htmlspecialchars($lang_usercp['std_privacy_level_updated'] ?? ''); }
@endphp
<tr><td colspan=2 class="heading" valign="top" align="center"><font color=red>{{ $savedMsg }}</font></td></tr>
@endif
@php
\App\Support\Html::trSmall($lang_usercp['row_reset_passkey'] ?? 'Reset passkey', '<input type=checkbox name=resetpasskey value=1 />'.htmlspecialchars($lang_usercp['checkbox_reset_my_passkey'] ?? '').'<br /><font class=small>'.htmlspecialchars($lang_usercp['text_reset_passkey_note'] ?? '').'</font>', 1);

// Two-step authentication
if ($sec['twoStep']['hasSecret']):
    \App\Support\Html::trSmall($lang_usercp['row_two_step_secret'] ?? 'Two-step secret', '<input type=text name=two_step_code />'.htmlspecialchars($lang_usercp['text_two_step_secret_unbind_note'] ?? ''), 1);
else:
    $ts = $sec['twoStep'];
    $twoStepY = '<div style="display: flex;align-items:center">';
    $twoStepY .= sprintf('<div><img src="%s" /></div>', htmlspecialchars($ts['qrCodeUrl']));
    $twoStepY .= sprintf(
        '<div style="padding-left: 20px">%s<a href="%s" target="_blank">Link</a><br /><br />%s%s<br/><br/>%s<input type=hidden name=two_step_secret value="%s" /><input type=text name=two_step_code readonly onfocus="this.removeAttribute(\'readonly\')"/></div>',
        htmlspecialchars($lang_usercp['text_two_step_secret_bind_by_qrdoe_note'] ?? ''),
        htmlspecialchars($ts['qrCodeUrl']),
        htmlspecialchars($lang_usercp['text_two_step_secret_bind_manually_note'] ?? ''),
        htmlspecialchars($ts['secret']),
        htmlspecialchars($lang_usercp['text_two_step_secret_bind_complete_note'] ?? ''),
        htmlspecialchars($ts['secret'])
    );
    $twoStepY .= '</div>';
    \App\Support\Html::trSmall($lang_usercp['row_two_step_secret'] ?? 'Two-step secret', $twoStepY, 1);
endif;

// Passkey list
printf('<tr><td class="rowhead" valign="top" align="right">%s</td><td class="rowfollow" valign="top" align="left">', htmlspecialchars(\App\Support\Locale::trans('passkey.passkey', [], null)));
echo $sec['passkeyListHtml'];
printf('</td></tr>');

if ($sec['showEmailChange']):
    \App\Support\Html::trSmall($lang_usercp['row_email_address'] ?? 'Email', '<input type="text" name="email" style="width: 200px" value="'.htmlspecialchars((string) ($CURUSER['email'] ?? '')).'" /> <br /><font class=small>'.htmlspecialchars($lang_usercp['text_email_address_note'] ?? '').'</font>', 1);
endif;

\App\Support\Html::trSmall($lang_usercp['row_change_password'] ?? 'Change password', '<input type="password" class="password" style="width: 200px" />', 1);
@endphp
<input type="hidden" name="chpassword" />
@php
\App\Support\Html::trSmall($lang_usercp['row_type_password_again'] ?? 'Password again', '<input type="password" class="passagain" style="width: 200px" />', 1);
\App\Support\Html::trSmall($lang_usercp['row_privacy_level'] ?? 'Privacy', $sec['privacyRadios']['normal'].' '.$sec['privacyRadios']['low'].' '.$sec['privacyRadios']['strong'], 1);
@endphp
<tr><td class="rowhead" valign="top" align="right">{{ $lang_usercp['row_save_settings'] ?? 'Save' }}</td><td class="rowfollow" valign="top" align=left><input type=button value="{{ $lang_usercp['submit_save_settings'] ?? 'Save' }}"></td></tr>
</table></form>
@php
\App\Support\Form::passwordHashJs('security', 'password', 'chpassword', false, 'passagain', 'username');
@endphp
@endif

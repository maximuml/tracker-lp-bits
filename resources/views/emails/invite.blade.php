{{ sprintf(__('takeinvite.mail_one'), $siteName) }}{{ $senderUsername }}{{ sprintf(__('takeinvite.mail_two'), $siteName, $siteName) }}
<b><a href="{{ $signupUrl }}">{{ __('takeinvite.mail_here') }}</a></b><br />
{{ $signupUrl }}
<br />{{ __('takeinvite.mail_three') }}{{ $inviteTimeout }}{{ sprintf(__('takeinvite.mail_four'), $siteName) }}{{ $senderUsername }}{{ __('takeinvite.mail_five') }}<br />
{{ $personalBody }}
<br /><br />{{ sprintf(__('takeinvite.mail_six'), $reportMail, $siteName) }}
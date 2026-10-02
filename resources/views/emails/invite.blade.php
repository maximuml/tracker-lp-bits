{{ sprintf(__('legacy/takeinvite.mail_one'), $siteName) }}{{ $senderUsername }}{{ sprintf(__('legacy/takeinvite.mail_two'), $siteName, $siteName) }}
<b><a href="{{ $signupUrl }}">{{ __('legacy/takeinvite.mail_here') }}</a></b><br />
{{ $signupUrl }}
<br />{{ __('legacy/takeinvite.mail_three') }}{{ $inviteTimeout }}{{ sprintf(__('legacy/takeinvite.mail_four'), $siteName) }}{{ $senderUsername }}{{ __('legacy/takeinvite.mail_five') }}<br />
{{ $personalBody }}
<br /><br />{{ sprintf(__('legacy/takeinvite.mail_six'), $reportMail, $siteName) }}
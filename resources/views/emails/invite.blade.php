{{ sprintf(__('takeinvite.mail_one'), $vm->siteName) }}{{ $vm->senderUsername }}{{ sprintf(__('takeinvite.mail_two'), $vm->siteName, $vm->siteName) }}
<b><a href="{{ $vm->signupUrl }}">{{ __('takeinvite.mail_here') }}</a></b><br />
{{ $vm->signupUrl }}
<br />{{ __('takeinvite.mail_three') }}{{ $vm->inviteTimeout }}{{ sprintf(__('takeinvite.mail_four'), $vm->siteName) }}{{ $vm->senderUsername }}{{ __('takeinvite.mail_five') }}<br />
{{ $vm->personalBody }}
<br /><br />{{ sprintf(__('takeinvite.mail_six'), $vm->reportMail, $vm->siteName) }}
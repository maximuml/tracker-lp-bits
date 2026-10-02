{{ __('legacy/usercp.mail_change_email_one') }}{{ $username }}{{ sprintf(__('legacy/usercp.mail_change_email_two'), $siteName) }}({{ $email }}){{ __('legacy/usercp.mail_change_email_three') }}

{{ __('legacy/usercp.mail_change_email_four') }}{{ $ip }}{{ __('legacy/usercp.mail_change_email_five') }}

{{ __('legacy/usercp.mail_change_email_six') }}&nbsp;<b><a href="{{ $confirmUrl }}">{{ __('legacy/usercp.mail_here') }}</a></b>&nbsp;{{ __('legacy/usercp.mail_change_email_six_1') }}<br />
{{ $confirmUrl }}

{{ __('legacy/usercp.mail_change_email_seven') }}

------<br />{{ __('legacy/usercp.mail_change_email_eight') }}
{{ sprintf(__('legacy/usercp.mail_change_email_nine'), $siteName) }}
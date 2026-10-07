{{ __('usercp.mail_change_email_one') }}{{ $username }}{{ sprintf(__('usercp.mail_change_email_two'), $siteName) }}({{ $email }}){{ __('usercp.mail_change_email_three') }}

{{ __('usercp.mail_change_email_four') }}{{ $ip }}{{ __('usercp.mail_change_email_five') }}

{{ __('usercp.mail_change_email_six') }}&nbsp;<b><a href="{{ $confirmUrl }}">{{ __('usercp.mail_here') }}</a></b>&nbsp;{{ __('usercp.mail_change_email_six_1') }}<br />
{{ $confirmUrl }}

{{ __('usercp.mail_change_email_seven') }}

------<br />{{ __('usercp.mail_change_email_eight') }}
{{ sprintf(__('usercp.mail_change_email_nine'), $siteName) }}
{{ \App\Support\Locale::trans('message.mail_dear', [], $locale) }}{{ $recipientUsername }},

{{ \App\Support\Locale::trans('message.mail_you_received_a_pm', [], $locale) }}

{{ \App\Support\Locale::trans('message.mail_sender', [], $locale) }}: {{ $senderUsername }}
{{ \App\Support\Locale::trans('message.mail_subject', [], $locale) }}: {{ $subject }}
{{ \App\Support\Locale::trans('message.mail_date', [], $locale) }}: {{ $date }}
{{ \App\Support\Locale::trans('message.mail_use_following_url', [], $locale) }}&nbsp;<b><a href="{{ $messageUrl }}">{{ \App\Support\Locale::trans('message.mail_here', [], $locale) }}</a></b>&nbsp;{{ \App\Support\Locale::trans('message.mail_use_following_url_1', [], $locale) }}<br />
{{ $messageUrl }}

------<br />{{ \App\Support\Locale::trans('message.mail_yours', [], $locale) }}
{{ sprintf(\App\Support\Locale::trans('message.mail_the_site_team', [], $locale), $siteName) }}
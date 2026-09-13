@props(['id'])
@safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml(\App\Support\UserDisplay::username((int) $id)))

@props(['caption', 'center' => true])
@safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml(\App\Support\Frame::open((string) $caption, (bool) $center, 10, '100%', 'left')))
{{ $slot }}
@safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml(\App\Support\Frame::CLOSE))

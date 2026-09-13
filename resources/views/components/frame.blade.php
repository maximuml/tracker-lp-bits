@props(['caption'])
@safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml(\App\Support\Frame::open((string) $caption, true, 10, '100%', 'left')))
{{ $slot }}
@safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml(\App\Support\Frame::CLOSE))

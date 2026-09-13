@props(['caption'])
@safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml(\App\Support\Frame::open((string) $caption, true, 10, '100%', 'left')))
@safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml(\App\Support\Frame::tableOpen(false, 5)))
{{ $slot }}
@safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml(\App\Support\Frame::TABLE_CLOSE))
@safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml(\App\Support\Frame::CLOSE))

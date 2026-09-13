@props(['up' => 0, 'down' => 0, 'infinite' => 'Inf.', 'decimals' => 2, 'alwaysWrap' => false])
@if ((float) $down > 0)
@safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml(\App\Support\Ratio::leaderboard((float) $up, (float) $down, (int) $decimals, (bool) $alwaysWrap)))
@else
{{ $infinite }}
@endif

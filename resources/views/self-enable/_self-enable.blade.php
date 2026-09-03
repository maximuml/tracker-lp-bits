<?php
\App\Support\AssetAppender::css('#ban-info td {border: none}', 'header', false);

\App\Support\Html::beginFrame($title, true, 10, '100%', 'center');

if ($unit <= 0) {
    printf('<h3>%s</h3>', \App\Support\Locale::trans('self-enable.feature_disabled', [], null));
} elseif ($enabled) {
    printf('<h3>%s</h3>', \App\Support\Locale::trans('self-enable.enable_status_normal', [], null));
} elseif (! $latestBanLog) {
    printf('<h3>%s</h3>', \App\Support\Locale::trans('self-enable.no_ban_info', [], null));
} else {
    $elapsedDay = (int) $elapsedDay;
    $total = (float) $total;
    $isUserBonusEnough = (bool) $isUserBonusEnough;
    $showError = (bool) ($showError ?? false);

    if ($showError) {
        if (! $isUserBonusEnough) {
            \App\Support\Html::stdMessage('Error', $insufficientMessage);
        }
    } else {
        printf('<h3>%s</h3>', \App\Support\Locale::trans('self-enable.latest_ban_info', [], null));
        printf('<table id="ban-info" border="1" cellpadding="5" cellspacing="0"><tbody>');
        printf('<tr><th>UID：</th><td>%s</td></tr>', $latestBanLog->uid);
        printf('<tr><th>Username：</th><td>%s</td></tr>', $latestBanLog->username);
        printf('<tr><th>Reason：</th><td>%s</td></tr>', $latestBanLog->reason);
        printf('<tr><th>CreatedAt：</th><td>%s</td></tr>', $latestBanLog->created_at);
        printf('</tbody></table>');
        printf('<p>%s</p>', \App\Support\Locale::trans('self-enable.deduct_bonus_per_day', ['unit' => number_format($unit)], null));
        printf('<p>%s</p>', \App\Support\Locale::trans('self-enable.deduct_bonus_total', ['days' => number_format($elapsedDay), 'total' => number_format($total)], null));
        if ($isUserBonusEnough) {
            printf('<p>%s</p>', \App\Support\Locale::trans('self-enable.enable_desc', [], null));
            printf('<form method="post"><input type="hidden" name="submit" value="1"><input type="submit" value="%s"></form>', \App\Support\Locale::trans('self-enable.enable_button', [], null));
        } else {
            printf('<p>%s</p>', $insufficientMessage);
        }
    }
}
\App\Support\Html::endFrame();
?>

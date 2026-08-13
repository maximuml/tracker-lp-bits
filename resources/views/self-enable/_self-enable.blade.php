<?php
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING & ~E_DEPRECATED);

\Nexus\Nexus::css('#ban-info td {border: none}', 'header', false);

$title = \App\Support\Locale::trans('self-enable.title', [], null);
\App\Support\Html::stdhead($title);
\App\Support\Frame::mainFrameOpen();
\App\Support\Html::beginFrame($title, true, 10, "100%", "center");
$unit = \App\Models\Setting::getSelfEnableBonus();
if ($unit <= 0) {
    printf('<h3>%s</h3>', \App\Support\Locale::trans('self-enable.feature_disabled', [], null));
} elseif ($CURUSER['enabled'] == 'yes') {
    printf('<h3>%s</h3>', \App\Support\Locale::trans('self-enable.enable_status_normal', [], null));
} else {
    $latestBanLog = \App\Models\UserBanLog::query()
        ->where('uid', $CURUSER['id'])
        ->orderBy('id', 'desc')
        ->first();
    if (!$latestBanLog) {
        printf('<h3>%s</h3>', \App\Support\Locale::trans('self-enable.no_ban_info', [], null));
    } else {
        $elapsedDay = ceil((time() - $latestBanLog->created_at->getTimestamp()) / 86400);
        $total = $unit * $elapsedDay;
        $isUserBonusEnough = $CURUSER['seedbonus'] >= $total;
        $userBonusNotEnoughTip = \App\Support\Locale::trans('self-enable.bonus_not_enough', ['bonus' => $CURUSER['seedbonus']], null);
        if (!empty(\App\Support\SupportContext::getPost('submit'))) {
            if (!$isUserBonusEnough) {
                \App\Support\Html::stdMessage('Error', $userBonusNotEnoughTip);
            } else {
                $userRep = new \App\Repositories\UserRepository();
                $bonusRep = new \App\Repositories\BonusRepository();
                $operator = \App\Models\User::query()->find($CURUSER['id']);
                $bonusRep->consumeUserBonus($CURUSER['id'], $total, \App\Models\BonusLogs::BUSINESS_TYPE_SELF_ENABLE, $title);
                $userRep->enableUser($operator, $CURUSER['id'], $title);
                \App\Support\LegacyResponse::redirect('index.php');
            }
        } else {
            printf('<h3>%s</h3>', \App\Support\Locale::trans('self-enable.latest_ban_info', [], null));
            printf('<table id="ban-info" border="1" cellpadding="5" cellspacing="0"><tbody>');
            printf('<tr><th>UID：</th><td>%s</td></tr>', $latestBanLog->uid);
            printf('<tr><th>Username：</th><td>%s</td></tr>',  $latestBanLog->username);
            printf('<tr><th>Reason：</th><td>%s</td></tr>', $latestBanLog->reason);
            printf('<tr><th>CreatedAt：</th><td>%s</td></tr>', $latestBanLog->created_at);
            printf('</tbody></table>');
            printf('<p>%s</p>', \App\Support\Locale::trans('self-enable.deduct_bonus_per_day', ['unit' => number_format($unit)], null));
            printf('<p>%s</p>', \App\Support\Locale::trans('self-enable.deduct_bonus_total', ['days' => number_format($elapsedDay), 'total' => number_format($total)], null));
            if ($isUserBonusEnough) {
                printf('<p>%s</p>', \App\Support\Locale::trans('self-enable.enable_desc', [], null));
                printf('<form method="post"><input type="hidden" name="submit" value="1"><input type="submit" value="%s"></form>', \App\Support\Locale::trans('self-enable.enable_button', [], null));
            } else {
                printf('<p>%s</p>', $userBonusNotEnoughTip);
            }
        }
    }
}
\App\Support\Html::endFrame();
\App\Support\Frame::mainFrameClose();
\App\Support\Html::stdfoot();

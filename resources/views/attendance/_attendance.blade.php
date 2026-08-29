<?php
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING & ~E_DEPRECATED);

\App\Support\AssetAppender::css('vendor/fullcalendar-5.10.2/main.min.css', 'header', true);
\App\Support\AssetAppender::js('vendor/fullcalendar-5.10.2/main.min.js', 'footer', true);

$iv = $iv ?? '';

if ($localeJs !== null) {
    \App\Support\AssetAppender::js("vendor/fullcalendar-5.10.2/locales/{$localeJs}.js", 'footer', true);
}


if ($hasAttendedToday) {
    $count = $attendance->total_days;
    $cdays = $attendance->days;
    $points = $attendance->points;

    $headerLeft = sprintf($lang_attendance['attend_info'] . $lang_attendance['retroactive_description'], $count, $cdays, $points, $CURUSER['attendance_card']);
    $headerRight = \App\Support\Locale::trans('attendance.ranking', ['ranking' => $myRanking, 'counts' => $todayCounts], null);

    \App\Support\Html::beginFrame($lang_attendance['success']);
    printf('<p>%s<span style="float:right">%s</span></p>', $headerLeft, $headerRight);
    \App\Support\Html::endFrame();

    $eventStr = json_encode($events);
    $validRangeStr = json_encode($validRange);

    $calendarScript = <<<EOP
let events = JSON.parse('$eventStr')
let validRange = JSON.parse('$validRangeStr')
let confirmText = "{$lang_attendance['retroactive_confirm_tip']}"
document.addEventListener('DOMContentLoaded', function() {
    var calendarEl = document.getElementById('calendar');
    var calendar = new FullCalendar.Calendar(calendarEl, {
      initialView: 'dayGridMonth',
      locale: '$localeJs',
      events: events,
      validRange: validRange,
      eventClick: function(info) {
        if (info.event.groupId == 'to_do') {
            retroactive(info.event.startStr)
        }
      }
    });
    calendar.render();
});

function retroactive(dateStr) {
    if (!window.confirm(confirmText + dateStr + ' ?')) {
        return
    }
    jQuery.post('ajax.php', {params: {date: dateStr}, action: 'attendanceRetroactive'}, function (response) {
        if (response.ret != 0) {
            alert(response.msg)
        } else {
            location.reload();
        }
    }, 'json')
}
EOP;

    \App\Support\AssetAppender::js($calendarScript, 'footer', false);

    echo '<div style="display: flex;justify-content: center;padding: 20px 0"><div id="calendar" style="width: 60%"></div></div>';
    echo '<ul>';
    printf('<li>'.$lang_attendance['initial'].'</li>', $attendance_initial_bonus);
    printf('<li>'.$lang_attendance['steps'].'</li>', $attendance_step_bonus, $attendance_max_bonus);
    echo '<li><ol>';
    foreach ($attendance_continuous_bonus as $day => $value) {
        printf('<li>'.$lang_attendance['continuous'].'</li>', $day, $value);
    }
    echo '</ol></li>';
    echo '</ul>';
} else {
    $buttonLabel = $lang_attendance['attend_button'] ?? 'Check in';
    \App\Support\Html::beginFrame($lang_attendance['title']);
    echo '<table width="100%" border="1" cellspacing="0" cellpadding="10"><tbody>';
    echo '<tr><td class="text">';
    echo '<div style="margin-top: 20px; text-align: center;">';
    echo '<form method="post" action="attendance.php" style="display: inline-block;">';
    echo '<table border="0" cellpadding="5">';
    if ($attendanceCaptchaEnabled && $iv == 'yes') {
        \App\Support\Captcha::showImageCode();
    }
    echo '<tr><td class="toolbox" colspan="2" align="center"><input type="submit" value="' . htmlspecialchars($buttonLabel, ENT_QUOTES, 'UTF-8') . '" class="btn" /></td></tr>';
    echo '</table>';
    echo '</form>';
    echo '</div>';
    echo '</td></tr>';
    echo '</tbody></table>';
    \App\Support\Html::endFrame();
}


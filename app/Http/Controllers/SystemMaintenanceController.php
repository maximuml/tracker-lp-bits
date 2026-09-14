<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Repositories\MysqlStatsRepository;
use App\Services\CleanupService;
use App\Support\CurrentUser;
use App\Support\Email;
use App\Support\Globals;
use App\Support\Language;
use App\Support\Mail;
use App\Support\UserDisplay;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

class SystemMaintenanceController extends LegacyController
{
    public function docleanup(Request $request): Response
    {

        return \response(
            app(CleanupService::class)->runFull($request->boolean('forceall'), true),
            200,
            ['Content-Type' => 'text/html; charset=utf-8']
        );

    }

    public function mailtestAction(Request $request): View|RedirectResponse|Response
    {
        return $this->mailtest($request);
    }

    public function mailtest(Request $request): View|RedirectResponse|Response
    {
        if (app(CurrentUser::class)->get() === null) {
            $qs = $request->getQueryString();

            return redirect('/mailtest.php'.($qs ? '?'.$qs : ''));
        }

        if (UserDisplay::currentClass() < UC_SYSOP) {
            return $this->legacyAbortResponse('Error', 'Permission denied.');
        }

        $langMailtest = (array) (app(Globals::class)->get('lang_mailtest') ?? []);
        $langFunctions = app(Language::class)->functions();

        if ($request->post('action') === 'sendmail') {
            $email = Email::sanitizeForDisplay((string) trim((string) $request->post('email', '')));
            if (! Email::isWellFormed($email)) {
                return $this->legacyAbortResponse(
                    (string) ($langMailtest['std_error'] ?? 'Error'),
                    (string) ($langMailtest['std_invalid_email_address'] ?? 'Invalid email address'),
                );
            }

            $globals = app(Globals::class);
            $siteName = (string) ($globals->get('SITENAME', '') ?? '');
            $siteEmail = (string) ($globals->get('SITEEMAIL', '') ?? '');
            $title = $siteName.($langMailtest['text_smtp_testing_mail'] ?? '');
            $body = (string) ($langMailtest['mail_test_mail_content'] ?? '');
            $sendResult = Mail::sentLegacy($email, $siteName, $siteEmail, $title, $body, 'mailtest', false, false, '', 'UTF-8');

            if ($sendResult === true) {
                return $this->legacyAbortResponse(
                    (string) ($langMailtest['std_success'] ?? 'Success'),
                    (string) ($langMailtest['std_success_note'] ?? 'Mail sent successfully.'),
                );
            }

            return $this->legacyAbortResponse(
                (string) ($langFunctions['std_error'] ?? 'Error'),
                (string) ($langFunctions['text_unable_to_send_mail'] ?? 'Unable to send mail.').' (SMTP disabled or mail not sent)',
                false,
            );
        }

        return $this->legacyPage($request, 'mailtest', true, [
            'lang_mailtest' => $langMailtest,
        ]);
    }

    public function mysqlStats(Request $request): View|RedirectResponse|Response
    {
        if (app(CurrentUser::class)->get() === null) {
            $qs = $request->getQueryString();

            return redirect('/mysql_stats.php'.($qs ? '?'.$qs : ''));
        }

        if (UserDisplay::currentClass() < UC_SYSOP) {
            abort(403);
        }

        $rep = app(MysqlStatsRepository::class);
        $status = $rep->status();

        $uptimeSeconds = max(1, (int) $status['uptimeSeconds']);
        $connections = (int) $status['connections'];
        $questions = (int) $status['questions'];
        $queryStatsDenominator = max(1, $questions - $connections);

        $byteTotal = fn (float $v): string => implode(' ', $rep->formatByteDown($v));
        $num = fn (float $v, int $dec = 0): string => number_format($v, $dec, '.', ',');

        $queryStatRows = [];
        foreach ($status['queryStats'] as $name => $value) {
            $queryStatRows[] = [
                'name' => (string) $name,
                'value' => $num((float) $value),
                'perHour' => $num((float) $value * 3600 / $uptimeSeconds, 2),
                'pct' => $num((float) $value * 100 / $queryStatsDenominator, 2),
            ];
        }
        $queryStatColumns = [[]];
        $querySplitAt = (int) ceil(count($queryStatRows) / 2);
        $countRows = 0;
        foreach ($queryStatRows as $r) {
            $queryStatColumns[array_key_last($queryStatColumns)][] = $r;
            if (++$countRows === $querySplitAt) {
                $queryStatColumns[] = [];
            }
        }

        $statusRows = [];
        foreach ($status['serverStatus'] as $name => $value) {
            $statusRows[] = ['name' => str_replace('_', ' ', (string) $name), 'value' => (string) $value];
        }
        $statusColumns = [[]];
        $totalRows = count($statusRows);
        $split1 = (int) ceil($totalRows / 3);
        $split2 = (int) ceil($totalRows * 2 / 3);
        $countRows = 0;
        foreach ($statusRows as $r) {
            $statusColumns[array_key_last($statusColumns)][] = $r;
            if (++$countRows === $split1 || $countRows === $split2) {
                $statusColumns[] = [];
            }
        }

        return $this->legacyPage($request, 'mysql_stats', true, [
            'serverRunningText' => 'This MySQL server has been running for '.$rep->timespanFormat($uptimeSeconds).'. It started up on '.$rep->localisedDate($status['startTime']),
            'receivedTotal' => $byteTotal($status['bytesReceived']),
            'receivedPerHour' => $byteTotal($status['bytesReceived'] * 3600 / $uptimeSeconds),
            'sentTotal' => $byteTotal($status['bytesSent']),
            'sentPerHour' => $byteTotal($status['bytesSent'] * 3600 / $uptimeSeconds),
            'totalBytesTotal' => $byteTotal($status['totalBytes']),
            'totalBytesPerHour' => $byteTotal($status['totalBytes'] * 3600 / $uptimeSeconds),
            'abortedConnects' => $num($status['abortedConnects']),
            'abortedConnectsPerHour' => $num($status['abortedConnects'] * 3600 / $uptimeSeconds, 2),
            'abortedConnectsPct' => $connections > 0 ? $num($status['abortedConnects'] * 100 / $connections, 2).'&nbsp;%' : '---',
            'abortedClients' => $num($status['abortedClients']),
            'abortedClientsPerHour' => $num($status['abortedClients'] * 3600 / $uptimeSeconds, 2),
            'abortedClientsPct' => $connections > 0 ? $num($status['abortedClients'] * 100 / $connections, 2).'&nbsp;%' : '---',
            'connectionsTotal' => $num($connections),
            'connectionsPerHour' => $num($connections * 3600 / $uptimeSeconds, 2),
            'questionsTotal' => $num($questions),
            'questionsPerHour' => $num($questions * 3600 / $uptimeSeconds, 2),
            'questionsPerMinute' => $num($questions * 60 / $uptimeSeconds, 2),
            'questionsPerSecond' => $num($questions / $uptimeSeconds, 2),
            'queryStatColumns' => $queryStatColumns,
            'statusColumns' => $statusColumns,
            'hasServerStatus' => $statusRows !== [],
        ]);
    }

    public function cron(Request $request): Response
    {

        return \response(
            app(CleanupService::class)->triggerCron(),
            200,
            ['Content-Type' => 'text/plain; charset=utf-8']
        );

    }
}

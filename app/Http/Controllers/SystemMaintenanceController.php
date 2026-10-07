<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Contracts\CleanupServiceInterface;
use App\Contracts\Repositories\MysqlStatsRepositoryInterface;
use App\Http\Requests\CleanupRequest;
use App\Http\Requests\MailTestRequest;
use App\Support\Config\SiteConfig;
use App\Support\CurrentUser;
use App\Support\Email;
use App\Support\Html\SafeHtml;
use App\Support\Mail;
use App\Support\UserDisplay;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

class SystemMaintenanceController extends LegacyController
{
    public function __construct(
        private readonly CurrentUser $currentUser,
        private readonly MysqlStatsRepositoryInterface $mysqlStatsRepository,
        private readonly CleanupServiceInterface $cleanupService,
    ) {}

    public function docleanup(Request $request): RedirectResponse
    {
        // Renamed endpoint — 308 replays the body + query string unchanged.
        $qs = $request->getQueryString();
        $suffix = $qs !== null && $qs !== '' ? '?'.$qs : '';

        return redirect()->to('/web/system/cleanup'.$suffix, 308);
    }

    public function cleanup(CleanupRequest $request): Response
    {

        return \response(
            $this->cleanupService->runFull($request->boolean('forceall'), true),
            200,
            ['Content-Type' => 'text/html; charset=utf-8']
        );

    }

    public function mailtestAction(Request $request): RedirectResponse
    {
        // Renamed endpoint — 308 replays the body + query string unchanged.
        $qs = $request->getQueryString();
        $suffix = $qs !== null && $qs !== '' ? '?'.$qs : '';

        return redirect()->to('/web/system/mail-test'.$suffix, 308);
    }

    public function mailtestSubmit(MailTestRequest $request): View|RedirectResponse|Response
    {
        return $this->mailtest($request);
    }

    public function mailtest(Request $request): View|RedirectResponse|Response
    {
        if ($this->currentUser->get() === null) {
            $qs = $request->getQueryString();

            return redirect('/web/mailtest'.($qs ? '?'.$qs : ''));
        }

        if (UserDisplay::currentClass() < UC_SYSOP) {
            return $this->abortResponse('Error', 'Permission denied.');
        }

        if ($request->post('action') === 'sendmail') {
            $email = Email::sanitizeForDisplay((string) trim((string) $request->post('email', '')));
            if (! Email::isWellFormed($email)) {
                return $this->abortResponse(
                    (string) (__('mailtest.std_error')),
                    (string) (__('mailtest.std_invalid_email_address')),
                );
            }

            $smtpType = SiteConfig::current()->smtp->type('');
            if ($smtpType === '' || $smtpType === 'none') {
                return $this->abortResponse(
                    (string) (__('functions.std_error')),
                    (string) (__('functions.text_unable_to_send_mail')).' (SMTP disabled)',
                    false,
                );
            }

            $siteName = SiteConfig::current()->basic->siteName();
            $siteEmail = SiteConfig::current()->main->siteEmail();
            $title = $siteName.(__('mailtest.text_smtp_testing_mail'));
            $body = (string) (__('mailtest.mail_test_mail_content'));
            $sendResult = Mail::queueLegacy($email, $siteName, $siteEmail, $title, $body, 'mailtest', false, false, '', 'UTF-8');

            if ($sendResult === true) {
                return $this->abortResponse(
                    (string) (__('mailtest.std_success')),
                    (string) (__('mailtest.std_success_note')),
                );
            }

            return $this->abortResponse(
                (string) (__('functions.std_error')),
                (string) (__('functions.text_unable_to_send_mail')).' (SMTP disabled or mail not sent)',
                false,
            );
        }

        return $this->renderPage($request, 'mailtest', true, [
        ]);
    }

    public function mysqlStats(Request $request): View|RedirectResponse|Response
    {
        if ($this->currentUser->get() === null) {
            $qs = $request->getQueryString();

            return redirect('/web/mysql_stats'.($qs ? '?'.$qs : ''));
        }

        if (UserDisplay::currentClass() < UC_SYSOP) {
            abort(403);
        }

        $rep = $this->mysqlStatsRepository;
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

        return $this->renderPage($request, 'mysql_stats', true, [
            'serverRunningText' => 'This MySQL server has been running for '.$rep->timespanFormat($uptimeSeconds).'. It started up on '.$rep->localisedDate($status['startTime']),
            'receivedTotal' => $byteTotal($status['bytesReceived']),
            'receivedPerHour' => $byteTotal($status['bytesReceived'] * 3600 / $uptimeSeconds),
            'sentTotal' => $byteTotal($status['bytesSent']),
            'sentPerHour' => $byteTotal($status['bytesSent'] * 3600 / $uptimeSeconds),
            'totalBytesTotal' => $byteTotal($status['totalBytes']),
            'totalBytesPerHour' => $byteTotal($status['totalBytes'] * 3600 / $uptimeSeconds),
            'abortedConnects' => $num($status['abortedConnects']),
            'abortedConnectsPerHour' => $num($status['abortedConnects'] * 3600 / $uptimeSeconds, 2),
            'abortedConnectsPct' => SafeHtml::fromTrustedHtml($connections > 0 ? $num($status['abortedConnects'] * 100 / $connections, 2).'&nbsp;%' : '---'),
            'abortedClients' => $num($status['abortedClients']),
            'abortedClientsPerHour' => $num($status['abortedClients'] * 3600 / $uptimeSeconds, 2),
            'abortedClientsPct' => SafeHtml::fromTrustedHtml($connections > 0 ? $num($status['abortedClients'] * 100 / $connections, 2).'&nbsp;%' : '---'),
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
            $this->cleanupService->triggerCron(),
            200,
            ['Content-Type' => 'text/plain; charset=utf-8']
        );

    }
}

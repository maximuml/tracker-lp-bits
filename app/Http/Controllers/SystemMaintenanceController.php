<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Repositories\MysqlStatsRepository;
use App\Services\CleanupService;
use App\Support\CurrentUser;
use App\Support\Email;
use App\Support\Globals;
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
        $langFunctions = (array) (app(Globals::class)->get('lang_functions') ?? []);

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

        return $this->legacyPage($request, 'mysql_stats', true, app(MysqlStatsRepository::class)->status());
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

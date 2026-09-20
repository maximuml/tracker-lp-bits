<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

/**
 * Collects Content-Security-Policy violation reports.
 *
 * Browsers POST here in one of two formats:
 *   - `application/csp-report`  — legacy report-uri payload `{"csp-report": {...}}`
 *   - `application/reports+json` — Reporting API list `[{"type":"csp-violation","body":{...}}]`
 *
 * No auth: reports arrive from any user's browser, often mid-violation
 * where session cookies may not be attached to the beacon.
 */
final class CspReportController extends Controller
{
    public function store(Request $request): Response
    {
        $payload = json_decode((string) $request->getContent(), true);
        if (! is_array($payload)) {
            return response('', 204);
        }

        $reports = [];
        if (isset($payload['csp-report']) && is_array($payload['csp-report'])) {
            $reports[] = $payload['csp-report'];
        } else {
            foreach ($payload as $entry) {
                if (is_array($entry) && ($entry['type'] ?? '') === 'csp-violation' && is_array($entry['body'] ?? null)) {
                    $reports[] = $entry['body'];
                }
            }
        }

        foreach ($reports as $report) {
            Log::warning('CSP violation', [
                'document-uri' => $report['document-uri'] ?? null,
                'violated-directive' => $report['violated-directive'] ?? $report['effectiveDirective'] ?? null,
                'effective-directive' => $report['effective-directive'] ?? null,
                'blocked-uri' => $report['blocked-uri'] ?? $report['blockedURL'] ?? null,
                'source-file' => $report['source-file'] ?? $report['sourceFile'] ?? null,
                'line-number' => $report['line-number'] ?? $report['lineNumber'] ?? null,
            ]);
        }

        return response('', 204);
    }
}

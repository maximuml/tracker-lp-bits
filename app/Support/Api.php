<?php

namespace App\Support;

use Illuminate\Http\Resources\Json\JsonResource;
use Nexus\Nexus;

/**
 * Legacy API response helpers extracted from `include/globalfunctions.php`.
 *
 * Backs `api()`, `success()` and `fail()`. Builds the standard response
 * envelope (`ret`/`msg`/`data`/`time`/`rid`) used by the legacy AJAX and
 * DataTable / LayuiTable endpoints.
 *
 * The request array (`$request`) is injected from the procedural wrapper
 * layer so this class no longer reads `$_REQUEST` directly.
 */
final class Api
{
    /**
     * Build a standard API response envelope.
     *
     * Mirrors `api($ret, $msg, $data)`:
     *   - `ret` is the numeric result code,
     *   - `msg` is a short human-readable string,
     *   - `data` is the payload.
     *
     * @param  array<string, mixed>  $request
     * @return array<string, mixed>
     */
    public static function call(int $ret, string $msg, mixed $data, array $request = []): array
    {
        Logger::write('api begin', 'info');

        if ($data instanceof JsonResource) {
            $data = $data->response()->getData(true);
        }

        Logger::write('api after prepare data', 'info');

        $nexus = Nexus::instance();
        $time = (float) number_format(microtime(true) - ($nexus ? $nexus->getStartTimestamp() : 0), 3);
        $count = null;
        $resultKey = 'ret';
        $msgKey = 'msg';
        $format = $request['__format'] ?? '';

        if (in_array($format, ['layui-table', 'data-table'], true)) {
            $resultKey = 'code';
            $count = $data['meta']['total'] ?? 0;
            if (isset($data['data'])) {
                $data = $data['data'];
            }
        }

        $results = [
            $resultKey => (int) $ret,
            $msgKey => (string) $msg,
            'data' => $data,
            'time' => $time,
            'rid' => $nexus ? $nexus->getRequestId() : 'NO_REQUEST_ID',
        ];

        if ($format === 'layui-table') {
            $results['count'] = $count;
        }

        if ($format === 'data-table') {
            $results['draw'] = (int) ($request['draw'] ?? 1);
            $results['recordsTotal'] = $count;
            $results['recordsFiltered'] = $count;
        }

        if (! (defined('IN_NEXUS') && IN_NEXUS) && Config::get('app.debug')) {
            $results['queries'] = LegacyDb::lastQuery(true);
        }

        Logger::write('api end', 'info');

        return $results;
    }

    /**
     * Convenience wrapper for a successful response.
     *
     * Mirrors `success($msg = 'OK', $data = [])`.
     *
     * @param  array<string, mixed>  $request
     * @return array<string, mixed>
     */
    public static function success(string $msg = 'OK', mixed $data = [], array $request = []): array
    {
        Logger::write('success before api', 'info');

        return self::call(0, $msg, $data, $request);
    }

    /**
     * Convenience wrapper for a failed response.
     *
     * Mirrors `fail($msg = 'ERROR', $data = [])`.
     *
     * @param  array<string, mixed>  $request
     * @return array<string, mixed>
     */
    public static function fail(string $msg = 'ERROR', mixed $data = [], array $request = []): array
    {
        return self::call(-1, $msg, $data, $request);
    }

    /**
     * Context-aware `success()` that reads the request array and handles the
     * legacy single-argument overload (`success($data)` vs `success($msg, $data)`).
     *
     * Backs the legacy `success()` helper.
     *
     * @return array<string, mixed>
     */
    public static function successWithContext(mixed ...$args): array
    {
        $request = SupportContext::allRequest();

        return match (count($args)) {
            0 => self::success('OK', [], $request),
            1 => self::success('OK', $args[0], $request),
            default => self::success((string) $args[0], $args[1] ?? [], $request),
        };
    }

    /**
     * Context-aware `fail()` that reads the request array and handles the
     * legacy single-argument overload (`fail($data)` vs `fail($msg, $data)`).
     *
     * Backs the legacy `fail()` helper.
     *
     * @return array<string, mixed>
     */
    public static function failWithContext(mixed ...$args): array
    {
        $request = SupportContext::allRequest();

        return match (count($args)) {
            0 => self::fail('ERROR', [], $request),
            1 => self::fail('ERROR', $args[0], $request),
            default => self::fail((string) $args[0], $args[1] ?? [], $request),
        };
    }
}

<?php

namespace App\Support;

use Illuminate\Http\Resources\Json\JsonResource;

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
     * @param  mixed  $data
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

        $nexus = \Nexus\Nexus::instance();
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

        if (!(defined('IN_NEXUS') && IN_NEXUS) && Config::get('app.debug')) {
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
     * @param  mixed  $data
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
     * @param  mixed  $data
     * @param  array<string, mixed>  $request
     * @return array<string, mixed>
     */
    public static function fail(string $msg = 'ERROR', mixed $data = [], array $request = []): array
    {
        return self::call(-1, $msg, $data, $request);
    }
}

<?php

namespace App\Support;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Legacy API response helpers extracted from `include/globalfunctions.php`.
 *
 * Backs `api()`, `success()` and `fail()`. Builds the standard response
 * envelope (`ret`/`msg`/`data`/`time`/`rid`) used by the legacy AJAX and
 * DataTable / LayuiTable endpoints.
 */
final class Api
{
    /**
     * Build a standard API response envelope.
     *
     * Mirrors `api()`:
     *   - < 3 arguments: `ret = -1`, `$args[0]` is the message, `$args[1]` is data.
     *   - >= 3 arguments: `ret = $args[0]`, `msg = $args[1]`, `data = $args[2]`.
     *
     * @param mixed ...$args
     * @return array<string, mixed>
     */
    public static function call(...$args): array
    {
        \do_log('api begin', 'info');

        if (func_num_args() < 3) {
            $ret = -1;
            $msg = $args[0] ?? 'ERROR';
            $data = $args[1] ?? [];
        } else {
            $ret = $args[0];
            $msg = $args[1];
            $data = $args[2];
        }

        if ($data instanceof JsonResource) {
            $data = $data->response()->getData(true);
        }

        \do_log('api after prepare data', 'info');

        $nexus = \Nexus\Nexus::instance();
        $time = (float) number_format(microtime(true) - ($nexus ? $nexus->getStartTimestamp() : 0), 3);
        $count = null;
        $resultKey = 'ret';
        $msgKey = 'msg';
        $format = $_REQUEST['__format'] ?? '';

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
            $results['draw'] = (int) ($_REQUEST['draw'] ?? 1);
            $results['recordsTotal'] = $count;
            $results['recordsFiltered'] = $count;
        }

        if (!(defined('IN_NEXUS') && IN_NEXUS) && Config::get('app.debug')) {
            $results['queries'] = LegacyDb::lastQuery(true);
        }

        \do_log('api end', 'info');

        return $results;
    }

    /**
     * Convenience wrapper for a successful response.
     *
     * Mirrors `success()`:
     *   - 1 argument: data only.
     *   - 2 arguments: message and data.
     *
     * @param mixed ...$args
     * @return array<string, mixed>
     */
    public static function success(...$args): array
    {
        $msg = 'OK';
        $data = [];
        $count = count($args);
        if ($count === 1) {
            $data = $args[0];
        } elseif ($count === 2) {
            $msg = $args[0];
            $data = $args[1];
        }

        \do_log('success before api', 'info');

        return self::call(0, $msg, $data);
    }

    /**
     * Convenience wrapper for a failed response.
     *
     * Mirrors `fail()`:
     *   - 1 argument: data only.
     *   - 2 arguments: message and data.
     *
     * @param mixed ...$args
     * @return array<string, mixed>
     */
    public static function fail(...$args): array
    {
        $msg = 'ERROR';
        $data = [];
        $count = count($args);
        if ($count === 1) {
            $data = $args[0];
        } elseif ($count === 2) {
            $msg = $args[0];
            $data = $args[1];
        }

        return self::call(-1, $msg, $data);
    }
}

<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Setting;
use App\Models\TrackerUrl;

/**
 * Legacy tracker announce helpers extracted from `include/globalfunctions.php`.
 *
 * Backs `get_tracker_schema_and_host()` and `get_hr_ratio()`.
 */
final class Tracker
{
    /**
     * Return the tracker URL parts for an announce response.
     *
     * Mirrors `get_tracker_schema_and_host($trackerUrlId, $combine)`.
     */
    /**
     * @return array<string, string>|string
     */
    public static function schemaAndHost(int $trackerUrlId, bool $combine = false): array|string
    {
        $log = "tracker_url_id: $trackerUrlId, combine: ".($combine ? 'true' : 'false');
        $url = TrackerUrl::getById($trackerUrlId);

        if (empty($url)) {
            $ssl_torrent = Url::isSecure() ? 'https://' : 'http://';
            $base_announce_url = sprintf(
                '%s/%s',
                trim(Setting::getBaseUrl(), '/'),
                trim(DEFAULT_TRACKER_URI, '/'),
            );
            $log .= ', ById no value';
        } else {
            $ssl_torrent = parse_url($url, PHP_URL_SCHEME).'://';
            $base_announce_url = substr($url, strlen($ssl_torrent));
            $log .= ', ById has value';
        }

        Logger::writeWithContext("$log, ssl_torrent: $ssl_torrent, base_announce_url: $base_announce_url");

        if ($combine) {
            return $ssl_torrent.$base_announce_url;
        }

        return compact('ssl_torrent', 'base_announce_url');
    }
}

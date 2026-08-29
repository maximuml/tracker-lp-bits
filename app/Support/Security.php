<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Attachment;

/**
 * Legacy security helpers extracted from `include/globalfunctions.php`.
 *
 * Backs `filter_src()`: a small URL allow-list used when rendering untrusted
 * image/script sources in legacy pages.
 */
final class Security
{
    public static function filterSrc(string $src): string
    {
        $path = parse_url($src, PHP_URL_PATH);
        if (empty($path)) {
            return $src;
        }

        $host = parse_url($src, PHP_URL_HOST);
        $currentHost = parse_url(Url::schemeAndHost(), PHP_URL_HOST);
        if (! empty($host) && $host != $currentHost) {
            return $src;
        }

        $documentRoot = Input::serverValue('DOCUMENT_ROOT');
        if ($documentRoot !== '') {
            $guessScriptFilename = sprintf('%s/%s', $documentRoot, trim($path, '/'));
            if (! file_exists($guessScriptFilename)) {
                return $src;
            }
        }

        $imgExtensions = implode('|', Attachment::IMG_EXTENSIONS);
        $allowSuffixPattern = "/\\.($imgExtensions)/i";
        if (preg_match($allowSuffixPattern, $path)) {
            return $src;
        }

        $allowScriptPattern = '/(forums|details|offers)\.php/i';
        if (preg_match($allowScriptPattern, $path)) {
            return $src;
        }

        $dangerScriptsPattern = '/(logout|login|ajax|announce|scrape|adduser|modtask|docleanup|freeleech|take.*)\.php/i';
        if (preg_match($dangerScriptsPattern, $path)) {
            $msg = sprintf('[DANGER_URL]: %s [%s]', $src, RequestContext::instance()->getRequestId());
            Logger::writeWithContext($msg, 'alert');
            Log::writeWithContext($msg, 'mod');
        }

        Logger::writeWithContext("[NOT_ALLOW_SRC]: $src with path: $path");

        return '';
    }
}

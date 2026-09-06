<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\TorrentPromotion;
use App\Exceptions\NexusException;
use App\Support\Config\SiteConfig;
use App\Support\Locale;
use App\Support\Logger;
use App\Support\Path;
use App\Support\Validators;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;

class UploadFileService
{
    public function getTorrentFile(Request $request): UploadedFile
    {
        $file = $request->file('file');
        if (empty($file)) {
            throw new NexusException(Locale::trans('upload.missing_torrent_file', [], null));
        }
        if (! $file->isValid()) {
            Logger::writeWithContext((string) ('torrent file is invalid: '.$file->getClientOriginalName().' (error: '.$file->getError().')'), (string) 'error', (bool) false);
            throw new NexusException('upload torrent file error');
        }
        $size = $file->getSize();
        $maxAllowSize = SiteConfig::current()->main->maxTorrentSize();
        if ($size > $maxAllowSize) {
            $msg = sprintf('%s%s%s',
                Locale::trans('upload.torrent_file_too_big', [], null),
                number_format($maxAllowSize),
                Locale::trans('upload.remake_torrent_note', [], null)
            );
            throw new NexusException($msg);
        }
        if ($size == 0) {
            throw new NexusException('upload.empty_file');
        }
        $filename = $file->getClientOriginalName();
        if (! Validators::isUploadFilename($filename)) {
            throw new NexusException('upload.invalid_filename');
        }
        if (! preg_match('/^(.+)\.torrent$/si', $filename, $matches)) {
            throw new NexusException('upload.filename_not_torrent');
        }

        $mime = $file->getMimeType();
        $allowedMimes = ['application/x-bittorrent', 'application/octet-stream', 'application/x-torrent'];
        if (! in_array($mime, $allowedMimes, true)) {
            throw new NexusException('upload.not_bencoded_file');
        }

        return $file;
    }

    public function getNfoContent(Request $request): string
    {
        $enableNfo = SiteConfig::current()->main->enableNfo();
        if (! $enableNfo) {
            return '';
        }
        $file = $request->file('nfo');
        if (empty($file)) {
            return '';
        }
        if (! $file->isValid()) {
            throw new NexusException(Locale::trans('upload.nfo_upload_failed', [], null));
        }
        $size = $file->getSize();
        if ($size == 0) {
            throw new NexusException(Locale::trans('upload.zero_byte_nfo', [], null));
        }
        if ($size > 65535) {
            throw new NexusException(Locale::trans('upload.nfo_too_big', [], null));
        }

        return str_replace("\x0d\x0d\x0a", "\x0d\x0a", $file->getContent());
    }

    /**
     * @param  mixed  $dict
     * @param  mixed  $key
     * @param  mixed  $type
     * @return mixed
     */
    public function checkTorrentDict($dict, $key, $type = null)
    {
        if (! is_array($dict)) {
            throw new NexusException(Locale::trans('upload.not_a_dictionary', [], null));
        }
        if (! isset($dict[$key])) {
            throw new NexusException(Locale::trans('upload.dictionary_is_missing_key', [], null));
        }
        $value = $dict[$key];
        if ($type !== null) {
            $isFunction = 'is_'.$type;
            if (function_exists($isFunction) && ! $isFunction($value)) {
                throw new NexusException(Locale::trans('upload.invalid_entry_in_dictionary', [], null));
            }
        }

        return $value;
    }

    /**
     * @param  array<int|string, mixed>  $info
     * @return array<int|string, mixed>
     *
     * @throws NexusException
     */
    public function getFileListInfo(array $info, string $dname): array
    {
        $filelist = [];
        $totallen = 0;
        if (isset($info['length'])) {
            $totallen = $info['length'];
            $filelist[] = [$dname, $totallen];
            $type = 'single';
        } else {
            $flist = $this->checkTorrentDict($info, 'files', 'array');

            if (! count($flist)) {
                throw new NexusException(Locale::trans('upload.empty_file', [], null));
            }
            foreach ($flist as $fn) {
                $ll = $this->checkTorrentDict($fn, 'length', 'integer');
                $path_key = isset($fn['path.utf-8']) ? 'path.utf-8' : 'path';
                $ff = $this->checkTorrentDict($fn, $path_key, 'list');

                $totallen += $ll;
                $ffa = [];
                foreach ($ff as $ffe) {
                    if (! is_string($ffe)) {
                        throw new NexusException(Locale::trans('upload.filename_errors', [], null));
                    }
                    $ffa[] = $ffe;
                }

                if (! count($ffa)) {
                    throw new NexusException(Locale::trans('upload.filename_errors', [], null));
                }
                $ffe = implode('/', $ffa);
                $filelist[] = [$ffe, $ll];
            }
            $type = 'multi';
        }

        return [
            'type' => $type,
            'totalLength' => $totallen,
            'fileList' => $filelist,
        ];
    }

    public function getTorrentSavePath(): string
    {
        $torrentSavePath = Path::resolve(SiteConfig::current()->main->torrentDir(), \ROOT_PATH);
        if (! is_dir($torrentSavePath)) {
            Logger::writeWithContext((string) sprintf('torrentSavePath: %s not exists', $torrentSavePath), (string) 'error', (bool) false);
            throw new NexusException(Locale::trans('upload.torrent_save_dir_not_exists', [], null));
        }
        if (! is_writable($torrentSavePath)) {
            Logger::writeWithContext((string) sprintf('torrentSavePath: %s not writable', $torrentSavePath), (string) 'error', (bool) false);
            throw new NexusException(Locale::trans('upload.torrent_save_dir_not_writable', [], null));
        }

        return $torrentSavePath;
    }

    /** @param  mixed  $torrentSize */
    public function getSpState($torrentSize): int
    {
        $siteConfig = SiteConfig::current();
        $largeTorrentSize = $siteConfig->torrent->largeSize();
        if ($largeTorrentSize > 0 && $torrentSize > $largeTorrentSize * 1073741824) {
            $largeTorrentSpState = $siteConfig->torrent->largeSpState();
            if (TorrentPromotion::tryFrom((int) $largeTorrentSpState) !== null) {
                Logger::writeWithContext((string) "large torrent, sp state from config: {$largeTorrentSpState}", (string) 'info', (bool) false);

                return $largeTorrentSpState;
            }
            Logger::writeWithContext((string) "invalid large torrent sp state: {$largeTorrentSpState}", (string) 'error', (bool) false);

            return TorrentPromotion::NORMAL->value;
        } else {
            $torrentConfig = SiteConfig::current()->torrent;
            $probabilities = [
                TorrentPromotion::FREE->value => $torrentConfig->randomFreeProbability(),
                TorrentPromotion::TWO_TIMES_UP->value => $torrentConfig->randomTwoTimesUpProbability(),
                TorrentPromotion::FREE_TWO_TIMES_UP->value => $torrentConfig->randomFreeTwoTimesUpProbability(),
                TorrentPromotion::HALF_DOWN->value => $torrentConfig->randomHalfDownProbability(),
                TorrentPromotion::HALF_DOWN_TWO_TIMES_UP->value => $torrentConfig->randomHalfDownTwoTimesUpProbability(),
                TorrentPromotion::ONE_THIRD_DOWN->value => $torrentConfig->randomOneThirdDownProbability(),
            ];
            $sum = array_sum($probabilities);
            if ($sum == 0) {
                Logger::writeWithContext((string) 'no random sp state', (string) 'warning', (bool) false);

                return TorrentPromotion::NORMAL->value;
            }
            $random = mt_rand(1, $sum);
            $currentProbability = 0;
            foreach ($probabilities as $k => $v) {
                $currentProbability += $v;
                if ($random <= $currentProbability) {
                    Logger::writeWithContext((string) sprintf('random sp state, probabilities: %s, get result: %s by probability: %s', json_encode($probabilities), $k, $v), (string) 'info', (bool) false);

                    return $k;
                }
            }
            throw new \RuntimeException;
        }
    }
}

<?php

declare(strict_types=1);

namespace App\Support\Torrent;

use App\Support\Format;
use App\Support\Html\SafeHtml;
use App\Support\Locale;
use App\ViewModels\Torrent\AudioTrackViewModel;
use App\ViewModels\Torrent\TechnicalInfoViewModel;

class TechnicalInformation
{
    private string $mediaInfo;

    /** @var array<string, array<string, string>> */
    private array $mediaInfoArr;

    public function __construct(string $mediaInfo)
    {
        $this->mediaInfo = $mediaInfo;
        $this->mediaInfoArr = $this->getMediaInfoArr($mediaInfo);
    }

    /**
     * @return array<string, array<string, string>>
     */
    public function getMediaInfoArr(string $mediaInfo): array
    {
        $arr = preg_split('/[\r\n]+/', $mediaInfo) ?: [];
        $result = [];
        $parentKey = '';
        foreach ($arr as $key => $value) {
            $value = $this->trim($value);
            if (empty($value)) {
                continue;
            }
            $rowKeyValue = explode(':', $value);
            $rowKeyValue = array_filter(array_map([$this, 'trim'], $rowKeyValue));
            if (count($rowKeyValue) == 1) {
                $parentKey = $rowKeyValue[0];
            } elseif (count($rowKeyValue) == 2) {
                if (empty($parentKey)) {
                    continue;
                }
                $result[$parentKey][$rowKeyValue[0]] = $rowKeyValue[1];
            }
        }

        return $result;

    }

    private function trim(string $value): string
    {
        return trim($value, " \n\r\t\v\0\u{A0}");
    }

    public function getRuntime(): string
    {
        return $this->mediaInfoArr['General']['Duration'] ?? '';
    }

    public function getResolution(): string
    {
        $width = $this->mediaInfoArr['Video']['Width'] ?? '';
        $height = $this->mediaInfoArr['Video']['Height'] ?? '';
        $ratio = $this->mediaInfoArr['Video']['Display aspect ratio'] ?? '';
        $result = '';
        if ($width && $height) {
            $result .= $width.' x '.$height;
        }
        if ($ratio) {
            $result .= "($ratio)";
        }

        return $result;
    }

    public function getBitrate(): string
    {
        $result = $this->mediaInfoArr['Video']['Bit rate'] ?? '';

        return $result;
    }

    public function getFramerate(): string
    {
        $result = $this->mediaInfoArr['Video']['Frame rate'] ?? '';

        return $result;
    }

    public function getProfile(): string
    {
        $result = $this->mediaInfoArr['Video']['Format profile'] ?? '';

        return $result;
    }

    public function getRefFrame(): string
    {
        foreach ($this->mediaInfoArr['Video'] ?? [] as $key => $value) {
            if (str_contains($key, 'Reference frames')) {
                return $value;
            }
        }

        return '';
    }

    /**
     * @return array<string, string>
     */
    public function getAudios(): array
    {
        $result = [];
        $audioIndex = 1;
        foreach ($this->mediaInfoArr as $parentKey => $values) {
            if (strpos($parentKey, 'Audio') === false) {
                continue;
            }
            $audioInfoArr = [];
            if (! empty($values['Language'])) {
                $audioInfoArr[] = $values['Language'];
            }
            if (! empty($values['Title'])) {
                $audioInfoArr[] = $values['Title'];
            }
            if (! empty($values['Format'])) {
                $audioInfoArr[] = $values['Format'];
            }
            if (! empty($values['Channel(s)'])) {
                $audioInfoArr[] = $values['Channel(s)'];
            }
            if (! empty($values['Bit rate'])) {
                $audioInfoArr[] = '@'.$values['Bit rate'];
            }
            if (! empty($audioInfoArr)) {
                // 使用多语言支持的键名
                $result[Locale::trans('torrent.technicalinfo_audio', [], null).$audioIndex] = implode(' ', $audioInfoArr);
                $audioIndex++;
            }
        }

        return $result;
    }

    /**
     * @return array<string, string>
     */
    public function getSubtitles(): array
    {
        $result = [];
        $subtitleIndex = 1;
        foreach ($this->mediaInfoArr as $parentKey => $values) {
            if (strpos($parentKey, 'Text') === false) {
                continue;
            }
            $subtitlesInfoArr = [];
            if (! empty($values['Language'])) {
                $subtitlesInfoArr[] = $values['Language'];
            }
            if (! empty($values['Title'])) {
                $subtitlesInfoArr[] = $values['Title'];
            }
            if (! empty($values['Format'])) {
                $subtitlesInfoArr[] = $values['Format'];
            }
            if (! empty($subtitlesInfoArr)) {
                // 使用多语言支持的键名
                $result[Locale::trans('torrent.technicalinfo_subtitles', [], null).$subtitleIndex] = implode(' ', $subtitlesInfoArr);
                $subtitleIndex++;
            }
        }

        return $result;
    }

    public function getHDRFormat(): string
    {
        return $this->mediaInfoArr['Video']['HDR format'] ?? '';
    }

    public function getVideoFormat(): string
    {
        return $this->mediaInfoArr['Video']['Format'] ?? '';
    }

    public function getBitDepth(): string
    {
        return $this->mediaInfoArr['Video']['Bit depth'] ?? '';
    }

    public function renderOnDetailsPage(): string
    {
        if (empty($this->mediaInfo)) {
            return '';
        }

        // .nti-* styles live in public/styles/nexus.css — an inline <style>
        // block here is blocked by CSP style-src (no nonce available).
        return view('torrent._technical_info', ['vm' => $this->detailsViewModel()])->render();
    }

    private function detailsViewModel(): TechnicalInfoViewModel
    {
        $rawMediaInfo = sprintf('[spoiler=%s]<pre>%s</pre>[/spoiler]', Locale::trans('torrent.show_hide_media_info', [], null), $this->mediaInfo);
        $rawSpoiler = SafeHtml::fromTrustedHtml(Format::formatComment($rawMediaInfo, false));

        $general = $this->getGeneralInfo();
        $videos = $this->getVideoInfoDetailed();
        $audios = $this->getAudioTracks();
        if (empty($general) && empty($videos) && empty($audios)) {
            // Parser couldn't pull anything structured — fall back to raw spoiler only.
            return new TechnicalInfoViewModel(rawOnly: true, rawSpoiler: $rawSpoiler);
        }

        $generalExtraSpoiler = null;
        if (! empty($general) && ! empty($general['extra'])) {
            $generalExtraSpoiler = $this->columnSpoiler(Locale::trans('torrent.technicalinfo_more_general', [], null), $general['extra']);
        }

        $encodingSpoiler = null;
        if (! empty($videos) && ! empty($videos['encoding_settings']) && is_string($videos['encoding_settings'])) {
            $label = Locale::trans('torrent.technicalinfo_encoding_settings', [], null);
            $encodingSpoiler = $this->columnSpoiler($label, [$label => $videos['encoding_settings']]);
        }

        $visibleTracks = [];
        $hiddenTracksHtml = '';
        foreach (array_slice($audios, 0, 3) as $track) {
            $visibleTracks[] = $this->audioTrackVm($track);
        }
        foreach (array_slice($audios, 3) as $track) {
            $hiddenTracksHtml .= view('torrent._nti_track', ['track' => $this->audioTrackVm($track)])->render();
        }
        $hiddenAudioSpoiler = $hiddenTracksHtml !== ''
            ? SafeHtml::fromTrustedHtml(sprintf(
                '<div class="nti-more">%s</div>',
                Format::formatComment(sprintf('[spoiler=%s]%s[/spoiler]', Locale::trans('torrent.collapse_show_more_audio', [], null), $hiddenTracksHtml), false)
            ))
            : null;

        return new TechnicalInfoViewModel(
            rawOnly: false,
            rawSpoiler: $rawSpoiler,
            generalTitle: Locale::trans('torrent.technicalinfo_section_general', [], null),
            hasGeneral: ! empty($general),
            generalMain: $general['main'] ?? [],
            generalExtraSpoiler: $generalExtraSpoiler,
            videoTitle: Locale::trans('torrent.technicalinfo_section_video', [], null),
            hasVideo: ! empty($videos),
            videosMain: is_array($videos['main'] ?? null) ? $videos['main'] : [],
            encodingSpoiler: $encodingSpoiler,
            audioTitle: Locale::trans('torrent.technicalinfo_section_audio', [], null),
            hasAudio: ! empty($audios),
            audioTracks: $visibleTracks,
            hiddenAudioSpoiler: $hiddenAudioSpoiler,
        );
    }

    /**
     * @param  array<string, string>  $items
     */
    private function columnSpoiler(string $title, array $items): SafeHtml
    {
        $body = view('torrent._nti_spoiler_kv', ['items' => $items])->render();
        $bbcode = sprintf('[spoiler=%s]%s[/spoiler]', $title, $body);

        return SafeHtml::fromTrustedHtml(sprintf('<div class="nti-more">%s</div>', Format::formatComment($bbcode, false)));
    }

    /** @param  array<string, mixed>  $track */
    private function audioTrackVm(array $track): AudioTrackViewModel
    {
        $head = '#'.(int) $track['index'];
        $headParts = [];
        if (! empty($track['language'])) {
            $headParts[] = $track['language'];
        }
        if (! empty($track['title']) && $track['title'] !== ($track['language'] ?? '')) {
            $headParts[] = $track['title'];
        }
        if (! empty($headParts)) {
            $head .= ' · '.implode(' · ', $headParts);
        }
        $badges = [];
        if (! empty($track['badges']['Default'])) {
            $badges[] = Locale::trans('torrent.technicalinfo_default', [], null);
        }
        if (! empty($track['badges']['Forced'])) {
            $badges[] = Locale::trans('torrent.technicalinfo_forced', [], null);
        }

        return new AudioTrackViewModel(head: $head, badges: $badges, rows: $track['rows']);
    }

    /**
     * Returns ['main' => [label => value], 'extra' => [label => value]]
     * The "main" set is the small column display; "extra" goes into a per-column spoiler.
     *
     * @return array<string, array<string, string>>
     */
    public function getGeneralInfo(): array
    {
        $g = $this->mediaInfoArr['General'] ?? [];
        if (empty($g)) {
            return [];
        }
        $main = [
            Locale::trans('torrent.technicalinfo_container', [], null) => $g['Format'] ?? '',
            Locale::trans('torrent.technicalinfo_file_size', [], null) => $g['File size'] ?? '',
            Locale::trans('torrent.technicalinfo_overall_bit_rate', [], null) => $g['Overall bit rate'] ?? ($g['Overall bit rate mode'] ?? ''),
            Locale::trans('torrent.technicalinfo_duration', [], null) => $g['Duration'] ?? '',
            Locale::trans('torrent.technicalinfo_encoded_date', [], null) => $g['Encoded date'] ?? '',
            Locale::trans('torrent.technicalinfo_writing_app', [], null) => $g['Writing application'] ?? ($g['Encoded application'] ?? ''),
            Locale::trans('torrent.technicalinfo_writing_lib', [], null) => $g['Writing library'] ?? '',
        ];
        $main = array_filter($main, fn ($v) => $v !== '');

        // Anything else from [General] goes to the "extra" spoiler so we don't drop info.
        $shownKeys = ['Format', 'File size', 'Overall bit rate', 'Overall bit rate mode',
            'Duration', 'Encoded date', 'Writing application', 'Encoded application',
            'Writing library', 'Complete name', 'Unique ID', 'File name'];
        $extra = [];
        foreach ($g as $k => $v) {
            if (in_array($k, $shownKeys, true)) {
                continue;
            }
            if ($v === '') {
                continue;
            }
            $extra[$k] = $v;
        }

        return ['main' => $main, 'extra' => $extra];
    }

    /**
     * Returns ['main' => [...], 'encoding_settings' => string|null]
     *
     * @return array<string, array<string, string>|string|null>
     */
    public function getVideoInfoDetailed(): array
    {
        $v = $this->mediaInfoArr['Video'] ?? [];
        if (empty($v)) {
            return [];
        }
        $format = $v['Format'] ?? '';
        if (! empty($v['Format profile']) && $format !== '') {
            $format .= ' / '.$v['Format profile'];
        } elseif (empty($format) && ! empty($v['Format profile'])) {
            $format = $v['Format profile'];
        }
        $color = $this->joinNonEmpty([
            $v['Color primaries'] ?? '',
            $v['Transfer characteristics'] ?? '',
        ], ' / ');
        $main = [
            Locale::trans('torrent.technicalinfo_format', [], null) => $format,
            Locale::trans('torrent.technicalinfo_resolution', [], null) => $this->getResolution(),
            Locale::trans('torrent.technicalinfo_bit_rate', [], null) => $v['Bit rate'] ?? ($v['Nominal bit rate'] ?? ''),
            Locale::trans('torrent.technicalinfo_frame_rate', [], null) => $v['Frame rate'] ?? '',
            Locale::trans('torrent.technicalinfo_bit_depth', [], null) => $v['Bit depth'] ?? '',
            Locale::trans('torrent.technicalinfo_color_space', [], null) => $color,
            'HDR' => $v['HDR format'] ?? '',
            Locale::trans('torrent.technicalinfo_scan_type', [], null) => $v['Scan type'] ?? '',
            Locale::trans('torrent.technicalinfo_ref_frames', [], null) => $this->getRefFrame(),
            Locale::trans('torrent.technicalinfo_encoder', [], null) => $v['Encoded library'] ?? ($v['Writing library'] ?? ''),
        ];
        $main = array_filter($main, fn ($v) => $v !== '');

        return [
            'main' => $main,
            'encoding_settings' => isset($v['Encoding settings']) && $v['Encoding settings'] !== ''
                ? $v['Encoding settings']
                : null,
        ];
    }

    /**
     * Returns a list of audio tracks: each item is [
     *   'index' => int,
     *   'language' => string,
     *   'title' => string,
     *   'rows' => [label => value],
     *   'badges' => ['Default' => bool, 'Forced' => bool],
     * ]
     *
     * @return array<int, array<string, mixed>>
     */
    public function getAudioTracks(): array
    {
        $tracks = [];
        $idx = 1;
        foreach ($this->mediaInfoArr as $section => $values) {
            if (strpos($section, 'Audio') === false) {
                continue;
            }
            $format = $values['Format'] ?? '';
            $commercial = $values['Commercial name'] ?? '';
            if ($commercial !== '' && $commercial !== $format) {
                $format = $format !== '' ? $format.' ('.$commercial.')' : $commercial;
            }
            $channels = $values['Channel(s)'] ?? '';
            $layout = $values['Channel layout'] ?? '';
            if ($layout !== '' && $channels !== '') {
                $channels .= ' ('.$layout.')';
            } elseif ($layout !== '' && $channels === '') {
                $channels = $layout;
            }
            $bitrate = $values['Bit rate'] ?? '';
            $bitrateMode = $values['Bit rate mode'] ?? '';
            if ($bitrate !== '' && $bitrateMode !== '') {
                $bitrate .= ' ('.$bitrateMode.')';
            }
            $rows = [
                Locale::trans('torrent.technicalinfo_format', [], null) => $format,
                Locale::trans('torrent.technicalinfo_channels', [], null) => $channels,
                Locale::trans('torrent.technicalinfo_sample_rate', [], null) => $values['Sampling rate'] ?? '',
                Locale::trans('torrent.technicalinfo_bit_rate', [], null) => $bitrate,
                Locale::trans('torrent.technicalinfo_bit_depth', [], null) => $values['Bit depth'] ?? '',
                Locale::trans('torrent.technicalinfo_compression', [], null) => $values['Compression mode'] ?? '',
            ];
            $rows = array_filter($rows, fn ($v) => $v !== '' && $v !== null);
            if (empty($rows)) {
                continue;
            }
            $tracks[] = [
                'index' => $idx,
                'language' => $values['Language'] ?? '',
                'title' => $values['Title'] ?? '',
                'rows' => $rows,
                'badges' => [
                    'Default' => isset($values['Default']) && strcasecmp($values['Default'], 'yes') === 0,
                    'Forced' => isset($values['Forced']) && strcasecmp($values['Forced'], 'yes') === 0,
                ],
            ];
            $idx++;
        }

        return $tracks;
    }

    /** @param  array<int, string>  $parts */
    private function joinNonEmpty(array $parts, string $glue = ' / '): string
    {
        $parts = array_filter(array_map([$this, 'trim'], $parts), fn ($v) => $v !== '');

        return implode($glue, $parts);
    }

    /** @return array<string, array<string, string>|null> */
    public function getSummaryInfo(): array
    {
        $videos = [
            Locale::trans('torrent.technicalinfo_duration', [], null) => $this->getRuntime(),
            Locale::trans('torrent.technicalinfo_resolution', [], null) => $this->getResolution(),
            Locale::trans('torrent.technicalinfo_bit_rate', [], null) => $this->getBitrate(),
            'HDR' => $this->getHDRFormat(),
            Locale::trans('torrent.technicalinfo_bit_depth', [], null) => $this->getBitDepth(),
            Locale::trans('torrent.technicalinfo_frame_rate', [], null) => $this->getFramerate(),
            Locale::trans('torrent.technicalinfo_profile', [], null) => $this->getProfile(),
            Locale::trans('torrent.technicalinfo_format', [], null) => $this->getVideoFormat(),
            Locale::trans('torrent.technicalinfo_ref_frames', [], null) => $this->getRefFrame(),
        ];
        $videos = array_filter($videos) ?: null;
        $audios = $this->getAudios() ?: null;
        $subtitles = $this->getSubtitles() ?: null;

        return compact('videos', 'audios', 'subtitles');
    }
}

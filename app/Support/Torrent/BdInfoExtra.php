<?php

declare(strict_types=1);

namespace App\Support\Torrent;

use App\Support\Format;
use App\Support\Html\SafeHtml;
use App\Support\Locale;
use App\ViewModels\Torrent\BdInfoColumnViewModel;
use App\ViewModels\Torrent\BdInfoDiscViewModel;
use App\ViewModels\Torrent\BdInfoViewModel;

class BdInfoExtra
{
    private string $bdInfo;

    /** @var array<string, mixed> */
    private array $bdInfoArr;

    public function __construct(string $bdInfo)
    {
        $this->bdInfo = $bdInfo;
        $this->bdInfoArr = $this->parseBdInfo($bdInfo);
    }

    /**
     * Parse BDINFO text into a structured array
     *
     * @return array<string, mixed>
     */
    private function parseBdInfo(string $bdInfo): array
    {
        $lines = preg_split('/[\r\n]+/', $bdInfo) ?: [];

        // Detect the Summary format (no section headers)
        $isSummaryFormat = $this->isSummaryFormat($lines);

        if ($isSummaryFormat) {
            $result = [
                'disc_info' => [],
                'playlist_report' => [],
                'video' => [],
                'audio' => [],
                'subtitles' => [],
            ];

            return $this->summaryFormat($lines, $result);
        } else {
            return $this->normalFormat($lines);
        }
    }

    /**
     * Detect the Summary format
     *
     * @param  array<int, string>  $lines
     */
    private function isSummaryFormat(array $lines): bool
    {
        foreach ($lines as $line) {
            $line = $this->trim($line);
            if (str_contains($line, 'DISC INFO') ||
                str_contains($line, 'PLAYLIST REPORT') ||
                str_contains($line, 'VIDEO') ||
                str_contains($line, 'AUDIO') ||
                str_contains($line, 'SUBTITLES')) {
                return false;
            }
        }

        return true;
    }

    /**
     * Parse the sectioned format (lines grouped under headers)
     *
     * @param  array<int, string>  $lines
     * @return array<string, mixed>
     */
    private function normalFormat(array $lines): array
    {
        $discs = [];
        $currentDisc = null;
        $currentSection = '';
        $audioIndex = 0;
        $subtitleIndex = 0;

        foreach ($lines as $line) {
            $line = $this->trim($line);
            if (empty($line)) {
                continue;
            }

            // Detect a new DISC
            if (str_contains($line, 'DISC INFO')) {
                // Save the previous DISC (if any)
                if ($currentDisc !== null) {
                    $discs[] = $currentDisc;
                }

                // Create a new DISC
                $currentDisc = [
                    'disc_info' => [],
                    'playlist_report' => [],
                    'video' => [],
                    'audio' => [],
                    'subtitles' => [],
                ];
                $currentSection = 'disc_info';
                $audioIndex = 0;
                $subtitleIndex = 0;

                continue;
            } elseif (str_contains($line, 'PLAYLIST REPORT')) {
                $currentSection = 'playlist_report';

                continue;
            } elseif (str_contains($line, 'VIDEO')) {
                $currentSection = 'video';

                continue;
            } elseif (str_contains($line, 'AUDIO')) {
                $currentSection = 'audio';

                continue;
            } elseif (str_contains($line, 'SUBTITLES')) {
                $currentSection = 'subtitles';

                continue;
            } elseif (str_contains($line, 'CHAPTERS') || str_contains($line, 'STREAM DIAGNOSTICS')) {
                $currentSection = '';

                continue;
            }

            // Parse each section's lines
            if ($currentDisc !== null && ! empty($currentSection)) {
                switch ($currentSection) {
                    case 'disc_info':
                        $this->parseDiscInfo($line, $currentDisc['disc_info']);
                        break;
                    case 'playlist_report':
                        $this->parsePlaylistReport($line, $currentDisc['playlist_report']);
                        break;
                    case 'video':
                        $this->parseVideo($line, $currentDisc['video']);
                        break;
                    case 'audio':
                        $this->parseAudio($line, $currentDisc['audio'], $audioIndex);
                        break;
                    case 'subtitles':
                        $this->parseSubtitles($line, $currentDisc['subtitles'], $subtitleIndex);
                        break;
                }
            }
        }

        // Save the last DISC
        if ($currentDisc !== null) {
            $discs[] = $currentDisc;
        }

        // No DISC found — return the empty structure
        if (empty($discs)) {
            return [
                'disc_info' => [],
                'playlist_report' => [],
                'video' => [],
                'audio' => [],
                'subtitles' => [],
            ];
        }

        // Return the first DISC's data (backward compatibility)
        return $discs[0];
    }

    /**
     * Parse the Summary format (no section headers)
     *
     * @param  array<int, string>  $lines
     * @param  array<string, mixed>  $result
     * @return array<string, mixed>
     */
    private function summaryFormat(array $lines, array $result): array
    {
        $audioIndex = 0;
        $subtitleIndex = 0;

        foreach ($lines as $line) {
            $line = $this->trim($line);
            if (empty($line)) {
                continue;
            }

            // Parse disc info
            if (str_contains($line, 'Disc Label:')) {
                $result['disc_info']['label'] = trim(substr($line, 11));
            } elseif (str_contains($line, 'Disc Size:')) {
                $result['disc_info']['size'] = trim(substr($line, 10));
            } elseif (str_contains($line, 'Protection:')) {
                $result['disc_info']['protection'] = trim(substr($line, 11));
            } elseif (str_contains($line, 'Playlist:')) {
                $result['playlist_report']['name'] = trim(substr($line, 9));
            } elseif (str_contains($line, 'Size:')) {
                $result['playlist_report']['size'] = trim(substr($line, 5));
            } elseif (str_contains($line, 'Length:')) {
                $result['playlist_report']['length'] = trim(substr($line, 7));
            } elseif (str_contains($line, 'Total Bitrate:')) {
                $result['playlist_report']['total_bitrate'] = trim(substr($line, 14));
            } elseif (str_contains($line, 'Video:')) {
                $this->summaryFormatVideo($line, $result['video']);
            } elseif (str_contains($line, 'Audio:')) {
                $this->summaryFormatAudio($line, $result['audio'], $audioIndex);
            } elseif (str_contains($line, 'Subtitle:')) {
                $this->summaryFormatSubtitle($line, $result['subtitles'], $subtitleIndex);
            }
        }

        return $result;
    }

    /**
     * Parse a Summary-format video line
     *
     * @param  array<string, mixed>  $video
     */
    private function summaryFormatVideo(string $line, array &$video): void
    {
        // Format: Video: MPEG-4 AVC Video / 31943 kbps / 1080p / 23.976 fps / 16:9 / High Profile 4.1
        if (preg_match('/Video:\s*(.+?)\s*\/\s*(\d+)\s*kbps\s*\/(.+)/', $line, $matches)) {
            $video['codec'] = trim($matches[1]);
            $video['bitrate'] = trim($matches[2]).' kbps';
            $video['description'] = trim($matches[3]);
        }
    }

    /**
     * Parse a Summary-format audio line
     *
     * @param  array<int, array<string, mixed>>  $audio
     */
    private function summaryFormatAudio(string $line, array &$audio, int &$audioIndex): void
    {
        // Format: Audio: Chinese / DTS-HD Master Audio / 2.0 / 48 kHz /   914 kbps / 16-bit (DTS Core: 2.0 / 48 kHz /   768 kbps / 16-bit)
        if (preg_match('/Audio:\s*([^*]+?)\s*\/\s*([^*]+?)\s*\/\s*([^*]+?)\s*\/\s*([^*]+?)\s*\/\s*([^*]+?)\s*\/\s*([^*]+?)(?:\s*\((.+)\))?/', $line, $matches)) {
            $bitrate = trim($matches[5]);
            // Don't append kbps twice when the bitrate already has it
            if (strpos($bitrate, 'kbps') === false) {
                $bitrate .= ' kbps';
            }

            $audio[$audioIndex] = [
                'language' => trim($matches[1]),
                'codec' => trim($matches[2]),
                'channels' => trim($matches[3]),
                'sample_rate' => trim($matches[4]),
                'bitrate' => $bitrate,
                'bit_depth' => trim($matches[6]),
                'description' => isset($matches[7]) ? trim($matches[7]) : '',
            ];
            $audioIndex++;
        }
    }

    /**
     * Parse a Summary-format subtitle line
     *
     * @param  array<int, array<string, mixed>>  $subtitles
     */
    private function summaryFormatSubtitle(string $line, array &$subtitles, int &$subtitleIndex): void
    {
        // Format: Subtitle: English / 38.300 kbps
        if (preg_match('/Subtitle:\s*([^*]+?)\s*\/\s*([^*]+?)\s*kbps/', $line, $matches)) {
            $subtitles[$subtitleIndex] = [
                'language' => trim($matches[1]),
                'bitrate' => trim($matches[2]).' kbps',
                'codec' => 'Presentation Graphics',
                'description' => '',
            ];
            $subtitleIndex++;
        }
    }

    /**
     * Parse disc info
     *
     * @param  array<string, mixed>  $discInfo
     */
    private function parseDiscInfo(string $line, array &$discInfo): void
    {
        if (str_contains($line, 'Disc Title:')) {
            $discInfo['title'] = trim(substr($line, 11));
        } elseif (str_contains($line, 'Disc Label:')) {
            $discInfo['label'] = trim(substr($line, 11));
        } elseif (str_contains($line, 'Disc Size:')) {
            $discInfo['size'] = trim(substr($line, 10));
        } elseif (str_contains($line, 'Protection:')) {
            $discInfo['protection'] = trim(substr($line, 11));
        } elseif (str_contains($line, 'Extras:')) {
            $discInfo['extras'] = trim(substr($line, 7));
        }
    }

    /**
     * Parse playlist report
     *
     * @param  array<string, mixed>  $playlistReport
     */
    private function parsePlaylistReport(string $line, array &$playlistReport): void
    {
        if (str_contains($line, 'Name:')) {
            $playlistReport['name'] = trim(substr($line, 5));
        } elseif (str_contains($line, 'Length:')) {
            $playlistReport['length'] = trim(substr($line, 7));
        } elseif (str_contains($line, 'Size:')) {
            $playlistReport['size'] = trim(substr($line, 5));
        } elseif (str_contains($line, 'Total Bitrate:')) {
            $playlistReport['total_bitrate'] = trim(substr($line, 14));
        }
    }

    /**
     * Parse a video line
     *
     * @param  array<int|string, mixed>  $video
     */
    private function parseVideo(string $line, array &$video): void
    {
        // Skip header and separator lines
        if (str_contains($line, 'Codec') || str_contains($line, '-----') || str_contains($line, 'Description')) {
            return;
        }

        // Parse video lines, including hidden streams (marked with *)
        if (preg_match('/^(\*?\s*)(.+?)\s+([\d,]+)\s+kbps\s+(.+)$/', $line, $matches)) {
            $isHidden = str_contains($matches[1], '*');

            if (! $isHidden) {
                // Primary video stream — multiple streams supported
                // Append to the array
                $video[] = [
                    'codec' => trim($matches[2]),
                    'bitrate' => trim($matches[3]).' kbps',
                    'description' => trim($matches[4]),
                ];
                $videoIndex = count($video) - 1;

                // Extract the resolution from every stream
                $description = trim($matches[4]);
                if (preg_match('/(\d+)p/', $description, $resMatches)) {
                    $video['height'] = $resMatches[1];
                }
                // Extract the aspect ratio only when present in the description
                if (preg_match('/(\d+:\d+)/', $description, $ratioMatches)) {
                    $video['aspect_ratio'] = $ratioMatches[1];
                }
            } else {
                // Hidden video stream — treated as its own stream but flagged hidden
                $video[] = [
                    'codec' => trim($matches[2]),
                    'bitrate' => trim($matches[3]).' kbps',
                    'description' => trim($matches[4]),
                    'hidden' => true,
                ];
            }
        }
    }

    /**
     * Extract non-English content from subtitle/audio descriptions
     *
     * @return array<string, mixed>
     */
    private function extractNonEnglishContent(string $text): array
    {
        $result = ['text' => $text, 'non_english_content' => []];

        // Collect every non-ASCII run
        if (preg_match_all('/[^\x{0000}-\x{007F}]+/u', $text, $matches)) {
            foreach ($matches[0] as $match) {
                // Strip whitespace and brackets
                $match = preg_replace('/[\s\t\n\r（）()【】\[\]]+/u', '', $match) ?? '';
                $match = trim($match);
                if (! empty($match)) {
                    $result['non_english_content'][] = $match;
                }
            }

            // Remove the non-English parts from the original text
            $result['text'] = preg_replace('/[^\x{0000}-\x{007F}]+/u', '', $text) ?? '';
        }

        $result['text'] = trim($result['text']);

        return $result;
    }

    /**
     * Parse an audio line
     *
     * @param  array<int, array<string, mixed>>  $audio
     */
    private function parseAudio(string $line, array &$audio, int &$audioIndex): void
    {
        // Skip header and separator lines
        if (str_contains($line, 'Codec') || str_contains($line, '-----') || str_contains($line, 'Language')) {
            return;
        }

        // Audio line format: DTS-HD Master Audio             English         1564 kbps       2.0 / 48 kHz / 1564 kbps / 24-bit
        // Hidden audio streams (marked with *) are included too
        if (preg_match('/^(\*?\s*)(.+?)\s+([A-Za-z]+)\s+([\d,]+)\s+kbps\s+(.+)$/', $line, $matches)) {
            $description = trim($matches[5]);

            // Extract bracketed content
            $extracted = $this->extractNonEnglishContent($description);
            $nonEnglishContent = $extracted['non_english_content'];
            $cleanDescription = $extracted['text'];

            $audio[$audioIndex] = [
                'codec' => trim($matches[2]),
                'language' => trim($matches[3]),
                'bitrate' => trim($matches[4]).' kbps',
                'description' => $cleanDescription,
                'non_english_content' => $nonEnglishContent,
            ];
            $audioIndex++;
        }
    }

    /**
     * Parse a subtitle line
     *
     * @param  array<int, array<string, mixed>>  $subtitles
     */
    private function parseSubtitles(string $line, array &$subtitles, int &$subtitleIndex): void
    {
        // Skip header and separator lines
        if (str_contains($line, 'Codec') || str_contains($line, '-----') || str_contains($line, 'Language')) {
            return;
        }

        // Skip the FILES section rows
        if (str_contains($line, 'Name') || str_contains($line, 'Time In') || str_contains($line, 'Length') || str_contains($line, 'Size') || str_contains($line, 'Total Bitrate')) {
            return;
        }

        // Skip file rows, e.g. 00003.M2TS      0:00:00.000     2:00:29.416
        if (preg_match('/^\w+\.M2TS\s+/', $line)) {
            return;
        }

        // Subtitle line format: Presentation Graphics           English         21.061 kbps
        // Prefer lines starting with "Presentation Graphics"
        if (preg_match('/^(Presentation Graphics)\s+([^*]+?)\s+([^*]+?)\s+kbps\s*(.*)$/', $line, $matches)) {
            $codec = trim($matches[1]);
            $language = trim($matches[2]);
            $bitrate = trim($matches[3]).' kbps';
            $description = trim($matches[4]);

            // Only add when a language is present
            if (! empty($language)) {
                // Extract bracketed content
                $extracted = $this->extractNonEnglishContent($description);
                $nonEnglishContent = $extracted['non_english_content'];
                $cleanDescription = $extracted['text'];

                $subtitles[$subtitleIndex] = [
                    'codec' => $codec,
                    'language' => $language,
                    'bitrate' => $bitrate,
                    'description' => $cleanDescription,
                    'non_english_content' => $nonEnglishContent,
                ];
                $subtitleIndex++;
            }
        }
    }

    /**
     * Get the duration
     */
    public function getDuration(): string
    {
        $length = $this->bdInfoArr['playlist_report']['length'] ?? '';
        if (empty($length)) {
            return '';
        }

        // Convert 1:55:22.123 -> 1h 55m 22s 123ms
        if (preg_match('/(\d+):(\d+):(\d+)\.(\d+)/', $length, $matches)) {
            $hours = intval($matches[1]);
            $minutes = intval($matches[2]);
            $seconds = intval($matches[3]);
            $milliseconds = intval($matches[4]);

            return sprintf('%dh %02dm %02ds %03dms', $hours, $minutes, $seconds, $milliseconds);
        }

        return $length;
    }

    /**
     * Get the total bitrate
     */
    public function getTotalBitrate(): string
    {
        return $this->bdInfoArr['playlist_report']['total_bitrate'] ?? '';
    }

    /**
     * Get the frame rate
     */
    public function getFrameRate(): string
    {
        $description = $this->bdInfoArr['video']['description'] ?? '';
        if (preg_match('/(\d+\.?\d*)\s+fps/', $description, $matches)) {
            return $matches[1].' fps';
        }

        return '';
    }

    /**
     * Get the video profile
     */
    public function getProfile(): string
    {
        $profiles = [];

        // Check all video streams, skipping hidden ones
        foreach ($this->bdInfoArr['video'] as $key => $video) {
            if (is_array($video) && isset($video['description']) && ! isset($video['hidden'])) {
                $description = $video['description'];
                if (preg_match('/([^\/]*?(?:profile|high|level|main)[^\/]*?)(?:\s*\/|$)/i', $description, $matches)) {
                    $profiles[] = trim($matches[1]);
                }
            }
        }

        // No profile found — check the summaryFormat shape (associative array)
        if (empty($profiles) && isset($this->bdInfoArr['video']['description'])) {
            $description = $this->bdInfoArr['video']['description'];
            if (preg_match('/([^\/]*?(?:profile|high|level|main)[^\/]*?)(?:\s*\/|$)/i', $description, $matches)) {
                $profiles[] = trim($matches[1]);
            }
        }

        return implode(' / ', $profiles);
    }

    public function getResolution(): string
    {
        $resolutions = [];

        // Walk every video stream, extracting resolution and aspect ratio
        foreach ($this->bdInfoArr['video'] as $index => $video) {
            // Handle both indexed arrays (multi-stream) and associative (single-stream)
            if (is_array($video) && isset($video['description'])) {
                $description = $video['description'];
                $resolutionItem = '';

                // Extract the "xxxp" resolution
                if (preg_match('/(\d+p)/', $description, $matches)) {
                    $resolutionItem = $matches[1];
                }

                // Extract the aspect ratio
                if (preg_match('/(\d+:\d+)/', $description, $ratioMatches)) {
                    $resolutionItem .= '('.$ratioMatches[1].')';
                }

                if (! empty($resolutionItem)) {
                    $resolutions[] = $resolutionItem;
                }
            }
        }

        // No resolution found — check the summaryFormat shape (associative array)
        if (empty($resolutions) && isset($this->bdInfoArr['video']['description'])) {
            $description = $this->bdInfoArr['video']['description'];
            $resolutionItem = '';

            // Extract the "xxxp" resolution
            if (preg_match('/(\d+p)/', $description, $matches)) {
                $resolutionItem = $matches[1];
            }

            // Extract the aspect ratio
            if (preg_match('/(\d+:\d+)/', $description, $ratioMatches)) {
                $resolutionItem .= '('.$ratioMatches[1].')';
            }

            if (! empty($resolutionItem)) {
                $resolutions[] = $resolutionItem;
            }
        }

        return implode(' / ', $resolutions);
    }

    public function getBitDepth(): string
    {
        // Read the bit depth from the first video stream
        $firstVideo = $this->bdInfoArr['video'][0] ?? null;
        if ($firstVideo && isset($firstVideo['description'])) {
            $description = $firstVideo['description'];
            if (preg_match('/(\d+)\s+bits/', $description, $matches)) {
                return $matches[1].' bits';
            }
        }

        // No bit depth found — check the summaryFormat shape (associative array)
        if (isset($this->bdInfoArr['video']['description'])) {
            $description = $this->bdInfoArr['video']['description'];
            if (preg_match('/(\d+)\s+bits/', $description, $matches)) {
                return $matches[1].' bits';
            }
        }

        return '';
    }

    public function getVideoFormat(): string
    {
        $formats = [];

        // Check all video streams
        foreach ($this->bdInfoArr['video'] as $key => $video) {
            if (is_array($video) && isset($video['codec'])) {
                $formats[] = $video['codec'];
            }
        }

        // No format found — check the summaryFormat shape (associative array)
        if (empty($formats) && isset($this->bdInfoArr['video']['codec'])) {
            $formats[] = $this->bdInfoArr['video']['codec'];
        }

        return implode(' / ', $formats);
    }

    /**
     * Get the aspect ratio
     */
    public function getAspectRatio(): string
    {
        return $this->bdInfoArr['video']['aspect_ratio'] ?? '';
    }

    /**
     * Get the Extras field
     */
    public function getExtras(): string
    {
        return $this->bdInfoArr['disc_info']['extras'] ?? '';
    }

    /**
     * Get the HDR format
     */
    public function getHDRFormat(): string
    {
        // Collect HDR info from all video streams
        $hdrTypes = [];
        $bitDepths = [];
        $nits = [];

        foreach ($this->bdInfoArr['video'] as $video) {
            $description = $video['description'] ?? '';

            // Extract the HDR format from the VIDEO description
            if (preg_match('/\b(HDR10\+|HDR10|HDR|HLG|Dolby Vision)(?:\s|\/|$)/i', $description, $matches)) {
                $hdrTypes[] = $matches[1];
            }

            // Check the bit depth
            if (preg_match('/(\d+)\s+bits/', $description, $matches)) {
                $bitDepths[] = $matches[1].' bits';
            }

            // Check the brightness
            if (preg_match('/(\d+)nits/', $description, $matches)) {
                $nits[] = $matches[1].'nits';
            }
        }

        // Deduplicate and build the result
        $result = [];

        // HDR formats
        $hdrTypes = array_unique($hdrTypes);
        if (! empty($hdrTypes)) {
            $result[] = implode(' / ', $hdrTypes);
        }

        // Bit depths
        $bitDepths = array_unique($bitDepths);
        if (! empty($bitDepths)) {
            $result[] = implode(' / ', $bitDepths);
        }

        // Brightness
        $nits = array_unique($nits);
        if (! empty($nits)) {
            $result[] = implode(' / ', $nits);
        }

        return implode(' / ', $result);
    }

    /**
     * Get the audio info
     *
     * @return array<string, string>
     */
    public function getAudios(): array
    {
        $result = [];
        $audioIndex = 1;
        foreach ($this->bdInfoArr['audio'] as $audio) {
            $audioInfo = [];

            // Language
            if (! empty($audio['language'])) {
                $audioInfo[] = $audio['language'];
            }

            // Codec
            if (! empty($audio['codec'])) {
                $audioInfo[] = $audio['codec'];
            }

            // Channel info
            if (! empty($audio['channels'])) {
                $audioInfo[] = $audio['channels'];
            } elseif (! empty($audio['description'])) {
                // Extract channels from the description
                if (preg_match('/(\d+\.\d+)/', $audio['description'], $matches)) {
                    $audioInfo[] = $matches[1];
                }
            }

            // Bitrate
            if (! empty($audio['bitrate'])) {
                $audioInfo[] = $audio['bitrate'];
            }

            // Bracketed content (appended last)
            if (! empty($audio['non_english_content'])) {
                foreach ($audio['non_english_content'] as $nonEnglishItem) {
                    $audioInfo[] = $nonEnglishItem;
                }
            }

            if (! empty($audioInfo)) {
                $result[Locale::trans('torrent.technicalinfo_audio', [], null).$audioIndex] = implode(' / ', $audioInfo);
                $audioIndex++;
            }
        }

        return $result;
    }

    /**
     * Get the subtitle info
     *
     * @return array<string, string>
     */
    public function getSubtitles(): array
    {
        $result = [];
        $subtitleIndex = 1;
        foreach ($this->bdInfoArr['subtitles'] as $subtitle) {
            if (! empty($subtitle['language'])) {
                $subtitleInfo = [$subtitle['language']];

                // Bracketed content (appended last)
                if (! empty($subtitle['non_english_content'])) {
                    foreach ($subtitle['non_english_content'] as $nonEnglishItem) {
                        $subtitleInfo[] = $nonEnglishItem;
                    }
                }

                $result[Locale::trans('torrent.technicalinfo_subtitles', [], null).$subtitleIndex] = implode(' / ', $subtitleInfo);
                $subtitleIndex++;
            }
        }

        return $result;
    }

    /**
     * Get all DISC data
     *
     * @return array<int, array<string, mixed>>
     */
    private function getAllDiscs(): array
    {
        $lines = preg_split('/[\r\n]+/', $this->bdInfo) ?: [];
        $discs = [];
        $currentDisc = null;
        $currentSection = '';
        $audioIndex = 0;
        $subtitleIndex = 0;

        foreach ($lines as $line) {
            $line = $this->trim($line);
            if (empty($line)) {
                continue;
            }

            // Detect a new DISC
            if (str_contains($line, 'DISC INFO')) {
                // Save the previous DISC (if any)
                if ($currentDisc !== null) {
                    $discs[] = $currentDisc;
                }

                // Create a new DISC
                $currentDisc = [
                    'disc_info' => [],
                    'playlist_report' => [],
                    'video' => [],
                    'audio' => [],
                    'subtitles' => [],
                ];
                $currentSection = 'disc_info';
                $audioIndex = 0;
                $subtitleIndex = 0;

                continue;
            } elseif (str_contains($line, 'PLAYLIST REPORT')) {
                $currentSection = 'playlist_report';

                continue;
            } elseif (str_contains($line, 'VIDEO')) {
                $currentSection = 'video';

                continue;
            } elseif (str_contains($line, 'AUDIO')) {
                $currentSection = 'audio';

                continue;
            } elseif (str_contains($line, 'SUBTITLES')) {
                $currentSection = 'subtitles';

                continue;
            } elseif (str_contains($line, 'CHAPTERS') || str_contains($line, 'STREAM DIAGNOSTICS')) {
                $currentSection = '';

                continue;
            }

            // Parse each section's lines
            if ($currentDisc !== null && ! empty($currentSection)) {
                switch ($currentSection) {
                    case 'disc_info':
                        $this->parseDiscInfo($line, $currentDisc['disc_info']);
                        break;
                    case 'playlist_report':
                        $this->parsePlaylistReport($line, $currentDisc['playlist_report']);
                        break;
                    case 'video':
                        $this->parseVideo($line, $currentDisc['video']);
                        break;
                    case 'audio':
                        $this->parseAudio($line, $currentDisc['audio'], $audioIndex);
                        break;
                    case 'subtitles':
                        $this->parseSubtitles($line, $currentDisc['subtitles'], $subtitleIndex);
                        break;
                }
            }
        }

        // Save the last DISC
        if ($currentDisc !== null) {
            $discs[] = $currentDisc;
        }

        // No DISC found (normalFormat) — try the summaryFormat shape
        if (empty($discs)) {
            // Check whether bdInfoArr carries usable media data
            if ((isset($this->bdInfoArr['video']) && ! empty($this->bdInfoArr['video'])) ||
                (isset($this->bdInfoArr['audio']) && ! empty($this->bdInfoArr['audio']))) {
                // Return bdInfoArr as a single DISC
                $discs[] = $this->bdInfoArr;
            }
        }

        return $discs;
    }

    /**
     * Get the summary info
     *
     * @return array<string, mixed>
     */
    public function getSummaryInfo(): array
    {
        $videos = [
            Locale::trans('torrent.technicalinfo_duration', [], null) => $this->getDuration(),
            Locale::trans('torrent.technicalinfo_resolution', [], null) => $this->getResolution(),
            Locale::trans('torrent.technicalinfo_bit_rate', [], null) => $this->getTotalBitrate(),
            'HDR' => $this->getHDRFormat(),
            Locale::trans('torrent.technicalinfo_bit_depth', [], null) => $this->getBitDepth(),
            Locale::trans('torrent.technicalinfo_frame_rate', [], null) => $this->getFrameRate(),
            Locale::trans('torrent.technicalinfo_profile', [], null) => $this->getProfile(),
            Locale::trans('torrent.technicalinfo_format', [], null) => $this->getVideoFormat(),
            Locale::trans('torrent.technicalinfo_extras', [], null) => $this->getExtras(),
        ];
        $videos = array_filter($videos) ?: null;
        $audios = $this->getAudios() ?: null;
        $subtitles = $this->getSubtitles() ?: null;

        return compact('videos', 'audios', 'subtitles');
    }

    /**
     * Render on the details page
     */
    public function renderOnDetailsPage(): string
    {
        return view('torrent._bdinfo', ['vm' => $this->detailsViewModel()])->render();
    }

    private function detailsViewModel(): BdInfoViewModel
    {
        $rawBdInfo = sprintf('[spoiler=%s][raw]<pre>%s</pre>[/raw][/spoiler]', Locale::trans('torrent.show_hide_bd_info', [], null), $this->bdInfo);
        $rawSpoiler = SafeHtml::fromTrustedHtml(Format::formatComment($rawBdInfo, false));

        $allDiscs = $this->getAllDiscs();
        $hasValidData = false;
        foreach ($allDiscs as $disc) {
            if (! empty($disc['video']) || ! empty($disc['audio'])) {
                $hasValidData = true;
                break;
            }
        }

        if (! $hasValidData) {
            return new BdInfoViewModel(rawOnly: true, rawSpoiler: $rawSpoiler);
        }

        $discs = [];
        $multiDisc = count($allDiscs) > 1;
        foreach ($allDiscs as $discIndex => $disc) {
            $originalBdInfoArr = $this->bdInfoArr;
            $this->bdInfoArr = $disc;

            $summaryInfo = $this->getSummaryInfo();
            $this->bdInfoArr = $originalBdInfoArr;

            $videos = $summaryInfo['videos'] ?: [];
            $audios = $summaryInfo['audios'] ?: [];
            $subtitles = $summaryInfo['subtitles'] ?: [];
            if (empty($videos) && empty($audios) && empty($subtitles)) {
                continue;
            }

            $discs[] = new BdInfoDiscViewModel(
                heading: $multiDisc ? 'Disc #'.($discIndex + 1).' : '.($disc['disc_info']['title'] ?? '') : null,
                videos: $videos !== [] ? $this->bdInfoColumn($videos) : null,
                audios: $audios !== [] ? $this->bdInfoColumn($audios) : null,
                subtitles: $subtitles !== [] ? $this->bdInfoColumn($subtitles) : null,
                trailingHr: $discIndex < count($allDiscs) - 1,
            );
        }

        return new BdInfoViewModel(rawOnly: false, rawSpoiler: $rawSpoiler, discs: $discs);
    }

    /**
     * @param  array<string, string>  $parts
     */
    private function bdInfoColumn(array $parts): BdInfoColumnViewModel
    {
        $audioPrefix = Locale::trans('torrent.technicalinfo_audio', [], null);
        $subtitlePrefix = Locale::trans('torrent.technicalinfo_subtitles', [], null);
        $audioOrSubtitleCount = 0;
        foreach ($parts as $key => $value) {
            if (str_starts_with($key, $audioPrefix) || str_starts_with($key, $subtitlePrefix)) {
                $audioOrSubtitleCount++;
            }
        }
        $collapse = $audioOrSubtitleCount > 3;

        $visibleRows = [];
        $hiddenParts = [];
        $displayCount = 0;
        foreach ($parts as $key => $value) {
            $displayCount++;
            if ($collapse && $displayCount > 3) {
                $hiddenParts[$key] = $value;
            } else {
                $visibleRows[$key] = $value;
            }
        }

        $hiddenSpoiler = null;
        if ($hiddenParts !== []) {
            $hiddenContent = '';
            foreach ($hiddenParts as $key => $value) {
                $hiddenContent .= sprintf('<b>%s: </b>%s<br>', $key, $value);
            }
            $hiddenContent = rtrim($hiddenContent, '<br>');

            $spoilerTitle = str_starts_with(array_keys($parts)[0], $audioPrefix)
                ? Locale::trans('torrent.collapse_show_more_audio', [], null)
                : Locale::trans('torrent.collapse_show_more_subtitles', [], null);

            $spoiler = sprintf('[spoiler=%s]%s[/spoiler]', $spoilerTitle, $hiddenContent);
            $hiddenSpoiler = SafeHtml::fromTrustedHtml(Format::formatComment($spoiler, false));
        }

        return new BdInfoColumnViewModel(visibleRows: $visibleRows, hiddenSpoiler: $hiddenSpoiler);
    }

    /**
     * Clean up a string
     */
    private function trim(string $value): string
    {
        return trim($value, " \n\r\t\v\0\u{A0}");
    }
}

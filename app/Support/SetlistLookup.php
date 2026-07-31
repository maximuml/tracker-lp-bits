<?php

namespace App\Support;

class SetlistLookup
{
    private const JINA_BASE = 'https://r.jina.ai/http://';
    private const LINKINPEDIA_API = 'https://linkinpedia.com/api.php';

    /**
     * Try to build a structured setlist from a torrent name.
     *
     * @param string $torrentName
     * @return array<string, mixed>
     */
    public static function fromTorrentName(string $torrentName): array
    {
        $meta = TorrentNameParser::parse($torrentName);

        if (empty($meta['artist']) || empty($meta['date'])) {
            return ['success' => false, 'error' => 'Could not parse artist or date from torrent name.'];
        }

        // 1. Linkinpedia is more reliable for LP/FM/JK/DBS shows
        $linkinpediaResult = self::fromLinkinpedia($meta);
        if ($linkinpediaResult['success']) {
            return $linkinpediaResult;
        }

        // 2. Fallback to setlist.fm search
        $queries = self::buildSearchQueries($meta);
        foreach ($queries as $query) {
            $searchUrl = 'www.setlist.fm/search?query=' . urlencode($query);
            $searchMarkdown = self::fetchJina($searchUrl);
            $setlistUrl = $searchMarkdown ? self::extractFirstSetlistUrl($searchMarkdown) : null;
            if ($setlistUrl && self::isRelevantSetlistUrl($setlistUrl, $meta)) {
                $result = self::fromUrl($setlistUrl, $meta);
                if ($result['success']) {
                    return $result;
                }
            }
        }

        return ['success' => false, 'error' => 'No setlist found for: ' . $torrentName];
    }

    /**
     * Fetch and parse a setlist.fm (or any compatible) URL.
     *
     * @param string $url
     * @param array<string, string> $meta
     * @return array<string, mixed>
     */
    public static function fromUrl(string $url, array $meta = []): array
    {
        $markdown = self::fetchSetlistMarkdown($url);

        if (empty($markdown)) {
            return ['success' => false, 'error' => 'Could not fetch setlist page.'];
        }

        $data = self::parseSetlistMarkdown($markdown);

        if (!$data) {
            return ['success' => false, 'error' => 'Could not parse setlist from page.'];
        }

        $data['source'] = $url;
        $data['meta'] = $meta;

        return ['success' => true, 'data' => $data, 'text' => self::formatForDescription($data)];
    }

    /**
     * @param array<string, string> $meta
     * @return array<int, string>
     */
    private static function buildSearchQueries(array $meta): array
    {
        $artist = $meta['artist'];
        $event = $meta['event'];
        $city = $meta['city'];
        $country = $meta['country'];
        $date = $meta['date'];
        $year = $meta['year'];

        $queries = [];
        if ($city && $country && $year) {
            $queries[] = "$artist $city $country $year";
        }
        if ($event && $year) {
            $queries[] = "$artist $event $year";
        }
        if ($city && $country && $date) {
            $queries[] = "$artist $city $country $date";
        }
        if ($event && $date) {
            $queries[] = "$artist $event $date";
        }
        $queries[] = "$artist $year";

        return array_values(array_unique($queries));
    }

    /**
     * @param array<string, string> $meta
     * @return array<string, mixed>
     */
    private static function fromLinkinpedia(array $meta): array
    {
        if (empty($meta['date'])) {
            return ['success' => false, 'error' => 'No date for Linkinpedia fallback.'];
        }

        $date = $meta['date']; // YYYY-MM-DD
        [$year, $month, $day] = explode('-', $date);
        $pageBase = $year . $month . $day;

        // Some dates have multiple shows (a, b, c...)
        $suffixes = ['', 'a', 'b', 'c', 'd', 'e', 'f', 'g', 'h', 'i', 'j', 'k', 'l', 'm'];

        foreach ($suffixes as $suffix) {
            $page = 'Live:' . $pageBase . $suffix;
            $wikitext = self::fetchLinkinpediaWikitext($page);
            if ($wikitext === null) {
                continue;
            }

            $tourdate = self::parseTemplate($wikitext, 'Tourdate');
            $pageArtist = $tourdate['Performing Act'] ?? $tourdate['Artist'] ?? '';
            if (!$pageArtist || !self::artistsMatch($pageArtist, $meta['artist'])) {
                continue;
            }

            $data = self::parseLinkinpediaWikitext($wikitext, $meta, $tourdate);
            if (!$data || empty($data['sets'])) {
                continue;
            }

            $data['source'] = 'https://linkinpedia.com/wiki/' . $page;
            $data['meta'] = $meta;

            return ['success' => true, 'data' => $data, 'text' => self::formatForDescription($data)];
        }

        return ['success' => false, 'error' => 'No matching Linkinpedia page found.'];
    }

    private static function fetchLinkinpediaWikitext(string $page): ?string
    {
        $url = self::LINKINPEDIA_API . '?action=parse&page=' . urlencode($page) . '&prop=wikitext&format=json';
        $ctx = stream_context_create([
            'http' => ['timeout' => 30, 'follow_location' => true],
            'ssl' => ['verify_peer' => false, 'verify_peer_name' => false],
        ]);

        $json = @file_get_contents($url, false, $ctx);
        if (!$json) {
            return null;
        }

        $data = json_decode($json, true);
        if (isset($data['error'])) {
            return null;
        }
        $wikitext = $data['parse']['wikitext']['*'] ?? null;

        if (!is_string($wikitext) || $wikitext === '') {
            return null;
        }

        return $wikitext;
    }

    /**
     * @param string $a
     * @param string $b
     * @return bool
     */
    private static function artistsMatch(string $a, string $b): bool
    {
        $a = strtolower(preg_replace('/[^a-z0-9]/', '', $a));
        $b = strtolower(preg_replace('/[^a-z0-9]/', '', $b));

        if ($a === $b) {
            return true;
        }

        // Linkin Park aliases
        if (($a === 'linkinpark' && $b === 'linkinpark') || ($a === 'lp' && $b === 'linkinpark')) {
            return true;
        }

        $aliases = [
            'linkinpark' => ['lp'],
            'fortminor' => ['fm'],
            'juliank' => ['julienk', 'jk'],
            'deadbysunrise' => ['dbs'],
            'chesterbennington' => ['chester'],
            'mikeshinoda' => ['mike'],
        ];

        foreach ($aliases as $base => $list) {
            $matchBase = ($a === $base) || in_array($a, $list, true);
            $matchOther = ($b === $base) || in_array($b, $list, true);
            if ($matchBase && $matchOther) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param string $wikitext
     * @param array<string, string> $meta
     * @param array<string, string> $tourdate
     * @return array<string, mixed>|null
     */
    private static function parseLinkinpediaWikitext(string $wikitext, array $meta, array $tourdate): ?array
    {
        $artist = $tourdate['Performing Act'] ?? $tourdate['Artist'] ?? $meta['artist'];
        $venue = $tourdate['Venue'] ?? $meta['event'] ?? '';
        $city = $tourdate['City'] ?? $meta['city'] ?? '';
        $state = $tourdate['State'] ?? $meta['state'] ?? '';
        $country = $tourdate['Country'] ?? $meta['country'] ?? '';
        $event = $tourdate['Event'] ?? $meta['event'] ?? $venue;

        // Some Linkinpedia pages include the state in the City field (e.g. "Tempe, AZ")
        if ($state && (str_ends_with($city, ', ' . $state) || str_ends_with($city, ', ' . strtoupper($state)))) {
            $state = '';
        }

        if ($city && $state && $country) {
            $venueString = $venue ? "$venue, $city, $state, $country" : "$city, $state, $country";
        } elseif ($city && $country) {
            $venueString = $venue ? "$venue, $city, $country" : "$city, $country";
        } else {
            $venueString = $venue ?: $event;
        }

        $year = $tourdate['Year'] ?? $meta['year'] ?? '';
        $month = $tourdate['Month'] ?? '';
        $day = $tourdate['Day'] ?? '';
        $date = $meta['date'];
        if ($year && $month && $day) {
            $dateMonth = self::monthNumber($month);
            if ($dateMonth) {
                $date = sprintf('%04d-%02d-%02d', $year, $dateMonth, $day);
            }
        }

        $setlistFields = self::parseTemplate($wikitext, 'Setlist');
        if (!$setlistFields) {
            return null;
        }

        // Act markers: ActNoX = act number, ActNameX = act name (act starts before song X+1)
        // Break markers: BreakX = 'Encore' (break before song X+1)
        $acts = [];
        $breaks = [];
        foreach ($setlistFields as $key => $value) {
            if (preg_match('/^ActNo(\d+)$/', $key, $m)) {
                $idx = (int) $m[1];
                $acts[$idx] = $setlistFields['ActName' . $idx] ?? ('Act ' . $value);
            }
            if (preg_match('/^Break(\d+)$/', $key, $m)) {
                $breaks[(int) $m[1]] = $value;
            }
        }

        $sets = [];
        $current = ['name' => 'Main Set', 'songs' => []];

        for ($i = 1; $i <= 99; $i++) {
            $key = "Song$i";
            if (!isset($setlistFields[$key])) {
                continue;
            }

            $song = trim($setlistFields[$key]);
            if (!$song) {
                continue;
            }

            // New act / break before this song?
            $prevIdx = $i - 1;
            if (isset($breaks[$prevIdx]) && strtolower($breaks[$prevIdx]) === 'encore') {
                if (!empty($current['songs'])) {
                    $sets[] = $current;
                }
                $current = ['name' => 'Encore', 'songs' => []];
            } elseif (isset($acts[$prevIdx])) {
                if (!empty($current['songs'])) {
                    $sets[] = $current;
                }
                $current = ['name' => $acts[$prevIdx], 'songs' => []];
            }

            // Strip wiki links
            $song = preg_replace('/\[\[(?:[^\]|]+\|)?([^\]]+)\]\]/', '$1', $song);

            $notes = [];

            if (isset($setlistFields["TapeNote$i"])) {
                $tn = trim($setlistFields["TapeNote$i"]);
                if ($tn) {
                    $notes[] = $tn;
                }
            }

            if (isset($setlistFields["Note$i"])) {
                $note = trim($setlistFields["Note$i"]);
                if ($note) {
                    $note = preg_replace('/\[\[(?:[^\]|]+\|)?([^\]]+)\]\]/', '$1', $note);
                    $notes[] = $note;
                }
            }

            if (isset($setlistFields["Rotation$i"]) && strtolower(trim($setlistFields["Rotation$i"])) === 'yes') {
                $notes[] = 'rotated';
            }

            $current['songs'][] = [
                'name' => $song,
                'note' => implode('; ', $notes),
            ];
        }

        if (!empty($current['songs'])) {
            $sets[] = $current;
        }

        if (!$sets) {
            return null;
        }

        return [
            'title' => $artist . ' - ' . $venueString,
            'artist' => $artist,
            'venue' => $venueString,
            'date' => $date,
            'sets' => $sets,
        ];
    }

    /**
     * Parse a MediaWiki template from wikitext, handling nested {{...}} blocks.
     *
     * @param string $wikitext
     * @param string $templateName
     * @return array<string, string>
     */
    private static function parseTemplate(string $wikitext, string $templateName): array
    {
        $body = self::extractTemplate($wikitext, $templateName);
        if ($body === null) {
            return [];
        }

        $fields = [];
        $lines = preg_split('/\r?\n/', $body);
        foreach ($lines as $line) {
            $line = ltrim($line, '| ');
            if (!$line) {
                continue;
            }
            if (preg_match('/^([^=]+?)\s*=\s*(.*)$/', $line, $m2)) {
                $fields[trim($m2[1])] = trim($m2[2]);
            }
        }

        return $fields;
    }

    /**
     * Extract the inner body of a MediaWiki template with balanced braces.
     *
     * @param string $wikitext
     * @param string $templateName
     * @return string|null
     */
    private static function extractTemplate(string $wikitext, string $templateName): ?string
    {
        if (preg_match('/\{\{\s*' . preg_quote($templateName, '/') . '(?:\s*\n|\s*\|)?/s', $wikitext, $m, PREG_OFFSET_CAPTURE) !== 1) {
            return null;
        }

        $start = $m[0][1] + strlen($m[0][0]);
        $depth = 1;
        $pos = $start;
        $len = strlen($wikitext);

        while ($pos < $len && $depth > 0) {
            $two = substr($wikitext, $pos, 2);
            if ($two === '{{') {
                $depth++;
                $pos += 2;
                continue;
            }
            if ($two === '}}') {
                $depth--;
                $pos += 2;
                continue;
            }
            $pos++;
        }

        if ($depth !== 0) {
            return null;
        }

        // $pos now points just after the closing '}}'
        return substr($wikitext, $start, $pos - $start - 2);
    }

    private static function monthNumber(string $month): int
    {
        $map = [
            'january' => 1, 'february' => 2, 'march' => 3, 'april' => 4,
            'may' => 5, 'june' => 6, 'july' => 7, 'august' => 8,
            'september' => 9, 'october' => 10, 'november' => 11, 'december' => 12,
            'jan' => 1, 'feb' => 2, 'mar' => 3, 'apr' => 4,
            'jun' => 6, 'jul' => 7, 'aug' => 8,
            'sep' => 9, 'oct' => 10, 'nov' => 11, 'dec' => 12,
        ];

        return $map[strtolower($month)] ?? 0;
    }

    /**
     * @param string $url
     * @param array<string, string> $meta
     * @return bool
     */
    private static function isRelevantSetlistUrl(string $url, array $meta): bool
    {
        $path = parse_url($url, PHP_URL_PATH) ?? '';
        $artist = strtolower(preg_replace('/[^a-z0-9]+/', '-', $meta['artist']));
        $artistSlug = str_replace('-', '', $meta['artist']); // e.g. "juliank"

        // Artist slug must appear in path
        if (str_contains($path, '/' . $artist . '/') || str_contains($path, $artist . '/')) {
            // good
        } elseif (str_contains($path, str_replace('-', '', $artist) . '/')) {
            // e.g. "julienk/"
        } elseif ($meta['artist'] === 'Fort Minor' && str_contains($path, '/fort-minor/')) {
            // ok
        } else {
            return false;
        }

        // Year should be present in path
        $year = $meta['year'];
        if ($year && !preg_match('/\/' . preg_quote($year, '/') . '\//', $path)) {
            return false;
        }

        return true;
    }

    private static function fetchSetlistMarkdown(string $url): ?string
    {
        $parsed = parse_url($url);
        $host = $parsed['host'] ?? 'www.setlist.fm';
        $path = $parsed['path'] ?? '/';
        $query = isset($parsed['query']) ? '?' . $parsed['query'] : '';
        $fetchUrl = $host . $path . $query;

        return self::fetchJina($fetchUrl);
    }

    private static function fetchJina(string $urlPath): ?string
    {
        $fullUrl = self::JINA_BASE . ltrim($urlPath, '/');

        $ctx = stream_context_create([
            'http' => [
                'timeout' => 30,
                'header' => "User-Agent: Mozilla/5.0 (compatible; tracker-lp-bits)\r\n",
                'follow_location' => true,
            ],
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false,
            ],
        ]);

        $content = @file_get_contents($fullUrl, false, $ctx);

        return $content !== false ? $content : null;
    }

    private static function extractFirstSetlistUrl(string $searchMarkdown): ?string
    {
        // Markdown link: [Title](http://www.setlist.fm/setlist/...html "...")
        if (preg_match('/\((https?:\/\/www\.setlist\.fm\/setlist\/[^\s\")]+?\.html)/', $searchMarkdown, $m)) {
            return $m[1];
        }

        // Plain URL fallback
        if (preg_match('/https?:\/\/www\.setlist\.fm\/setlist\/[^\s\")]+?\.html/', $searchMarkdown, $m)) {
            return $m[0];
        }

        return null;
    }

    /**
     * @param string $markdown
     * @return array<string, mixed>|null
     */
    private static function parseSetlistMarkdown(string $markdown): ?array
    {
        $title = '';
        if (preg_match('/^Title:\s*(.+)$/m', $markdown, $m)) {
            $title = trim($m[1]);
        }

        $artist = '';
        $venue = '';
        $date = '';

        // e.g. # **[Linkin Park](url) Setlist** at [Nürburgring, Nürburg, Germany](url)
        if (preg_match('/#\s*\*\*\[([^\]]+)\].*?Setlist\*\*\s*(?:at\s*)?\[([^\]]+)\]/i', $markdown, $m)) {
            $artist = strip_tags($m[1]);
            $venue = strip_tags($m[2]);
        } elseif (preg_match('/#\s*\*\*\[([^\]]+)\].*?Setlist\*\*/i', $markdown, $m)) {
            $artist = strip_tags($m[1]);
        }

        if (preg_match('/(Jan|Feb|Mar|Apr|May|Jun|Jul|Aug|Sep|Oct|Nov|Dec)\s+\d{1,2}\s+\d{4}/i', $markdown, $m)) {
            $date = date('Y-m-d', strtotime($m[0]));
        } elseif (preg_match('/\b(\d{4}-\d{2}-\d{2})\b/', $markdown, $m)) {
            $date = $m[1];
        }

        if (!preg_match('/## Setlist\s*\n(.*)(?=\n## |\n\[I was there\]|\n## Songs on Albums|$)/s', $markdown, $m)) {
            return null;
        }

        $setlistBlock = $m[1];
        $sets = [];
        $current = ['name' => 'Main Set', 'songs' => []];

        foreach (explode("\n", $setlistBlock) as $line) {
            $line = trim($line);
            if (!$line) {
                continue;
            }

            if (str_starts_with($line, '## ')) {
                break;
            }

            // Section marker: "3.   Act I" or "30.   Encore:"
            if (preg_match('/^\d+\.\s*(Act [IV]+|Encore:?)$/i', $line, $m)) {
                if (!empty($current['songs'])) {
                    $sets[] = $current;
                }
                $current = ['name' => rtrim($m[1], ':'), 'songs' => []];
                continue;
            }

            $song = self::parseSetlistSongLine($line);
            if ($song) {
                $current['songs'][] = $song;
            }
        }

        if (!empty($current['songs'])) {
            $sets[] = $current;
        }

        if (empty($sets)) {
            return null;
        }

        return [
            'title' => $title,
            'artist' => $artist,
            'venue' => $venue,
            'date' => $date,
            'sets' => $sets,
        ];
    }

    /**
     * @param string $line
     * @return array<string, string>|null
     */
    private static function parseSetlistSongLine(string $line): ?array
    {
        if (!preg_match('/^\d+\.\s+(.*)$/', $line, $m)) {
            return null;
        }

        $rest = trim($m[1]);

        // Remove Play Video link
        $rest = self::stripMarkdownLinks($rest);
        $rest = preg_replace('/\bPlay Video\b/', '', $rest);
        $rest = trim($rest);

        if (!$rest) {
            return null;
        }

        // "Song played from tape ..." prefix
        $isTape = false;
        if (preg_match('/^Song played from tape\s+(.*)$/i', $rest, $m2)) {
            $isTape = true;
            $rest = trim($m2[1]);
        }

        // Split title and note by the last balanced parentheses group
        $title = $rest;
        $note = '';
        if (preg_match('/^(.*)\s+\(([^()]*)\)$/s', $rest, $m2)) {
            $title = trim($m2[1]);
            $note = trim($m2[2]);
        }

        // Fix missing spaces before "cover" / "song"
        $note = preg_replace('/([a-zA-Z])(cover|song)\b/', '$1 $2', $note);

        if ($isTape) {
            $note = ($note ? 'Song played from tape; ' : 'Song played from tape') . $note;
        }

        $title = trim(preg_replace('/\s+/', ' ', $title));

        if (!$title) {
            return null;
        }

        return ['name' => $title, 'note' => $note];
    }

    private static function stripMarkdownLinks(string $text): string
    {
        // [Label](url "title") -> Label, with a trailing space if a word char follows
        $text = preg_replace('/\[([^\]]+)\]\((?:https?:\/\/[^\s\)"]+)(?:\s+"[^"]*")?\)(?=[a-zA-Z0-9])/', '$1 ', $text);
        $text = preg_replace('/\[([^\]]+)\]\((?:https?:\/\/[^\s\)"]+)(?:\s+"[^"]*")?\)/', '$1', $text);

        return $text;
    }

    /**
     * @param array<string, mixed> $data
     * @return string
     */
    private static function formatForDescription(array $data): string
    {
        $lines = [];

        if (!empty($data['artist'])) {
            $lines[] = '[b]Artist:[/b] ' . $data['artist'];
        }
        if (!empty($data['venue'])) {
            $lines[] = '[b]Venue:[/b] ' . $data['venue'];
        }
        if (!empty($data['date'])) {
            $lines[] = '[b]Date:[/b] ' . $data['date'];
        }
        if (!empty($data['source'])) {
            $lines[] = '[b]Source:[/b] ' . $data['source'];
        }
        $lines[] = '';

        foreach ($data['sets'] as $set) {
            $lines[] = '[b]' . $set['name'] . '[/b]';
            $counter = 1;
            foreach ($set['songs'] as $song) {
                $line = $counter . '. ' . $song['name'];
                if (!empty($song['note'])) {
                    $line .= ' (' . $song['note'] . ')';
                }
                $lines[] = $line;
                $counter++;
            }
            $lines[] = '';
        }

        return rtrim(implode("\n", $lines));
    }
}

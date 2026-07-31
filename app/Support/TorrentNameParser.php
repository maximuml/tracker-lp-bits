<?php

namespace App\Support;

class TorrentNameParser
{
    /**
     * Extract group/artist, event/title, city, state, country, and date from a torrent name.
     *
     * Supported patterns:
     *   Linkin Park - Lisboa, Portugal, Rock in Rio (21.06.2026)
     *   Julien-K - Tempe, AZ, USA, Club Tattoo 13th Anniversary (10.05.2008)
     *   Linkin Park - Hamburg, Germany, Volksparkstadion (03.06.2026)
     *   Fort Minor - Hamburg, Germany, Docks (16.11.2005)
     *
     * @param string $name
     * @return array<string, string>
     */
    public static function parse(string $name): array
    {
        $name = trim($name);

        // 1. Extract and normalize date DD.MM.YYYY -> YYYY-MM-DD
        $date = '';
        if (preg_match('/\((\d{2})\.(\d{2})\.(\d{4})\)/', $name, $m)) {
            $date = $m[3] . '-' . $m[2] . '-' . $m[1];
        } elseif (preg_match('/(\d{4}-\d{2}-\d{2})/', $name, $m)) {
            $date = $m[1];
        } elseif (preg_match('/\b(\d{4})\b/', $name, $m)) {
            $date = $m[1];
        }

        // 2. Remove the date block and common quality/format tags
        $cleaned = preg_replace('/\s*\(\d{2}\.\d{2}\.\d{4}\)\s*$/', '', $name);
        $cleaned = preg_replace('/\s*[\[(].*?[\])]/s', '', $cleaned);
        $cleaned = trim($cleaned);

        // 3. Split artist from the rest: "Artist - rest"
        $parts = preg_split('/\s+-\s+/', $cleaned, 2);
        $artist = trim($parts[0] ?? '');
        $remainder = trim($parts[1] ?? '');

        // 4. Split remainder by commas
        $tokens = array_map('trim', explode(',', $remainder));

        // 5. The last token is normally the event/venue title
        $event = (string) array_pop($tokens);

        // 6. Identify country, state and city from remaining tokens
        $city = '';
        $state = '';
        $country = '';
        $count = count($tokens);

        if ($count >= 1) {
            // If we have at least 2 tokens left and the last of them is a known country
            // e.g. [Lisboa, Portugal] -> country=Portugal
            $candidate = $tokens[$count - 1];
            if (self::isCountry($candidate)) {
                $country = $candidate;
                $remaining = array_slice($tokens, 0, $count - 1);

                if (count($remaining) === 1) {
                    $city = $remaining[0];
                } elseif (count($remaining) >= 2) {
                    // If the last remaining token is a US state code, treat it as state
                    if (self::isUsState($remaining[count($remaining) - 1])) {
                        $state = array_pop($remaining);
                    }
                    $city = implode(', ', $remaining);
                }
            } elseif ($count >= 2 && self::isUsState($tokens[$count - 2])) {
                // Country was not recognized as a country, but a state code sits before the last token
                $country = $tokens[$count - 1];
                $state = $tokens[$count - 2];
                $city = implode(', ', array_slice($tokens, 0, $count - 2));
            } else {
                // Fallback: assume first token is city, last remaining is country
                $country = $candidate;
                $city = implode(', ', array_slice($tokens, 0, $count - 1));
            }
        } else {
            // No commas at all, the whole remainder is the event
            $event = $remainder;
        }

        return [
            'artist' => $artist,
            'event' => $event,
            'city' => $city,
            'state' => $state,
            'country' => $country,
            'date' => $date,
            'year' => $date ? substr($date, 0, 4) : '',
        ];
    }

    /**
     * @param string $value
     * @return bool
     */
    private static function isCountry(string $value): bool
    {
        $countries = [
            'afghanistan', 'albania', 'algeria', 'argentina', 'armenia', 'australia', 'austria',
            'azerbaijan', 'bahrain', 'belarus', 'belgium', 'bolivia', 'bosnia and herzegovina',
            'brazil', 'bulgaria', 'cambodia', 'canada', 'chile', 'china', 'colombia', 'costa rica',
            'croatia', 'cyprus', 'czech republic', 'czechia', 'denmark', 'ecuador', 'egypt',
            'estonia', 'finland', 'france', 'germany', 'greece', 'hungary', 'iceland', 'india',
            'indonesia', 'iran', 'iraq', 'ireland', 'israel', 'italy', 'japan', 'jordan',
            'kazakhstan', 'kenya', 'kuwait', 'latvia', 'lebanon', 'lithuania', 'luxembourg',
            'malaysia', 'malta', 'mexico', 'morocco', 'netherlands', 'new zealand', 'nigeria',
            'norway', 'oman', 'pakistan', 'panama', 'paraguay', 'peru', 'philippines', 'poland',
            'portugal', 'qatar', 'romania', 'russia', 'russian federation', 'saudi arabia',
            'serbia', 'singapore', 'slovakia', 'slovenia', 'south africa', 'south korea',
            'spain', 'sweden', 'switzerland', 'thailand', 'turkey', 'ukraine', 'united arab emirates',
            'united kingdom', 'united states', 'united states of america', 'uruguay', 'uzbekistan',
            'venezuela', 'vietnam',
        ];

        return in_array(strtolower($value), $countries, true);
    }

    /**
     * @param string $value
     * @return bool
     */
    private static function isUsState(string $value): bool
    {
        $states = [
            'al', 'ak', 'az', 'ar', 'ca', 'co', 'ct', 'de', 'fl', 'ga', 'hi', 'id', 'il',
            'in', 'ia', 'ks', 'ky', 'la', 'me', 'md', 'ma', 'mi', 'mn', 'ms', 'mo', 'mt',
            'ne', 'nv', 'nh', 'nj', 'nm', 'ny', 'nc', 'nd', 'oh', 'ok', 'or', 'pa', 'ri',
            'sc', 'sd', 'tn', 'tx', 'ut', 'vt', 'va', 'wa', 'wv', 'wi', 'wy', 'dc',
        ];

        return in_array(strtolower($value), $states, true);
    }
}

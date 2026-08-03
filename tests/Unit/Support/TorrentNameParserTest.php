<?php

namespace Tests\Unit\Support;

use App\Support\TorrentNameParser;
use PHPUnit\Framework\TestCase;

final class TorrentNameParserTest extends TestCase
{
    public function test_parses_standard_artist_city_country_event_date(): void
    {
        $result = TorrentNameParser::parse('Linkin Park - Lisboa, Portugal, Rock in Rio (21.06.2026)');

        $this->assertSame('Linkin Park', $result['artist']);
        $this->assertSame('Rock in Rio', $result['event']);
        $this->assertSame('Lisboa', $result['city']);
        $this->assertSame('', $result['state']);
        $this->assertSame('Portugal', $result['country']);
        $this->assertSame('2026-06-21', $result['date']);
        $this->assertSame('2026', $result['year']);
    }

    public function test_parses_artist_city_state_country_event_date(): void
    {
        $result = TorrentNameParser::parse('Julien-K - Tempe, AZ, USA, Club Tattoo 13th Anniversary (10.05.2008)');

        $this->assertSame('Julien-K', $result['artist']);
        $this->assertSame('Club Tattoo 13th Anniversary', $result['event']);
        $this->assertSame('Tempe', $result['city']);
        $this->assertSame('AZ', $result['state']);
        $this->assertSame('USA', $result['country']);
        $this->assertSame('2008-05-10', $result['date']);
    }

    public function test_parses_german_city_and_event(): void
    {
        $result = TorrentNameParser::parse('Linkin Park - Hamburg, Germany, Volksparkstadion (03.06.2026)');

        $this->assertSame('Linkin Park', $result['artist']);
        $this->assertSame('Volksparkstadion', $result['event']);
        $this->assertSame('Hamburg', $result['city']);
        $this->assertSame('Germany', $result['country']);
        $this->assertSame('2026-06-03', $result['date']);
    }

    public function test_falls_back_to_year_when_no_parenthesized_date(): void
    {
        $result = TorrentNameParser::parse('Fort Minor - Hamburg, Germany, Docks 2005');

        $this->assertSame('Fort Minor', $result['artist']);
        $this->assertSame('Docks 2005', $result['event']);
        $this->assertSame('2005', $result['date']);
        $this->assertSame('2005', $result['year']);
    }

    public function test_without_dash_artist_is_full_string_and_event_is_empty(): void
    {
        $result = TorrentNameParser::parse('Just a title with no artist');

        $this->assertSame('Just a title with no artist', $result['artist']);
        $this->assertSame('', $result['event']);
        $this->assertSame('', $result['date']);
    }

    public function test_ignores_bracket_quality_tags(): void
    {
        $result = TorrentNameParser::parse('Linkin Park - Hamburg, Germany, Docks [FLAC] (16.11.2005)');

        $this->assertSame('Linkin Park', $result['artist']);
        $this->assertSame('Docks', $result['event']);
        $this->assertSame('2005-11-16', $result['date']);
    }

    public function test_ignores_parentheses_with_non_date_content(): void
    {
        $result = TorrentNameParser::parse('Linkin Park - Hamburg, Germany, Docks (Live) (16.11.2005)');

        $this->assertSame('Linkin Park', $result['artist']);
        $this->assertSame('Docks', $result['event']);
        $this->assertSame('2005-11-16', $result['date']);
    }
}

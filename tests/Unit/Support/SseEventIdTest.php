<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use App\Support\SseEventId;
use PHPUnit\Framework\TestCase;
use Tests\Attributes\TestCategory;

/**
 * REL-02: Last-Event-ID codec for the multi-channel notification
 * stream — a bare numeric id can only carry the pm channel, the JSON
 * form carries them all.
 */
#[TestCategory(TestCategory::PURE_UNIT)]
final class SseEventIdTest extends TestCase
{
    public function test_encode_round_trip(): void
    {
        $cursors = ['pm' => 5, 'shout' => 2, 'comment' => 9, 'topic_reply' => 1, 'staff' => 0];

        $decoded = SseEventId::applyTo(SseEventId::encode($cursors), array_map(fn () => 0, $cursors));

        $this->assertSame($cursors, $decoded);
    }

    public function test_json_id_overrides_query_params_per_channel(): void
    {
        $params = ['pm' => 1, 'shout' => 1, 'comment' => 1, 'topic_reply' => 1, 'staff' => 1];

        $out = SseEventId::applyTo('{"pm":7,"comment":3}', $params);

        $this->assertSame(7, $out['pm']);
        $this->assertSame(3, $out['comment']);
        $this->assertSame(1, $out['shout']);
        $this->assertSame(1, $out['topic_reply']);
        $this->assertSame(1, $out['staff']);
    }

    public function test_legacy_numeric_id_advances_only_pm(): void
    {
        $params = ['pm' => 1, 'shout' => 4];

        $out = SseEventId::applyTo('42', $params);

        $this->assertSame(42, $out['pm']);
        $this->assertSame(4, $out['shout']);
    }

    public function test_numeric_id_never_moves_a_channel_backwards(): void
    {
        $out = SseEventId::applyTo('3', ['pm' => 10]);

        $this->assertSame(10, $out['pm']);
    }

    public function test_garbage_and_empty_ids_are_ignored(): void
    {
        $params = ['pm' => 2, 'shout' => 3];

        $this->assertSame($params, SseEventId::applyTo('', $params));
        $this->assertSame($params, SseEventId::applyTo('not-json{', $params));
        $this->assertSame($params, SseEventId::applyTo('"str"', $params));
        $this->assertSame($params, SseEventId::applyTo('{"pm":"abc"}', $params));
        $this->assertSame($params, SseEventId::applyTo('{"pm":-5}', $params));
    }
}

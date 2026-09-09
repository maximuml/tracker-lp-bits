<?php

namespace Tests\Unit\Support;

use App\Support\Json;
use PHPUnit\Framework\TestCase;
use Tests\Attributes\TestCategory;

#[TestCategory(TestCategory::PURE_UNIT)]
class JsonTest extends TestCase
{
    public function test_encode_uses_legacy_flags(): void
    {
        $this->assertSame('{"a":"b/c"}', Json::encode(['a' => 'b/c']));
        $this->assertSame('{"msg":"hello 世界"}', Json::encode(['msg' => 'hello 世界']));
    }
}

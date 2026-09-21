<?php

declare(strict_types=1);

namespace Tests\Integration\Http\Requests;

use App\Http\Requests\TorrentUploadRequest;
use Tests\Attributes\TestCategory;
use Tests\TestCase;

#[TestCategory(TestCategory::SERVICE_INTEGRATION)]
final class TorrentUploadRequestTest extends TestCase
{
    public function test_rules_use_real_form_field_names(): void
    {
        $request = new TorrentUploadRequest;

        $rules = $request->rules();

        $this->assertSame('sometimes|nullable|string|max:255', $rules['name']);
        $this->assertSame('sometimes|in:yes', $rules['uplver']);
        $this->assertArrayHasKey('offer', $rules);
        $this->assertArrayNotHasKey('anonymous', $rules);
        $this->assertArrayNotHasKey('offer_id', $rules);
        $this->assertStringContainsString('exists:categories,id', $rules['type']);
        $this->assertStringContainsString('max:', $rules['file']);
        $this->assertTrue($request->authorize());
    }

    public function test_messages_are_localized(): void
    {
        $messages = (new TorrentUploadRequest)->messages();

        $this->assertSame(
            ['descr.required', 'descr.min', 'type.required', 'type.min', 'type.integer', 'type.exists',
                'file.required', 'file.file', 'file.uploaded', 'file.mimetypes', 'file.max'],
            array_keys($messages)
        );
        $this->assertNotEmpty($messages['descr.required']);
        $this->assertNotEmpty($messages['file.max']);
    }
}

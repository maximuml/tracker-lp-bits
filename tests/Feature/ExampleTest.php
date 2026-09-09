<?php

namespace Tests\Feature;

use Tests\Attributes\TestCategory;
use Tests\TestCase;

#[TestCategory(TestCategory::HTTP_FEATURE)]
class ExampleTest extends TestCase
{
    /**
     * A basic test example.
     *
     * @return void
     */
    public function test_root_redirects_to_index()
    {
        $response = $this->get('/');

        $response->assertStatus(302);
    }
}

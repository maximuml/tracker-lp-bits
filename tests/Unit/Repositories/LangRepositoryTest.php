<?php

declare(strict_types=1);

namespace Tests\Unit\Repositories;

use App\Repositories\BaseRepository;
use App\Repositories\LangRepository;
use Tests\TestCase;

final class LangRepositoryTest extends TestCase
{
    public function test_is_instantiable(): void
    {
        $repo = new LangRepository;

        $this->assertInstanceOf(LangRepository::class, $repo);
    }

    public function test_extends_base_repository(): void
    {
        $repo = new LangRepository;

        $this->assertInstanceOf(BaseRepository::class, $repo);
    }

    public function test_inherited_allowed_sort_columns_defaults_to_id(): void
    {
        $repo = new LangRepository;
        $method = new \ReflectionMethod($repo, 'allowedSortColumns');
        $method->setAccessible(true);

        $this->assertSame(['id'], $method->invoke($repo));
    }

    public function test_inherited_get_sort_field_and_type_defaults_to_id_desc(): void
    {
        $repo = new LangRepository;
        $method = new \ReflectionMethod($repo, 'getSortFieldAndType');
        $method->setAccessible(true);

        [$field, $type] = $method->invoke($repo, []);

        $this->assertSame('id', $field);
        $this->assertSame('desc', $type);
    }

    public function test_inherited_get_sort_field_and_type_falls_back_when_field_not_allowed(): void
    {
        $repo = new LangRepository;
        $method = new \ReflectionMethod($repo, 'getSortFieldAndType');
        $method->setAccessible(true);

        [$field, $type] = $method->invoke($repo, ['sort_field' => 'evil', 'sort_type' => 'asc']);

        $this->assertSame('id', $field);
        $this->assertSame('asc', $type);
    }
}

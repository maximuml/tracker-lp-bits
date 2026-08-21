<?php

namespace Tests\Feature;

use App\Console\Commands\EsCreateIndex;
use App\Console\Commands\EsDeleteIndex;
use App\Console\Commands\EsImport;
use App\Console\Commands\EsInfo;
use App\Listeners\SyncTorrentToElasticsearch;
use App\Repositories\SearchRepository;
use App\Support\SearchSuggest;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * Phase 4.1: verify that Elasticsearch has been fully removed from the
 * codebase — no SearchRepository class, no ES listener, no ES commands,
 * and no ES env vars in .env.example.
 */
final class Phase41RemoveElasticsearchTest extends TestCase
{
    use DatabaseTransactions;

    public function test_search_repository_class_no_longer_exists(): void
    {
        $this->assertFalse(
            class_exists(SearchRepository::class),
            'SearchRepository class should not exist after Elasticsearch removal.'
        );
    }

    public function test_es_listener_class_no_longer_exists(): void
    {
        $this->assertFalse(
            class_exists(SyncTorrentToElasticsearch::class),
            'SyncTorrentToElasticsearch listener should not exist after ES removal.'
        );
    }

    public function test_es_console_commands_no_longer_exist(): void
    {
        $this->assertFalse(class_exists(EsInfo::class));
        $this->assertFalse(class_exists(EsCreateIndex::class));
        $this->assertFalse(class_exists(EsDeleteIndex::class));
        $this->assertFalse(class_exists(EsImport::class));
    }

    public function test_elasticsearch_not_in_composer_json(): void
    {
        $composerJson = file_get_contents(base_path('composer.json'));
        $this->assertStringNotContainsString('elasticsearch/elasticsearch', (string) $composerJson);
    }

    public function test_elasticsearch_env_vars_removed_from_env_example(): void
    {
        $envExample = file_get_contents(base_path('.env.example'));
        $this->assertStringNotContainsString('ELASTICSEARCH_HOST', (string) $envExample);
        $this->assertStringNotContainsString('ELASTICSEARCH_ENABLED', (string) $envExample);
    }

    public function test_search_suggest_still_works_without_search_repository(): void
    {
        $this->assertTrue(class_exists(SearchSuggest::class));
        $this->assertTrue(method_exists(SearchSuggest::class, 'add'));
    }
}

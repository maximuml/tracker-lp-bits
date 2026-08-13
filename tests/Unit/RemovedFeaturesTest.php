<?php

namespace Tests\Unit;

use Tests\TestCase;

class RemovedFeaturesTest extends TestCase
{
    public function test_promotion_link_feature_is_removed(): void
    {
        $this->assertFileDoesNotExist(public_path('promotionlink.php'));
        $this->assertFileDoesNotExist(base_path('lang/en/lang_promotionlink.php'));
        $this->assertFileDoesNotExist(database_path('migrations/2021_06_08_113437_create_prolinkclicks_table.php'));

        // The legacy per-page wrappers are gone; verify the strings are not
        // present in the migrated views/controllers either.
        foreach ([
            base_path('resources/views/usercp/index.blade.php'),
            base_path('resources/views/usercp/_usercp.blade.php'),
            base_path('resources/views/my/bonus.blade.php'),
            base_path('resources/views/my/_bonus.blade.php'),
            base_path('resources/views/topten/index.blade.php'),
            base_path('resources/views/topten/_topten.blade.php'),
        ] as $file) {
            $this->assertFileExists($file);
            $content = file_get_contents($file);
            $this->assertStringNotContainsString('promotionlink', $content);
            $this->assertStringNotContainsString('prolinkpoint', $content);
            $this->assertStringNotContainsString('prolinkclicks', $content);
        }
    }

    public function test_only_english_language_pack_remains(): void
    {
        $dirs = array_values(array_filter(glob(lang_path('/*')), 'is_dir'));

        $this->assertCount(1, $dirs);
        $this->assertSame('en', basename($dirs[0]));
    }

    public function test_hot_classic_recommend_pick_options_removed(): void
    {
        foreach ([
            base_path('resources/views/torrents/upload.blade.php'),
            base_path('resources/views/torrents/_upload.blade.php'),
            base_path('resources/views/torrent/edit.blade.php'),
            base_path('resources/views/torrent/_edit.blade.php'),
            app_path('Http/Controllers/TorrentUploadController.php'),
            app_path('Http/Controllers/TorrentEditController.php'),
        ] as $file) {
            $this->assertFileExists($file);
            $content = file_get_contents($file);
            $this->assertStringNotContainsString('sel_recmovie', $content);
            $this->assertStringNotContainsString('picktype', $content);
        }
    }

    public function test_school_field_removed_from_signup(): void
    {
        foreach ([
            base_path('resources/views/auth/signup.blade.php'),
            app_path('Http/Controllers/Auth/RegistrationController.php'),
        ] as $file) {
            $this->assertFileExists($file);
            $this->assertStringNotContainsString('name="school"', file_get_contents($file));
        }
    }

    public function test_uploader_bandwidth_row_removed_from_torrent_details(): void
    {
        foreach ([
            base_path('resources/views/torrent/details.blade.php'),
            base_path('resources/views/torrent/_details.blade.php'),
            app_path('Http/Controllers/TorrentDetailsController.php'),
        ] as $file) {
            $this->assertFileExists($file);
            $content = file_get_contents($file);
            $this->assertStringNotContainsString('Uploader Bandwidth', $content);
            $this->assertStringNotContainsString('Upload Speed', $content);
        }
    }
}

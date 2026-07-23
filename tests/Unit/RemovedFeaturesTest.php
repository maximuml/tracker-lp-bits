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

        $this->assertStringNotContainsString('promotionlink', file_get_contents(public_path('usercp.php')));
        $this->assertStringNotContainsString('prolinkpoint', file_get_contents(public_path('mybonus.php')));
        $this->assertStringNotContainsString('prolinkclicks', file_get_contents(public_path('topten.php')));
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
            public_path('upload.php'),
            public_path('edit.php'),
            public_path('takeupload.php'),
            public_path('takeedit.php'),
            base_path('include/functions.php'),
        ] as $file) {
            $this->assertFileExists($file);
            $content = file_get_contents($file);
            $this->assertStringNotContainsString('sel_recmovie', $content);
            $this->assertStringNotContainsString('picktype', $content);
        }
    }

    public function test_school_field_removed_from_signup(): void
    {
        foreach ([public_path('signup.php'), public_path('takesignup.php')] as $file) {
            $this->assertFileExists($file);
            $this->assertStringNotContainsString('name="school"', file_get_contents($file));
        }
    }

    public function test_uploader_bandwidth_row_removed_from_torrent_details(): void
    {
        $content = file_get_contents(public_path('details.php'));
        $this->assertStringNotContainsString('Uploader Bandwidth', $content);
        $this->assertStringNotContainsString('Upload Speed', $content);
    }
}

<?php

declare(strict_types=1);

namespace Tests\Unit\Support\Config;

use App\Support\Config\AttachmentConfig;
use App\Support\Config\MainConfig;
use App\Support\Config\SecurityConfig;
use App\Support\Config\SiteConfig;
use App\Support\Config\TweakConfig;
use PHPUnit\Framework\TestCase;
use Tests\Attributes\TestCategory;

#[TestCategory(TestCategory::PURE_UNIT)]
final class TypedConfigTest extends TestCase
{
    public function test_attachment_config_save_directory_type(): void
    {
        $config = new AttachmentConfig(['savedirectorytype' => 'daydir']);

        $this->assertSame('daydir', $config->saveDirectoryType('monthdir'));
    }

    public function test_attachment_config_save_directory_type_default(): void
    {
        $config = new AttachmentConfig([]);

        $this->assertSame('monthdir', $config->saveDirectoryType('monthdir'));
    }

    public function test_attachment_config_save_directory(): void
    {
        $config = new AttachmentConfig(['savedirectory' => 'uploads']);

        $this->assertSame('uploads', $config->saveDirectory('attachments'));
    }

    public function test_attachment_config_thumbnail_type(): void
    {
        $config = new AttachmentConfig(['thumbnailtype' => 'nothumb']);

        $this->assertSame('nothumb', $config->thumbnailType('createthumb'));
    }

    public function test_attachment_config_thumb_dimensions(): void
    {
        $config = new AttachmentConfig([
            'thumbwidth' => 300,
            'thumbheight' => 200,
            'thumbquality' => 90,
        ]);

        $this->assertSame(300, $config->thumbWidth(200));
        $this->assertSame(200, $config->thumbHeight(200));
        $this->assertSame(90, $config->thumbQuality(80));
    }

    public function test_attachment_config_watermark(): void
    {
        $config = new AttachmentConfig([
            'watermarkpos' => 'bottom-right',
            'watermarkwidth' => 150,
            'watermarkheight' => 120,
            'watermarkquality' => 95,
        ]);

        $this->assertSame('bottom-right', $config->watermarkPos('no'));
        $this->assertSame(150, $config->watermarkWidth(100));
        $this->assertSame(120, $config->watermarkHeight(100));
        $this->assertSame(95, $config->watermarkQuality(90));
    }

    public function test_attachment_config_alt_thumb(): void
    {
        $config = new AttachmentConfig([
            'altthumbwidth' => 80,
            'altthumbheight' => 60,
        ]);

        $this->assertSame(80, $config->altThumbWidth(100));
        $this->assertSame(60, $config->altThumbHeight(100));
    }

    public function test_main_config_max_news_num(): void
    {
        $config = new MainConfig(['maxnewsnum' => 10]);

        $this->assertSame(10, $config->maxNewsNum(5));
    }

    public function test_main_config_forum_pagination(): void
    {
        $config = new MainConfig(['postsperpage' => 30, 'topicsperpage' => 40]);

        $this->assertSame(30, $config->forumPostsPerPage(25));
        $this->assertSame(40, $config->forumTopicsPerPage(20));
    }

    public function test_main_config_bitbucket(): void
    {
        $config = new MainConfig(['bitbucket' => 'custom_bucket']);

        $this->assertSame('custom_bucket', $config->bitbucket('bitbucket'));
    }

    public function test_main_config_max_subject_length(): void
    {
        $config = new MainConfig(['maxsubjectlength' => 200]);

        $this->assertSame(200, $config->maxSubjectLength(100));
    }

    public function test_main_config_slogan(): void
    {
        $config = new MainConfig(['SLOGAN' => 'Best tracker']);

        $this->assertSame('Best tracker', $config->slogan());
    }

    public function test_main_config_logo(): void
    {
        $config = new MainConfig(['logo' => 'logo.png']);

        $this->assertSame('logo.png', $config->logo());
    }

    public function test_main_config_site_online(): void
    {
        $config = new MainConfig(['site_online' => 'yes']);

        $this->assertTrue($config->siteOnline(true));
    }

    public function test_main_config_donation(): void
    {
        $config = new MainConfig(['donation' => 'yes']);

        $this->assertTrue($config->donation(false));
    }

    public function test_main_config_icp_license(): void
    {
        $config = new MainConfig(['icplicense' => 'ICP123']);

        $this->assertSame('ICP123', $config->icpLicense());
    }

    public function test_security_config_disable_email_change(): void
    {
        $config = new SecurityConfig(['changeemail' => 'yes']);

        $this->assertTrue($config->disableEmailChange(false));
    }

    public function test_security_config_disable_email_change_default(): void
    {
        $config = new SecurityConfig([]);

        $this->assertFalse($config->disableEmailChange(false));
    }

    public function test_tweak_config_enable_tooltip(): void
    {
        $config = new TweakConfig(['enabletooltip' => 'yes']);

        $this->assertTrue($config->enableTooltip(false));
    }

    public function test_tweak_config_meta_values(): void
    {
        $config = new TweakConfig([
            'titlekeywords' => 'torrents, tracker',
            'metakeywords' => 'bits, lp',
            'metadescription' => 'A private tracker',
        ]);

        $this->assertSame('torrents, tracker', $config->titleKeywords());
        $this->assertSame('bits, lp', $config->metaKeywords());
        $this->assertSame('A private tracker', $config->metaDescription());
    }

    public function test_tweak_config_css_date(): void
    {
        $config = new TweakConfig(['cssdate' => '20260101']);

        $this->assertSame('20260101', $config->cssDate());
    }

    public function test_tweak_config_date_founded(): void
    {
        $config = new TweakConfig(['datefounded' => '2020-01-01']);

        $this->assertSame('2020-01-01', $config->dateFounded());
    }

    public function test_tweak_config_sql_debug(): void
    {
        $config = new TweakConfig(['enablesqldebug' => 'yes', 'sqldebug' => 5]);

        $this->assertTrue($config->enableSqlDebug(false));
        $this->assertSame(5, $config->sqlDebug(0));
    }

    public function test_tweak_config_analytics_code(): void
    {
        $config = new TweakConfig(['analyticscode' => '<script></script>']);

        $this->assertSame('<script></script>', $config->analyticsCode());
    }

    public function test_tweak_config_where(): void
    {
        $config = new TweakConfig(['where' => 'CN']);

        $this->assertSame('CN', $config->where());
    }

    public function test_site_config_has_tweak(): void
    {
        $config = new SiteConfig(['tweak' => ['enabletooltip' => 'yes']]);

        $this->assertTrue($config->tweak->enableTooltip(false));
    }

    public function test_site_config_tweak_empty_by_default(): void
    {
        $config = new SiteConfig([]);

        $this->assertFalse($config->tweak->enableTooltip(false));
    }
}

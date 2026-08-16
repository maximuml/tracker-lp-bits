<?php

namespace Tests\Unit\Support;

use App\Support\PageLayoutContext;
use PHPUnit\Framework\TestCase;

class PageLayoutContextTest extends TestCase
{
    /**
     * @param  array<string, mixed>  $user
     * @param  array<string, mixed>  $userUpdateSet
     */
    private function context(array $user, array &$userUpdateSet = []): PageLayoutContext
    {
        return new PageLayoutContext(
            user: $user,
            lang: ['text_slots' => 'Slots', 'text_unlimited' => 'Unlimited'],
            cache: null,
            defaultStylesheet: 1,
            langDir: 'en',
            siteName: 'Test Tracker',
            slogan: 'Test slogan',
            logoMain: 'logo.png',
            baseUrl: 'https://example.com',
            siteOnline: 'yes',
            enableDonation: 'no',
            titleKeywordsTweak: '',
            metaKeywordsTweak: '',
            metaDescriptionTweak: '',
            cssDateTweak: '',
            deleteNotTransferTwoAccount: 0,
            neverDeleteAccount: 0,
            iniUploadMain: 0,
            dateFounded: '2020-01-01',
            icpLicenseMain: '',
            addKeyShortcut: '',
            queryName: [],
            enableSqlDebugTweak: 'no',
            sqlDebugTweak: 0,
            analyticsCodeTweak: '',
            requestSearch: '',
            requestSearchArea: '0',
            scriptFileName: '/var/www/public/index.php',
            script: 'index',
            enableOffer: 'no',
            customMenu: null,
            maxdlSystem: '',
            whereTweak: 'yes',
            adminClass: 14,
            moderatorClass: 13,
            sysopClass: 15,
            vipClass: 10,
            menuHtml: '',
            menuSelected: '',
            userUpdateSet: $userUpdateSet,
        );
    }

    public function test_user_class_and_stylesheet_helpers(): void
    {
        $context = $this->context(['id' => 7, 'class' => 3, 'stylesheet' => 5]);

        $this->assertTrue($context->isLoggedIn());
        $this->assertSame(3, $context->userClass());
        $this->assertSame(5, $context->userStylesheet());
    }

    public function test_defaults_for_missing_user_values(): void
    {
        $context = $this->context([]);

        $this->assertFalse($context->isLoggedIn());
        $this->assertSame(0, $context->userClass());
        $this->assertSame(1, $context->userStylesheet());
        $this->assertNull($context->userFontSize());
    }

    public function test_user_update_set_is_mutable_reference(): void
    {
        $userUpdateSet = [];
        $context = $this->context(['id' => 1], $userUpdateSet);

        $context->userUpdateSet['last_access'] = '2026-08-02 12:00:00';

        $this->assertSame(['last_access' => '2026-08-02 12:00:00'], $userUpdateSet);
        $this->assertSame(['last_access' => '2026-08-02 12:00:00'], $context->userUpdateSet);
    }
}

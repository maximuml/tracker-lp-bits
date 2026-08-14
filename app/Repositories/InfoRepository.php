<?php

namespace App\Repositories;

use App\Models\Faq;
use App\Models\Language;
use App\Support\Email;
use App\Support\Settings;
use Illuminate\Support\Facades\DB;

final class InfoRepository
{
    /**
     * @return array<string, mixed>
     */
    public static function aboutNexus(): array
    {
        return [
            'languages' => Language::query()
                ->orderBy('trans_state')
                ->get(['flagpic', 'lang_name', 'trans_state'])
                ->toArray(),
            'stylesheets' => DB::table('stylesheets')
                ->orderBy('id')
                ->get(['name', 'designer', 'comment'])
                ->map(fn ($row) => (array) $row)
                ->all(),
            'siteName' => (string) Settings::get('basic.SITENAME', ''),
        ];
    }

    public static function resolveRuleLangId(int $langId): int
    {
        $hasRules = Language::query()->where('id', $langId)->value('rule_lang');

        if (! $hasRules) {
            return 6;
        }

        return $langId;
    }

    /**
     * @return array<int|string, array<string, mixed>>
     */
    public static function faqCategories(int $langId): array
    {
        $faqCategories = [];

        $categories = Faq::query()
            ->where('type', 'categ')
            ->where('lang_id', $langId)
            ->orderBy('order')
            ->get(['question', 'flag', 'link_id']);

        foreach ($categories as $category) {
            $linkId = (string) $category->link_id;
            $faqCategories[$linkId] = [
                'title' => $category->question,
                'flag' => $category->flag,
                'link_id' => $category->link_id,
            ];
        }

        $items = Faq::query()
            ->where('type', 'item')
            ->where('lang_id', $langId)
            ->get(['id', 'question', 'answer', 'flag', 'categ', 'link_id']);

        foreach ($items as $item) {
            $categLinkId = (string) $item->categ;
            $itemId = (int) $item->id;
            $faqCategories[$categLinkId]['items'][$itemId] = [
                'question' => $item->question,
                'answer' => $item->answer,
                'flag' => $item->flag,
                'link_id' => $item->link_id,
            ];
        }

        return $faqCategories;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function rules(int $langId): array
    {
        return DB::table('rules')
            ->where('lang_id', $langId)
            ->orderBy('id')
            ->get(['title', 'text'])
            ->map(fn ($row) => (array) $row)
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    public static function donationPageData(): array
    {
        $enabled = Settings::get('main.donation', 'no') === 'yes';
        $custom = (string) Settings::fromDb('misc.donation_custom', '');
        $paypal = Email::sanitizeForDisplay((string) Settings::get('main.PAYPALACCOUNT', ''));
        $alipay = Email::sanitizeForDisplay((string) Settings::get('main.ALIPAYACCOUNT', ''));
        $showPaypal = $paypal !== '' && Email::isWellFormed($paypal);
        $showAlipay = $alipay !== '' && Email::isWellFormed($alipay);

        $tdAttr = '';
        if ($showPaypal && $showAlipay) {
            $tdAttr = 'width="50%"';
        } elseif ($showPaypal || $showAlipay) {
            $tdAttr = 'colspan="2" width="100%"';
        }

        return [
            'enabled' => $enabled,
            'custom' => $custom,
            'paypal' => $paypal,
            'alipay' => $alipay,
            'showPaypal' => $showPaypal,
            'showAlipay' => $showAlipay,
            'showCustom' => $custom !== '',
            'showAny' => $showPaypal || $showAlipay || $custom !== '',
            'tdAttr' => $tdAttr,
            'accountantId' => (int) Settings::get('main.ACCOUNTANTID', 1),
        ];
    }
}

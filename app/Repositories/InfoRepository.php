<?php

namespace App\Repositories;

use App\Models\Faq;
use App\Models\Language;
use App\Models\User;
use App\Support\Email;
use App\Support\Pagination;
use App\Support\Settings;
use Illuminate\Support\Facades\DB;
use Nexus\Database\NexusDB;

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

    /**
     * @return  array<string, mixed>
     */
    public static function getUserHistoryPosts(int $userId, int $userClass, int $perpage, string $phpSelf): array
    {
        $postcount = (int) NexusDB::table('posts as p')
            ->leftJoin('topics as t', 'p.topicid', '=', 't.id')
            ->leftJoin('forums as f', 't.forumid', '=', 'f.id')
            ->where('p.userid', $userId)
            ->where('f.minclassread', '<=', $userClass)
            ->distinct()
            ->count('p.id');

        [$pagertop, $pagerbottom, , $offset, $perpage] = Pagination::pager($perpage, $postcount, $phpSelf . "?action=viewposts&id=$userId&");

        $posts = NexusDB::table('posts as p')
            ->leftJoin('topics as t', 'p.topicid', '=', 't.id')
            ->leftJoin('forums as f', 't.forumid', '=', 'f.id')
            ->leftJoin('readposts as r', function ($join) {
                $join->on('p.topicid', '=', 'r.topicid')->on('p.userid', '=', 'r.userid');
            })
            ->where('p.userid', $userId)
            ->where('f.minclassread', '<=', $userClass)
            ->orderByDesc('p.id')
            ->offset($offset)
            ->limit($perpage)
            ->get(['f.id AS f_id', 'f.name', 't.id AS t_id', 't.subject', 't.lastpost', 'r.lastpostread', 'p.*'])
            ->map(fn ($row) => (array) $row)
            ->toArray();

        $editorIds = array_filter(array_unique(array_column($posts, 'editedby')));
        $editorNames = ! empty($editorIds)
            ? User::query()->whereIn('id', $editorIds)->pluck('username', 'id')->toArray()
            : [];

        return [
            'postcount' => $postcount,
            'pagertop' => $pagertop,
            'pagerbottom' => $pagerbottom,
            'perpage' => $perpage,
            'posts' => $posts,
            'editorNames' => $editorNames,
        ];
    }

    /**
     * @return  array<string, mixed>
     */
    public static function getUserHistoryComments(int $userId, int $perpage, string $phpSelf): array
    {
        $commentcount = (int) NexusDB::table('comments as c')
            ->leftJoin('torrents as t', 'c.torrent', '=', 't.id')
            ->where('c.user', $userId)
            ->count();

        [$pagertop, $pagerbottom, , $offset, $perpage] = Pagination::pager($perpage, $commentcount, $phpSelf . "?action=viewcomments&id=$userId&");

        $comments = NexusDB::table('comments as c')
            ->leftJoin('torrents as t', 'c.torrent', '=', 't.id')
            ->where('c.user', $userId)
            ->orderByDesc('c.id')
            ->offset($offset)
            ->limit($perpage)
            ->get(['t.name', 'c.torrent AS t_id', 'c.id', 'c.added', 'c.text'])
            ->map(fn ($row) => (array) $row)
            ->toArray();

        $torrentIds = array_filter(array_unique(array_column($comments, 't_id')));
        $countsBefore = [];
        if (! empty($comments)) {
            foreach ($comments as $comment) {
                $countsBefore[$comment['id']] = (int) NexusDB::table('comments')
                    ->where('torrent', $comment['t_id'])
                    ->where('id', '<', $comment['id'])
                    ->count();
            }
        }

        return [
            'commentcount' => $commentcount,
            'pagertop' => $pagertop,
            'pagerbottom' => $pagerbottom,
            'perpage' => $perpage,
            'comments' => $comments,
            'commentPageMap' => $countsBefore,
        ];
    }

    /**
     * @return  array{faqCateg: array<string, mixed>, faqOrphaned: array<string, mixed>}
     */
    public static function faqManageData(): array
    {
        $categRows = NexusDB::table('faq')
            ->leftJoin('language', 'faq.lang_id', '=', 'language.id')
            ->where('faq.type', 'categ')
            ->orderBy('language.lang_name')
            ->orderBy('faq.order')
            ->get(['faq.id', 'faq.link_id', 'faq.lang_id', 'language.lang_name', 'faq.question', 'faq.flag', 'faq.order']);

        $faqCateg = [];
        foreach ($categRows as $row) {
            $arr = (array) $row;
            $faqCateg[$arr['lang_id']][$arr['link_id']] = [
                'title' => $arr['question'],
                'flag' => $arr['flag'],
                'order' => $arr['order'],
                'id' => $arr['id'],
                'lang_name' => $arr['lang_name'],
                'items' => $faqCateg[$arr['lang_id']][$arr['link_id']]['items'] ?? [],
            ];
        }

        $itemRows = NexusDB::table('faq')
            ->where('type', 'item')
            ->orderBy('order')
            ->get(['id', 'question', 'lang_id', 'flag', 'categ', 'order']);

        foreach ($itemRows as $row) {
            $arr = (array) $row;
            $faqCateg[$arr['lang_id']][$arr['categ']]['items'][$arr['id']] = [
                'question' => $arr['question'],
                'flag' => $arr['flag'],
                'order' => $arr['order'],
            ];
        }

        $faqOrphaned = [];
        foreach ($faqCateg as $lang => $temp2) {
            foreach ($temp2 as $id => $temp) {
                if (! array_key_exists('title', $temp)) {
                    foreach ($temp['items'] as $id2 => $tempItem) {
                        $faqOrphaned[$lang][$id2] = $tempItem;
                    }
                    unset($faqCateg[$lang][$id]);
                }
            }
        }

        return [
            'faqCateg' => $faqCateg,
            'faqOrphaned' => $faqOrphaned,
        ];
    }

    /**
     * @param  array<int|string, int>  $order
     */
    public static function reorderFaq(array $order): void
    {
        foreach ($order as $id => $position) {
            NexusDB::table('faq')->where('id', (int) $id)->update(['order' => (int) $position]);
        }
        NexusDB::cache_del('faq');
    }

    /** @param  array<string, mixed>  $data */
    public static function updateFaq(int $id, array $data): void
    {
        NexusDB::table('faq')->where('id', $id)->update($data);
        NexusDB::cache_del('faq');
    }

    public static function deleteFaq(int $id): void
    {
        NexusDB::table('faq')->where('id', $id)->delete();
        NexusDB::cache_del('faq');
    }

    /**
     * @return  array<string, mixed>|null
     */
    public static function getFaqById(int $id): ?array
    {
        $arr = (array) NexusDB::table('faq')->where('id', $id)->first();
        return empty($arr) ? null : $arr;
    }

    /**
     * @return  array<int, array<string, mixed>>
     */
    public static function getFaqCategoriesByLang(int $langId): array
    {
        return NexusDB::table('faq')
            ->where('type', 'categ')
            ->where('lang_id', $langId)
            ->orderBy('order')
            ->get(['id', 'question', 'link_id'])
            ->map(fn ($r) => (array) $r)
            ->all();
    }

    public static function getLanguageName(int $langId): string
    {
        return (string) (NexusDB::table('language')->where('id', $langId)->value('lang_name') ?? '');
    }

    /**
     * @return  array{maxorder: int, maxlinkid: int}
     */
    public static function getFaqMaxOrderAndLinkId(string $type, int $langId): array
    {
        $maxRow = (array) NexusDB::table('faq')
            ->where('type', $type)
            ->where('lang_id', $langId)
            ->selectRaw('MAX(`order`) AS maxorder, MAX(`link_id`) AS maxlinkid')
            ->first();

        return [
            'maxorder' => (int) ($maxRow['maxorder'] ?? 0),
            'maxlinkid' => (int) ($maxRow['maxlinkid'] ?? 0),
        ];
    }

    /** @param  array<string, mixed>  $data */
    public static function insertFaq(array $data): void
    {
        NexusDB::table('faq')->insert($data);
        NexusDB::cache_del('faq');
    }
}

<?php

declare(strict_types=1);

/**
 * @property int $id
 * @property string|null $info_hash
 * @property string $name
 * @property string $filename
 * @property string $save_as
 * @property string $cover
 * @property string $small_descr
 * @property int $category
 * @property int $source
 * @property int $medium
 * @property int $codec
 * @property int $standard
 * @property int $processing
 * @property int $audiocodec
 * @property int $size
 * @property string|null $added
 * @property string $type
 * @property int $numfiles
 * @property int $comments
 * @property int $views
 * @property int $hits
 * @property int $times_completed
 * @property int $leechers
 * @property int $seeders
 * @property string|null $last_action
 * @property bool $visible
 * @property bool $banned
 * @property int $owner
 * @property int $sp_state
 * @property int $promotion_time_type
 * @property string|null $promotion_until
 * @property bool $anonymous
 * @property int|null $url
 * @property string $pos_state
 * @property string|null $pos_state_until
 * @property int $cache_stamp
 * @property string|null $last_reseed
 * @property int $hr
 * @property int $approval_status
 * @property int $price
 * @property string $pieces_hash
 */

namespace App\Models;

use App\Contracts\Repositories\MeiliSearchRepositoryInterface;
use App\Enums\PromotionTimeType;
use App\Enums\TorrentApprovalStatus;
use App\Enums\TorrentHr;
use App\Enums\TorrentNfoViewStyle;
use App\Enums\TorrentPosState;
use App\Enums\TorrentPromotion;
use App\Enums\TorrentType;
use App\Enums\TorrentVisible;
use App\Models\Traits\HasTorrentAccessors;
use App\Models\Traits\HasTorrentRelationships;
use App\Models\Traits\HasTorrentScopes;
use App\Support\Html\SafeHtml;
use App\Support\Locale;
use Illuminate\Container\Container;
use Illuminate\Database\Eloquent\Collection;
use Laravel\Scout\ModelObserver;
use Laravel\Scout\Searchable;
use Laravel\Scout\SearchableScope;

/**
 * @property int $id
 * @property int $category
 * @property int $hr
 * @property string $name
 * @property-read Category $basic_category
 * @property-read User $user
 * @property-read Tag[]|Collection<int, Tag> $tags
 */
class Torrent extends NexusModel
{
    use HasTorrentAccessors, HasTorrentRelationships, HasTorrentScopes, Searchable;

    /** @var list<string> */
    protected $fillable = [
        'name', 'filename', 'save_as',
        'category', 'source', 'medium', 'codec', 'standard', 'processing', 'audiocodec',
        'size', 'added', 'type', 'numfiles', 'owner', 'nfo', 'sp_state', 'promotion_time_type',
        'promotion_until', 'anonymous', 'url', 'pos_state', 'cache_stamp',
        'last_reseed', 'leechers', 'seeders', 'cover', 'last_action', 'info_hash', 'pieces_hash',
        'times_completed', 'approval_status', 'banned', 'visible', 'pos_state_until', 'price',
        'hr',
    ];

    const FILTER_VISIBLE_ALL = '0';

    const FILTER_VISIBLE_YES = '1';

    const FILTER_VISIBLE_NO = '2';

    const BANNED_YES = 1;

    const BANNED_NO = 0;

    /** @var array<string, string> */
    protected $casts = [
        'added' => 'datetime',
        'promotion_until' => 'datetime',
        'pos_state_until' => 'datetime',
        'last_action' => 'datetime',
        'anonymous' => 'boolean',
        'banned' => 'boolean',
        'visible' => 'boolean',
        'type' => TorrentType::class,
    ];

    /** @var list<string> */
    protected $hidden = [
        'info_hash',
    ];

    /** @var list<string> */
    public static $commentFields = [
        'id', 'name', 'added', 'visible', 'banned', 'owner', 'sp_state', 'promotion_time_type', 'promotion_until', 'pos_state',
        'hr', 'last_action', 'leechers', 'seeders', 'times_completed', 'views', 'size', 'cover', 'anonymous',
        'approval_status', 'pos_state_until', 'category', 'source', 'medium', 'codec', 'standard', 'processing', 'audiocodec',
        'price',
    ];

    /** @var array<int|string, mixed> */
    public static $basicRelations = [
        'basic_category', 'basic_audio_codec', 'basic_codec', 'basic_media',
        'basic_source', 'basic_standard', ];

    /** @var array<int|string, mixed> */
    public static $posStates = [
        TorrentPosState::NONE->value => ['text' => 'Normal', 'icon_counts' => 0],
        TorrentPosState::STICKY_SECOND->value => ['text' => 'Sticky second', 'icon_counts' => 1],
        TorrentPosState::STICKY_FIRST->value => ['text' => 'Sticky first', 'icon_counts' => 2],
    ];

    /** @var array<int|string, mixed> */
    public static $hrStatus = [
        TorrentHr::NO->value => ['text' => 'NO'],
        TorrentHr::YES->value => ['text' => 'YES'],
    ];

    /**
     * @deprecated Use App\Enums\TorrentPromotion enum methods (label(), color(), upMultiplier(), downMultiplier()) instead.
     *
     * @var array<int|string, mixed>
     */
    public static array $promotionTypes = [
        TorrentPromotion::NORMAL->value => [
            'text' => 'Normal',
            'up_multiplier' => 1,
            'down_multiplier' => 1,
            'color' => '',
        ],
        TorrentPromotion::FREE->value => [
            'text' => 'Free',
            'up_multiplier' => 1,
            'down_multiplier' => 0,
            'color' => 'linear-gradient(to right, rgba(0,52,206,0.5), rgba(0,52,206,1), rgba(0,52,206,0.5))',
        ],
        TorrentPromotion::TWO_TIMES_UP->value => [
            'text' => '2X',
            'up_multiplier' => 2,
            'down_multiplier' => 1,
            'color' => 'linear-gradient(to right, rgba(0,153,0,0.5), rgba(0,153,0,1), rgba(0,153,0,0.5))',
        ],
        TorrentPromotion::FREE_TWO_TIMES_UP->value => [
            'text' => '2X Free',
            'up_multiplier' => 2,
            'down_multiplier' => 0,
            'color' => 'linear-gradient(to right, rgba(0,153,0,1), rgba(0,52,206,1)',
        ],
        TorrentPromotion::HALF_DOWN->value => [
            'text' => '50%',
            'up_multiplier' => 1,
            'down_multiplier' => 0.5,
            'color' => 'linear-gradient(to right, rgba(220,0,3,0.5), rgba(220,0,3,1), rgba(220,0,3,0.5))',
        ],
        TorrentPromotion::HALF_DOWN_TWO_TIMES_UP->value => [
            'text' => '2X 50%',
            'up_multiplier' => 2,
            'down_multiplier' => 0.5,
            'color' => 'linear-gradient(to right, rgba(0,153,0,1), rgba(220,0,3,1)',
        ],
        TorrentPromotion::ONE_THIRD_DOWN->value => [
            'text' => '30%',
            'up_multiplier' => 1,
            'down_multiplier' => 0.3,
            'color' => 'linear-gradient(to right, rgba(65,23,73,0.5), rgba(65,23,73,1), rgba(65,23,73,0.5))',
        ],
    ];

    /**
     * @deprecated Use App\Enums\PromotionTimeType enum methods (label()) instead.
     *
     * @var array<int|string, mixed>
     */
    public static array $promotionTimeTypes = [
        PromotionTimeType::GLOBAL->value => ['text' => 'Global'],
        PromotionTimeType::PERMANENT->value => ['text' => 'Permanent'],
        PromotionTimeType::DEADLINE->value => ['text' => 'Until'],
    ];

    const BONUS_REWARD_VALUES = [50, 100, 200, 500, 1000];

    /** @var array<int|string, mixed> */
    public static array $approvalStatus = [
        TorrentApprovalStatus::NONE->value => [
            'text' => 'None',
            'badge_color' => 'primary',
        ],
        TorrentApprovalStatus::ALLOW->value => [
            'text' => 'Allow',
            'badge_color' => 'success',
        ],
        TorrentApprovalStatus::DENY->value => [
            'text' => 'Deny',
            'badge_color' => 'danger',
        ],
    ];

    /**
     * Approval-status badge icon, keyed by TorrentApprovalStatus value.
     */
    public static function approvalStatusIcon(int $status): SafeHtml
    {
        $view = match ($status) {
            TorrentApprovalStatus::ALLOW->value => 'torrents._icon-allow',
            TorrentApprovalStatus::DENY->value => 'torrents._icon-deny',
            TorrentApprovalStatus::NONE->value => 'torrents._icon-none',
            default => null,
        };

        return SafeHtml::fromTrustedHtml(
            $view === null ? '' : trim(view($view)->render())
        );
    }

    const REQUIRE_SEED_SECTION_DEFAULT_PROMOTION_STATE = TorrentPromotion::FREE->value;

    const REQUIRE_SEED_SECTION_DEFAULT_BONUS_ADDITION_FACTOR = 0;

    const REQUIRE_SEED_SECTION_DEFAULT_TORRENT_COUNT_MAX = 100;

    const REQUIRE_SEED_SECTION_PROMOTION_STATE_CACHE_KEY = 'REQUIRE_SEED_SECTION_PROMOTION_STATE_CACHE';

    const REQUIRE_SEED_SECTION_TORRENT_ON_LIST_CACHE_KEY = 'REQUIRE_SEED_SECTION_TORRENT_ON_LIST_CACHE';

    const REQUIRE_SEED_SECTION_TORRENT_USER_CACHE_KEY = 'REQUIRE_SEED_SECTION_TORRENT_USER_CACHE';

    /** @var array<int|string, mixed> */
    public static array $nfoViewStyles = [
        TorrentNfoViewStyle::DOS->value => ['text' => 'DOS-vy'],
        TorrentNfoViewStyle::WINDOWS->value => ['text' => 'Windows-vy'],
    ];

    /**
     * @param  mixed  $appendTableName
     * @return list<string>
     */
    public static function getFieldsForList($appendTableName = false): array
    {
        $fields = 'id, sp_state, promotion_time_type, promotion_until, banned, pos_state, category, source, medium, codec, standard, processing, audiocodec, leechers, seeders, name, times_completed, size, added, comments,anonymous,owner,url,cache_stamp, hr, approval_status, cover, price';
        $split = preg_split('/[,\s]+/', $fields);
        $fields = $split === false ? [] : $split;
        if ($appendTableName) {
            foreach ($fields as &$value) {
                $value = 'torrents.'.$value;
            }
        }

        return $fields;
    }

    /**
     * Only sync the MeiliSearch index when MeiliSearch is enabled.
     */
    public function shouldBeSearchable(): bool
    {
        return self::meiliSearchRepository()->isEnabled();
    }

    /** @return  array<int|string, mixed> */
    public function toSearchableArray(): array
    {
        $fields = self::meiliSearchRepository()->getRequiredFields();
        $row = [];
        foreach ($fields as $field) {
            $row[$field] = self::meiliSearchRepository()->formatValueForMeili($field, $this->getAttribute($field));
        }

        return $row;
    }

    /**
     * Override the Scout boot so unit tests that instantiate the model outside
     * the full Laravel application do not fail when the config container is
     * not available.
     */
    public static function bootSearchable(): void
    {
        static::addGlobalScope(new SearchableScope);

        static::whenBooted(function () {
            if (Container::getInstance()->bound('config')) {
                static::observe(new ModelObserver);
            }
            (new self)->registerSearchableMacros();
        });
    }

    /**
     * @param  mixed  $onlyKeyValue
     * @param  mixed  $valueField
     * @return array<int|string, mixed>
     */
    public static function listApprovalStatus($onlyKeyValue = false, $valueField = 'text'): array
    {
        $result = self::$approvalStatus;
        $keyValue = [];
        foreach ($result as $status => &$info) {
            $text = Locale::trans("torrent.approval.status_text.{$status}", [], null);
            $info['text'] = $text;
            $keyValue[$status] = $info[$valueField];
        }
        if ($onlyKeyValue) {
            return $keyValue;
        }

        return $result;
    }

    /**
     * @param  mixed  $onlyKeyValue
     * @param  mixed  $valueField
     * @return array<int|string, mixed>
     */
    public static function listPromotionTypes($onlyKeyValue = false, $valueField = 'text'): array
    {
        $result = self::$promotionTypes;
        $keyValue = [];
        foreach ($result as $status => &$info) {
            $text = $info['text'];
            $info['text'] = $text;
            $keyValue[$status] = $info[$valueField];
        }
        if ($onlyKeyValue) {
            return $keyValue;
        }

        return $result;
    }

    /**
     * @param  mixed  $onlyKeyValue
     * @param  mixed  $valueField
     * @return array<int|string, mixed>
     */
    public static function listPromotionTimeTypes($onlyKeyValue = false, $valueField = 'text'): array
    {
        return self::listStaticProps(self::$promotionTimeTypes, 'torrent.promotion_time_types', $onlyKeyValue, $valueField);
    }

    /**
     * @param  mixed  $onlyKeyValue
     * @param  mixed  $valueField
     * @return array<int|string, mixed>
     */
    public static function listPosStates($onlyKeyValue = false, $valueField = 'text'): array
    {
        $result = self::$posStates;
        $keyValues = [];
        foreach ($result as $key => &$value) {
            $value['text'] = Locale::trans('torrent.pos_state_'.$key, [], null);
            $keyValues[$key] = $value[$valueField];
        }
        if ($onlyKeyValue) {
            return $keyValues;
        }

        return $result;
    }

    /** @return  array<int|string, mixed> */
    public static function getFieldLabels(): array
    {
        $fields = [
            'comments', 'times_completed', 'peers_count', 'thank_users_count', 'numfiles', 'bookmark_yes', 'bookmark_no',
            'reward_yes', 'reward_no', 'reward_logs', 'download', 'thanks_yes', 'thanks_no',
        ];
        $result = [];
        foreach ($fields as $field) {
            $result[$field] = Locale::trans("torrent.show.{$field}_label", [], null);
        }

        return $result;
    }

    /**
     * @param  array<int|string, mixed>  $fields
     * @return mixed
     */
    public function checkIsNormal(array $fields = ['visible', 'banned'])
    {
        if (in_array('visible', $fields) && $this->getAttribute('visible') != TorrentVisible::YES->value) {
            throw new \InvalidArgumentException(sprintf('Torrent: %s is not visible.', $this->id));
        }
        if (in_array('banned', $fields) && $this->getAttribute('banned') == self::BANNED_YES) {
            throw new \InvalidArgumentException(sprintf('Torrent: %s is banned.', $this->id));
        }

        return true;
    }

    /** @param  mixed  $field */
    public function getSubCategoryLabel($field): string
    {
        $category = $this->basic_category;
        if (! $category) {
            return '';
        }
        $searchBox = $category->search_box;
        if (! $searchBox) {
            return '';
        }

        return $searchBox->getTaxonomyLabel($field);
    }

    private static function meiliSearchRepository(): MeiliSearchRepositoryInterface
    {
        return app(MeiliSearchRepositoryInterface::class);
    }
}

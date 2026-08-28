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
 * @property string $visible
 * @property string $banned
 * @property int $owner
 * @property int $sp_state
 * @property int $promotion_time_type
 * @property string|null $promotion_until
 * @property string $anonymous
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

use App\Models\Traits\HasTorrentAccessors;
use App\Models\Traits\HasTorrentRelationships;
use App\Models\Traits\HasTorrentScopes;
use App\Repositories\MeiliSearchRepository;
use App\Support\Locale;
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

    const VISIBLE_YES = 'yes';

    const VISIBLE_NO = 'no';

    const FILTER_VISIBLE_ALL = '0';

    const FILTER_VISIBLE_YES = '1';

    const FILTER_VISIBLE_NO = '2';

    const BANNED_YES = 'yes';

    const BANNED_NO = 'no';

    /** @var array<string, string> */
    protected $casts = [
        'added' => 'datetime',
        'promotion_until' => 'datetime',
        'pos_state_until' => 'datetime',
        'last_action' => 'datetime',
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

    const POS_STATE_STICKY_NONE = 'normal';

    const POS_STATE_STICKY_FIRST = 'sticky';

    /**
     * alphabet 'r' is  after 'n' and before 's', so it will fit: order by pos_state desc,
     * first sticky, then r_sticky, then normal
     */
    const POS_STATE_STICKY_SECOND = 'r_sticky';

    /** @var array<int|string, mixed> */
    public static $posStates = [
        self::POS_STATE_STICKY_NONE => ['text' => 'Normal', 'icon_counts' => 0],
        self::POS_STATE_STICKY_SECOND => ['text' => 'Sticky second', 'icon_counts' => 1],
        self::POS_STATE_STICKY_FIRST => ['text' => 'Sticky first', 'icon_counts' => 2],
    ];

    const HR_YES = 1;

    const HR_NO = 0;

    /** @var array<int|string, mixed> */
    public static $hrStatus = [
        self::HR_NO => ['text' => 'NO'],
        self::HR_YES => ['text' => 'YES'],
    ];

    /** @deprecated Use App\Enums\TorrentPromotion enum instead. */
    const PROMOTION_NORMAL = 1;

    /** @deprecated Use App\Enums\TorrentPromotion enum instead. */
    const PROMOTION_FREE = 2;

    /** @deprecated Use App\Enums\TorrentPromotion enum instead. */
    const PROMOTION_TWO_TIMES_UP = 3;

    /** @deprecated Use App\Enums\TorrentPromotion enum instead. */
    const PROMOTION_FREE_TWO_TIMES_UP = 4;

    /** @deprecated Use App\Enums\TorrentPromotion enum instead. */
    const PROMOTION_HALF_DOWN = 5;

    /** @deprecated Use App\Enums\TorrentPromotion enum instead. */
    const PROMOTION_HALF_DOWN_TWO_TIMES_UP = 6;

    /** @deprecated Use App\Enums\TorrentPromotion enum instead. */
    const PROMOTION_ONE_THIRD_DOWN = 7;

    /**
     * @deprecated Use App\Enums\TorrentPromotion enum methods (label(), color(), upMultiplier(), downMultiplier()) instead.
     *
     * @var array<int|string, mixed>
     */
    public static array $promotionTypes = [
        self::PROMOTION_NORMAL => [
            'text' => 'Normal',
            'up_multiplier' => 1,
            'down_multiplier' => 1,
            'color' => '',
        ],
        self::PROMOTION_FREE => [
            'text' => 'Free',
            'up_multiplier' => 1,
            'down_multiplier' => 0,
            'color' => 'linear-gradient(to right, rgba(0,52,206,0.5), rgba(0,52,206,1), rgba(0,52,206,0.5))',
        ],
        self::PROMOTION_TWO_TIMES_UP => [
            'text' => '2X',
            'up_multiplier' => 2,
            'down_multiplier' => 1,
            'color' => 'linear-gradient(to right, rgba(0,153,0,0.5), rgba(0,153,0,1), rgba(0,153,0,0.5))',
        ],
        self::PROMOTION_FREE_TWO_TIMES_UP => [
            'text' => '2X Free',
            'up_multiplier' => 2,
            'down_multiplier' => 0,
            'color' => 'linear-gradient(to right, rgba(0,153,0,1), rgba(0,52,206,1)',
        ],
        self::PROMOTION_HALF_DOWN => [
            'text' => '50%',
            'up_multiplier' => 1,
            'down_multiplier' => 0.5,
            'color' => 'linear-gradient(to right, rgba(220,0,3,0.5), rgba(220,0,3,1), rgba(220,0,3,0.5))',
        ],
        self::PROMOTION_HALF_DOWN_TWO_TIMES_UP => [
            'text' => '2X 50%',
            'up_multiplier' => 2,
            'down_multiplier' => 0.5,
            'color' => 'linear-gradient(to right, rgba(0,153,0,1), rgba(220,0,3,1)',
        ],
        self::PROMOTION_ONE_THIRD_DOWN => [
            'text' => '30%',
            'up_multiplier' => 1,
            'down_multiplier' => 0.3,
            'color' => 'linear-gradient(to right, rgba(65,23,73,0.5), rgba(65,23,73,1), rgba(65,23,73,0.5))',
        ],
    ];

    /** @deprecated Use App\Enums\PromotionTimeType enum instead. */
    const PROMOTION_TIME_TYPE_GLOBAL = 0;

    /** @deprecated Use App\Enums\PromotionTimeType enum instead. */
    const PROMOTION_TIME_TYPE_PERMANENT = 1;

    /** @deprecated Use App\Enums\PromotionTimeType enum instead. */
    const PROMOTION_TIME_TYPE_DEADLINE = 2;

    /**
     * @deprecated Use App\Enums\PromotionTimeType enum methods (label()) instead.
     *
     * @var array<int|string, mixed>
     */
    public static array $promotionTimeTypes = [
        self::PROMOTION_TIME_TYPE_GLOBAL => ['text' => 'Global'],
        self::PROMOTION_TIME_TYPE_PERMANENT => ['text' => 'Permanent'],
        self::PROMOTION_TIME_TYPE_DEADLINE => ['text' => 'Until'],
    ];

    const BONUS_REWARD_VALUES = [50, 100, 200, 500, 1000];

    const APPROVAL_STATUS_NONE = 0;

    const APPROVAL_STATUS_ALLOW = 1;

    const APPROVAL_STATUS_DENY = 2;

    /** @var array<int|string, mixed> */
    public static array $approvalStatus = [
        self::APPROVAL_STATUS_NONE => [
            'text' => 'None',
            'badge_color' => 'primary',
            'icon' => '<svg t="1655184824967" class="icon" viewBox="0 0 1024 1024" version="1.1" xmlns="http://www.w3.org/2000/svg" p-id="34118" width="16" height="16"><path d="M450.267 772.245l0 92.511 92.511 0 0-92.511L450.267 772.245zM689.448 452.28c13.538-24.367 20.311-50.991 20.311-79.875 0-49.938-19.261-92.516-57.765-127.713-38.517-35.197-90.114-52.8-154.797-52.8-61.077 0-110.191 16.4-147.342 49.188-37.16 32.798-59.497 80.032-67.014 141.703l83.486 9.927c7.218-46.025 22.41-79.875 45.576-101.533 23.166-21.665 52.047-32.494 86.647-32.494 35.802 0 66.038 11.957 90.711 35.874 24.667 23.92 37.01 51.675 37.01 83.266 0 17.451-4.222 33.55-12.642 48.284-8.425 14.747-26.698 34.526-54.83 59.346s-47.607 43.701-58.442 56.637c-14.741 17.754-25.424 35.354-32.037 52.797-9.028 23.172-13.537 50.701-13.537 82.584 0 5.418 0.146 13.539 0.45 24.374l78.069 0c0.599-32.495 2.855-55.966 6.772-70.4 3.903-14.44 9.926-27.229 18.047-38.363 8.127-11.123 25.425-28.43 51.901-51.895C649.43 506.288 675.908 476.656 689.448 452.28L689.448 452.28z" p-id="34119" fill="#e78d0f"></path></svg>',
        ],
        self::APPROVAL_STATUS_ALLOW => [
            'text' => 'Allow',
            'badge_color' => 'success',
            'icon' => '<svg t="1655145688503" class="icon" viewBox="0 0 1413 1024" version="1.1" xmlns="http://www.w3.org/2000/svg" p-id="16225" width="16" height="16"><path d="M1381.807797 107.47394L1274.675044 0.438669 465.281736 809.880718l-322.665524-322.714266L35.434718 594.152982l430.041982 430.041982 107.084012-107.035271-0.243705-0.292446z" fill="#1afa29" p-id="16226"></path></svg>',
        ],
        self::APPROVAL_STATUS_DENY => [
            'text' => 'Deny',
            'badge_color' => 'danger',
            'icon' => '<svg t="1655184952662" class="icon" viewBox="0 0 1024 1024" version="1.1" xmlns="http://www.w3.org/2000/svg" p-id="35029" width="16" height="16"><path d="M220.8 812.8l22.4 22.4 272-272 272 272 48-44.8-275.2-272 275.2-272-48-48-272 275.2-272-275.2-22.4 25.6-22.4 22.4 272 272-272 272z" fill="#d81e06" p-id="35030"></path></svg>',
        ],
    ];

    const NFO_VIEW_STYLE_DOS = 'magic';

    const NFO_VIEW_STYLE_WINDOWS = 'latin-1';

    const REQUIRE_SEED_SECTION_DEFAULT_PROMOTION_STATE = self::PROMOTION_FREE;

    const REQUIRE_SEED_SECTION_DEFAULT_BONUS_ADDITION_FACTOR = 0;

    const REQUIRE_SEED_SECTION_DEFAULT_TORRENT_COUNT_MAX = 100;

    const REQUIRE_SEED_SECTION_PROMOTION_STATE_CACHE_KEY = 'REQUIRE_SEED_SECTION_PROMOTION_STATE_CACHE';

    const REQUIRE_SEED_SECTION_TORRENT_ON_LIST_CACHE_KEY = 'REQUIRE_SEED_SECTION_TORRENT_ON_LIST_CACHE';

    const REQUIRE_SEED_SECTION_TORRENT_USER_CACHE_KEY = 'REQUIRE_SEED_SECTION_TORRENT_USER_CACHE';

    /** @var array<int|string, mixed> */
    public static array $nfoViewStyles = [
        self::NFO_VIEW_STYLE_DOS => ['text' => 'DOS-vy'],
        self::NFO_VIEW_STYLE_WINDOWS => ['text' => 'Windows-vy'],
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
        return MeiliSearchRepository::isEnabled();
    }

    /** @return  array<int|string, mixed> */
    public function toSearchableArray(): array
    {
        $fields = MeiliSearchRepository::getRequiredFields();
        $row = [];
        foreach ($fields as $field) {
            $row[$field] = MeiliSearchRepository::formatValueForMeili($field, $this->getAttribute($field));
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
            if (app()->bound('config')) {
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
        if (in_array('visible', $fields) && $this->getAttribute('visible') != self::VISIBLE_YES) {
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
}

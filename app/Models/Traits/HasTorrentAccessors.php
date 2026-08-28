<?php

declare(strict_types=1);

namespace App\Models\Traits;

use App\Enums\HitAndRunMode;
use App\Enums\TorrentPromotion;
use App\Models\HitAndRun;
use App\Models\Torrent;
use App\Support\Locale;
use App\Support\Logger;
use App\Support\Promotion;
use App\Support\Time;
use Illuminate\Database\Eloquent\Casts\Attribute;

/**
 * Eloquent attribute accessors / mutators for the Torrent model.
 */
trait HasTorrentAccessors
{
    /**
     * 重写获取 info_hash 的方法，确保从数据库读出时是正确的格式
     * 注意：不要使用 getInfoHashAttribute()，不带缓存，第1次有值，第2次指针到头，数据是空！！！
     *
     * @return Attribute<mixed, mixed>
     */
    public function infoHash(): Attribute
    {
        return Attribute::make(
            get: function ($value) {
                // PostgreSQL 返回 bytea 时可能是十六进制流或资源
                if (is_resource($value)) {
                    rewind($value);

                    return stream_get_contents($value);
                }

                return $value;
            }
        )->shouldCache();
    }

    /** @return mixed */
    public function getPromotionInfoAttribute()
    {
        return self::$promotionTypes[$this->sp_state_real] ?? null;
    }

    /** @return mixed */
    public function getSpStateRealTextAttribute()
    {
        $spStateReal = $this->sp_state_real;

        return self::$promotionTypes[$spStateReal]['text'] ?? '';
    }

    /**
     * Effective promotion state, considering global special state and validity.
     *
     * Uses TorrentPromotion enum for validation while still returning the legacy
     * integer code so callers can keep indexing $promotionTypes.
     */
    public function getSpStateRealAttribute(): int
    {
        if ($this->getRawOriginal('sp_state') === null) {
            throw new \RuntimeException('no select sp_state field');
        }
        $spState = (int) $this->sp_state;
        $global = (int) Promotion::globalSpecialState();
        $log = sprintf('torrent: %s sp_state: %s, global sp state: %s', $this->id, $spState, $global);

        $resolved = TorrentPromotion::fromIntSafe($spState);
        $globalResolved = TorrentPromotion::fromIntSafe($global);

        if ($globalResolved !== TorrentPromotion::NORMAL) {
            $spState = $globalResolved->value;
            $resolved = $globalResolved;
            $log .= sprintf(', global != %s, set sp_state to global: %s', self::PROMOTION_NORMAL, $global);
        }

        // fromIntSafe guarantees a valid enum, but keep the $promotionTypes guard
        // for backwards compatibility with any code that still inspects that array.
        if (! isset(self::$promotionTypes[$spState])) {
            $log .= ", but now sp_state: $spState, is invalid, reset to: ".self::PROMOTION_NORMAL;
            $spState = self::PROMOTION_NORMAL;
        }

        Logger::writeWithContext((string) $log, (string) 'debug', (bool) false);

        return $spState;
    }

    /** @return mixed */
    protected function getPosStateTextAttribute()
    {
        $text = Locale::trans('torrent.pos_state_'.$this->pos_state, [], null);
        if ($this->pos_state != Torrent::POS_STATE_STICKY_NONE) {
            if ($this->pos_state_until) {
                $append = Time::formatDateTime($this->pos_state_until);
            } else {
                $append = Locale::trans('label.permanent', [], null);
            }
            $text .= "($append)";
        }

        return $text;
    }

    /** @return Attribute<mixed, mixed> */
    protected function approvalStatusText(): Attribute
    {
        return new Attribute(
            get: fn ($value, $attributes) => Locale::trans('torrent.approval.status_text.'.$attributes['approval_status'], [], null)
        );
    }

    /** @return Attribute<mixed, mixed> */
    protected function spStateText(): Attribute
    {
        return new Attribute(
            get: fn ($value, $attributes) => self::$promotionTypes[$this->sp_state]['text'] ?? ''
        );
    }

    public function getHrRealAttribute(): int
    {
        $searchBoxId = $this->basic_category->mode ?? 0;
        if ($searchBoxId == 0) {
            Logger::writeWithContext((string) sprintf('[INVALID_CATEGORY], Torrent: %s, category: %s invalid', $this->id, $this->category), (string) 'error', (bool) false);

            return self::HR_NO;
        }
        $hrMode = HitAndRunMode::fromStringSafe(
            is_string($mode = HitAndRun::getConfig('mode', $searchBoxId)) ? $mode : null
        );
        if ($hrMode === HitAndRunMode::GLOBAL) {
            return self::HR_YES;
        }
        if ($hrMode === HitAndRunMode::DISABLED) {
            return self::HR_NO;
        }

        return (int) $this->getRawOriginal('hr');
    }

    /** @return mixed */
    public function getHrTextAttribute()
    {
        return self::$hrStatus[$this->hr] ?? '';
    }

    public function getTagsFormattedAttribute(): string
    {
        $html = [];
        foreach ($this->tags as $tag) {
            $html[] = sprintf(
                '<span style="color: %s;background-color: %s;border-radius: %s;font-size: %s;padding: %s;margin: %s">%s</span>',
                $tag->font_color, $tag->color, $tag->border_radius, $tag->font_size, $tag->padding, $tag->margin, $tag->name
            );
        }

        return implode('', $html);
    }
}

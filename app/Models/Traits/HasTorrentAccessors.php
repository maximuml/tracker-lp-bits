<?php

declare(strict_types=1);

namespace App\Models\Traits;

use App\Enums\TorrentPosState;
use App\Enums\TorrentPromotion;
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
        return Torrent::promotionTypes()[$this->sp_state_real] ?? null;
    }

    /**
     * Effective promotion state, considering global special state and validity.
     *
     * Uses TorrentPromotion enum for validation while still returning the legacy
     * integer code so callers can keep indexing the promotion table.
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
            $log .= sprintf(', global != %s, set sp_state to global: %s', TorrentPromotion::NORMAL->value, $global);
        }

        // fromIntSafe guarantees a valid enum, but keep the guard for
        // backwards compatibility with any code that still inspects
        // the promotion table shape.
        if (! isset(Torrent::promotionTypes()[$spState])) {
            $log .= ", but now sp_state: $spState, is invalid, reset to: ".TorrentPromotion::NORMAL->value;
            $spState = TorrentPromotion::NORMAL->value;
        }

        Logger::writeWithContext((string) $log, (string) 'debug', (bool) false);

        return $spState;
    }

    /** @return mixed */
    protected function getPosStateTextAttribute()
    {
        $text = Locale::trans('torrent.pos_state_'.$this->pos_state, [], null);
        if ($this->pos_state != TorrentPosState::NONE->value) {
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
            get: fn ($value, $attributes) => Torrent::promotionTypes()[$this->sp_state]['text'] ?? ''
        );
    }

    public function getTagsFormattedAttribute(): string
    {
        $html = [];
        foreach ($this->tags as $tag) {
            $html[] = trim(view('components.filament.color-badge', [
                'fontColor' => $tag->font_color,
                'color' => $tag->color,
                'radius' => $tag->border_radius,
                'size' => $tag->font_size,
                'pad' => $tag->padding,
                'margin' => $tag->margin,
                'text' => $tag->name,
            ])->render());
        }

        return implode('', $html);
    }
}

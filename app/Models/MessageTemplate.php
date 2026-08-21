<?php

/**
 * @property int $id
 * @property string $name
 * @property int $language_id
 * @property string $content
 * @property string|null $created_at
 * @property string|null $updated_at
 */

namespace App\Models;

use App\Enums\MessageTemplateNameEnum;
use App\Models\Traits\NexusActivityLogTrait;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MessageTemplate extends NexusModel
{
    use NexusActivityLogTrait;

    /** @var list<string> */
    protected $fillable = ['name', 'content', 'language_id'];

    /** @var bool */
    public $timestamps = true;

    /** @var array<string, string> */
    protected $casts = [
        'name' => MessageTemplateNameEnum::class,
    ];

    /** @return  array<int|string, mixed> */
    public static function listAllNames(): array
    {
        $result = [];
        foreach (MessageTemplateNameEnum::cases() as $messageTemplate) {
            $result[$messageTemplate->value] = $messageTemplate->label();
        }

        return $result;
    }

    /** @return  BelongsTo<Language, $this> */
    public function language(): BelongsTo
    {
        return $this->belongsTo(Language::class);
    }

    /**
     * @param  mixed  $languageId
     * @param  array<int|string, mixed>  $placeholders
     */
    public static function forRegisterWelcome($languageId, array $placeholders): ?string
    {
        $result = self::query()->where('language_id', $languageId)
            ->where('name', MessageTemplateNameEnum::REGISTER_WELCOME->value)
            ->first();

        return self::format($result, $placeholders);
    }

    /**
     * @param  array<int|string, mixed>  $placeholders
     */
    private static function format(?self $template, array $placeholders): ?string
    {
        if ($template && $template->content) {
            $search = array_map(function ($value) {
                return ":$value";
            }, array_keys($placeholders));

            return str_replace($search, array_values($placeholders), $template->content);
        }

        return null;
    }
}

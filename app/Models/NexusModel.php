<?php

namespace App\Models;

use App\Support\Locale;
use App\Support\UserDisplay;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Config;

/**
 * @property int $id
 */
class NexusModel extends Model
{
    /** @use HasFactory<Factory> */
    use HasFactory;

    /** @var bool */
    public $timestamps = false;

    /** @var int */
    protected $perPage = 50;

    /** @return  string */
    public function getConnectionName()
    {
        return Config::get('nexus.database.default', null);
    }

    /** @return  Attribute<mixed, mixed> */
    protected function usernameForAdmin(): Attribute
    {
        return Attribute::make(
            get: fn (mixed $value, array $attributes) => UserDisplay::adminUsername($attributes['uid'] ?? $attributes['userid'] ?? $attributes['user_id'])
        );
    }

    /**
     * @return string
     */
    protected function serializeDate(\DateTimeInterface $date)
    {
        return $date->format($this->dateFormat ?: 'Y-m-d H:i:s');
    }

    /**
     * Check is valid date string
     *
     * @param  mixed  $name
     * @param  mixed  $format
     *
     * @see https://stackoverflow.com/questions/19271381/correctly-determine-if-date-string-is-a-valid-date-in-that-format
     */
    public function isValidDate($name, $format = 'Y-m-d H:i:s'): bool
    {
        $date = $this->getRawOriginal($name);
        $d = \DateTime::createFromFormat($format, $date);

        // The Y ( 4 digits year ) returns TRUE for any integer with any number of digits so changing the comparison from == to === fixes the issue.
        return $d && $d->format($format) === $date;
    }

    /**
     * @param  mixed  $field
     * @return mixed
     */
    public function getDeadlineText($field = 'deadline')
    {
        $raw = $this->getRawOriginal($field);
        if (in_array($raw, [null, '0000-00-00 00:00:00', ''], true)) {
            return Locale::trans('label.permanent', [], null);
        }

        return sprintf('%s: %s', Locale::trans('label.deadline', [], null), $raw);
    }

    /**
     * @param  mixed  $dataSource
     * @param  mixed  $textTransPrefix
     * @param  mixed  $onlyKeyValue
     * @param  mixed  $valueField
     * @return array<int|string, mixed>
     */
    public static function listStaticProps($dataSource, $textTransPrefix, $onlyKeyValue = false, $valueField = 'text'): array
    {
        $result = $dataSource;
        $keyValue = [];
        foreach ($result as $key => &$info) {
            if (str_contains($textTransPrefix, '%s')) {
                $transKey = sprintf($textTransPrefix, $key);
            } else {
                $transKey = "$textTransPrefix.$key";
            }
            $text = $textTransPrefix ? Locale::trans($transKey, [], null) : $info['text'];
            $info['text'] = $text;
            $keyValue[$key] = $info[$valueField];
        }
        if ($onlyKeyValue) {
            return $keyValue;
        }

        return $result;
    }
}

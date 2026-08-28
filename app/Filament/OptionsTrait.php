<?php

declare(strict_types=1);

namespace App\Filament;

trait OptionsTrait
{
    /** @var array<string, string> */
    private static array $matchTypes = ['dec' => 'dec', 'hex' => 'hex'];

    /** @var array<string, string> */
    private static array $yesOrNo = ['yes' => 'yes', 'no' => 'no'];

    /**
     * @return array<int|string, string>
     */
    private static function getEnableDisableOptions(int|string $enableValue = 0, int|string $disableValue = 1): array
    {
        return [
            $enableValue => __('label.enabled'),
            $disableValue => __('label.disabled'),
        ];
    }

    /**
     * @return array<int|string, string>
     */
    private static function getYesNoOptions(int|string $yesValue = 1, int|string $noValue = 0): array
    {
        return [
            $yesValue => 'Yes',
            $noValue => 'No',
        ];
    }
}

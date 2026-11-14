<?php

declare(strict_types=1);

namespace App\ViewModels;

/**
 * ViewModel for the self-enable page (self-enable/index.blade.php).
 *
 * Assembled by UserAdminController::selfEnable()/selfEnableSubmit() via fromPageData(); dead keys (bonus, elapsedDay, total) dropped.
 */
final class SelfEnablePageViewModel extends ViewModel
{
    /**
     * @param  array<string, mixed>  $t
     */
    public function __construct(
        public readonly string $title,
        public readonly float $unit,
        public readonly bool $enabled,
        public readonly ?object $latestBanLog,
        public readonly bool $isUserBonusEnough,
        public readonly string $insufficientMessage,
        public readonly array $t,
        public readonly bool $showError = false,
    ) {}

    /**
     * Build from the controller's $viewData array, keeping only the keys the view reads.
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromPageData(array $data): self
    {
        return new self(
            title: (string) ($data['title'] ?? ''),
            unit: (float) ($data['unit'] ?? 0),
            enabled: (bool) ($data['enabled'] ?? false),
            latestBanLog: is_object($data['latestBanLog'] ?? null) ? $data['latestBanLog'] : null,
            isUserBonusEnough: (bool) ($data['isUserBonusEnough'] ?? false),
            insufficientMessage: (string) ($data['insufficientMessage'] ?? ''),
            t: (array) ($data['t'] ?? []),
            showError: (bool) ($data['showError'] ?? false),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return get_object_vars($this);
    }
}

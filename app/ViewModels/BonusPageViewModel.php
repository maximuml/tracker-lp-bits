<?php

declare(strict_types=1);

namespace App\ViewModels;

/**
 * ViewModel for the bonus (karma) page.
 *
 * Returned by BonusPageService::build().
 */
final class BonusPageViewModel extends ViewModel
{
    /**
     * @param  array<string, mixed>  $lang
     * @param  array<string, mixed>  $curUser
     * @param  array<int, array<string, mixed>>  $allBonus
     */
    public function __construct(
        public readonly array $lang,
        public readonly array $curUser,
        public readonly int $userId,
        public readonly string $action,
        public readonly string $do,
        public readonly string $msg,
        public readonly string $bonus,
        public readonly string $lockText,
        public readonly array $allBonus,
        public readonly string $shopHtml,
        public readonly string $infoHtml,
        public readonly string $sitename,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'lang' => $this->lang,
            'curUser' => $this->curUser,
            'userId' => $this->userId,
            'action' => $this->action,
            'do' => $this->do,
            'msg' => $this->msg,
            'bonus' => $this->bonus,
            'lockText' => $this->lockText,
            'allBonus' => $this->allBonus,
            'shopHtml' => $this->shopHtml,
            'infoHtml' => $this->infoHtml,
            'sitename' => $this->sitename,
        ];
    }
}

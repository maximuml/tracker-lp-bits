<?php

declare(strict_types=1);

namespace App\Services\Bonus;

use App\DTOs\Auth\ActorContext;
use App\Enums\BusinessType;
use App\Services\Bonus\Exchanges\MybonusRedirect;
use App\Services\BonusLedgerService;
use App\Support\Logger;
use App\Support\PageResponses;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;

/**
 * Bonus-for-traffic exchanges: converting points into uploaded/downloaded
 * GB. Both are a ledger debit plus an atomic column increment on the user
 * row.
 */
final class TrafficExchangeService
{
    public function __construct(private readonly BonusLedgerService $ledger) {}

    /**
     * @param  array<string, mixed>  $bonusarray
     */
    public function exchangeUpload(ActorContext $actor, array $bonusarray, float $points, string $baseUrl, float $ratiolimitBonus, int $dlamountlimitBonus): RedirectResponse
    {
        $uploaded = (float) $actor->value('uploaded', 0);
        $downloaded = (float) $actor->value('downloaded', 0);
        if ($uploaded > $dlamountlimitBonus * 1073741824) {
            $ratio = $downloaded > 0
                ? $uploaded / $downloaded
                : PHP_INT_MAX;
        } else {
            $ratio = 0;
        }
        if ($ratiolimitBonus > 0 && $ratio > $ratiolimitBonus) {
            PageResponses::abort(__('mybonus.text_error'), __('mybonus.text_cheat_alert'), true, false);
        }
        $up = (int) $uploaded + (int) $bonusarray['menge'];
        Logger::writeWithContext(sprintf('user: %s going to use %s bonus to exchange uploaded from %s to %s', $actor->id, $points, $uploaded, $up), 'info', false);
        $this->ledger->debit($actor->id, $points, BusinessType::EXCHANGE_UPLOAD, $points.' Points for uploaded.', [
            'uploaded' => DB::raw(DB::getQueryGrammar()->wrap('uploaded').' + '.(int) $bonusarray['menge']), // @phpstan-ignore argument.type
        ]);

        return MybonusRedirect::to($baseUrl, 'upload');
    }

    /**
     * @param  array<string, mixed>  $bonusarray
     */
    public function exchangeDownload(ActorContext $actor, array $bonusarray, float $points, string $baseUrl): RedirectResponse
    {
        $down = (int) $actor->value('downloaded', 0) + (int) $bonusarray['menge'];
        Logger::writeWithContext(sprintf('user: %s going to use %s bonus to exchange downloaded from %s to %s', $actor->id, $points, $actor->value('downloaded', 0), $down), 'info', false);
        $this->ledger->debit($actor->id, $points, BusinessType::EXCHANGE_DOWNLOAD, $points.' Points for downloaded.', [
            'downloaded' => DB::raw(DB::getQueryGrammar()->wrap('downloaded').' + '.(int) $bonusarray['menge']), // @phpstan-ignore argument.type
        ]);

        return MybonusRedirect::to($baseUrl, 'download');
    }
}

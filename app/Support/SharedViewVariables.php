<?php

declare(strict_types=1);

namespace App\Support;

use App\Contracts\Repositories\SearchBoxRepositoryInterface;
use App\DTOs\Auth\ActorContext;
use App\Support\Config\SiteConfig;
use Illuminate\Database\QueryException;

/**
 * The legacy variables still shared with every Blade view ($SITENAME,
 * $BASEURL, $CURUSER, …), resolved from typed config and the current user.
 */
final class SharedViewVariables
{
    public function __construct(
        private readonly SearchBoxRepositoryInterface $searchBoxRepository,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function all(): array
    {
        return PageState::instance()->viewSettings($this->settings(...)) + [
            'CURUSER' => ActorContext::current()->user?->toLegacyArray(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function settings(): array
    {
        try {
            return $this->resolveSettings();
        } catch (QueryException) {
            return [];
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function resolveSettings(): array
    {
        $config = SiteConfig::current();
        $main = $config->main;
        $browseCat = $main->browseCat(0);
        if ($browseCat === 0) {
            $browseCat = $this->searchBoxRepository->getOrderedIds()[0] ?? 1;
        }

        return [
            'SITENAME' => $config->basic->siteName(),
            'BASEURL' => $config->basic->baseUrl() ?: (string) RequestValues::serverValue('HTTP_HOST', 'localhost'),
            'SLOGAN' => $main->slogan(),
            'altname_main' => $main->altName(),
            'browsecatmode' => $browseCat,
            'deflang' => $main->defaultLang(),
            'max_torrent_size' => $main->maxTorrentSize(),
            'where_tweak' => $config->tweak->where(),
        ];
    }
}

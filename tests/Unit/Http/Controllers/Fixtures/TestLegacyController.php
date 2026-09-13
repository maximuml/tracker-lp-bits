<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Controllers\Fixtures;

use App\Http\Controllers\LegacyController;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

class TestLegacyController extends LegacyController
{
    /** @param  array<string, mixed>  $data */
    public function page(Request $request, string $page, bool $auth = true, array $data = []): View|RedirectResponse
    {
        return $this->legacyPage($request, $page, $auth, $data);
    }

    /** @param  array<string, mixed>  $data */
    public function pageWithRedirect(Request $request, string $page, bool $auth = true, array $data = []): Response|RedirectResponse
    {
        return $this->legacyPageWithRedirect($request, $page, $auth, $data);
    }

    /** @param  array<string, mixed>  $data */
    public function pageRaw(Request $request, string $page, bool $auth = true, array $data = []): Response|RedirectResponse
    {
        return $this->legacyPageRaw($request, $page, $auth, $data);
    }

    public function abortPage(string $heading, string $text): Response
    {
        return $this->legacyAbortResponse($heading, $text);
    }
}

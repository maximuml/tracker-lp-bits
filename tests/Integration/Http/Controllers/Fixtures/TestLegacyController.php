<?php

declare(strict_types=1);

namespace Tests\Integration\Http\Controllers\Fixtures;

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
        return $this->renderPage($request, $page, $auth, $data);
    }

    public function abortPage(string $heading, string $text): Response
    {
        return $this->abortResponse($heading, $text);
    }
}

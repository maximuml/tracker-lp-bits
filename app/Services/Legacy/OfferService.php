<?php

declare(strict_types=1);

namespace App\Services\Legacy;

use App\Repositories\OfferRepository;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use App\Support\SupportContext;

/**
 * Temporary bridge for the legacy offers page.
 */
final class OfferService
{
    private OfferRepository $repository;

    public function __construct(OfferRepository $repository)
    {
        $this->repository = $repository;
    }

    /**
     * @return array<string, mixed>|RedirectResponse
     */
    public function legacy(Request $request): array|RedirectResponse
    {
        $result = $this->renderOffers();
        if ($result instanceof RedirectResponse) {
            return $result;
        }

        return ['content' => $result->getContent()];
    }

    private function renderOffers(): Response|RedirectResponse
    {
        $path = __DIR__ . '/offers_content.php';

        if (! file_exists($path)) {
            return response('Legacy content missing: offers', 500);
        }

        ob_start();
        try {
            extract(SupportContext::getGlobalsForView());
            include $path;
        } catch (HttpResponseException $e) {
            ob_get_clean();

            throw $e;
        }

        $content = (string) ob_get_clean();

        foreach (headers_list() as $header) {
            if (stripos($header, 'Location:') === 0) {
                $url = trim(substr($header, 9));
                header_remove('Location');

                return redirect($url);
            }
        }

        return response($content);
    }
}

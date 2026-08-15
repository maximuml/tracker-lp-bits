<?php

declare(strict_types=1);

namespace App\Services\Legacy;

use App\Repositories\MessageRepository;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Temporary bridge for legacy message pages.
 *
 * The procedural partials live in `app/Services/Legacy/partials/` and are
 * included inside output buffering. Direct `header('Location: ...')` calls are
 * translated to Laravel `RedirectResponse` and `LegacyResponse::abort()` throws
 * `HttpResponseException`, which we re-throw.
 */
final class MessageService
{
    private MessageRepository $repository;

    public function __construct(MessageRepository $repository)
    {
        $this->repository = $repository;
    }

    /**
     * @return array<string, mixed>|RedirectResponse
     */
    public function messages(Request $request): array|RedirectResponse
    {
        $result = $this->renderMessages();
        if ($result instanceof RedirectResponse) {
            return $result;
        }

        return ['content' => $result->getContent()];
    }

    public function sendmessage(Request $request): Response|RedirectResponse
    {
        return $this->renderPartial('sendmessage');
    }

    public function takeMessage(Request $request): Response|RedirectResponse
    {
        return $this->renderPartial('takemessage');
    }

    public function deletemessage(Request $request): Response|RedirectResponse
    {
        return $this->renderPartial('deletemessage');
    }

    private function renderMessages(): Response|RedirectResponse
    {
        $path = __DIR__ . '/messages_content.php';

        if (! file_exists($path)) {
            return response('Legacy content missing: messages', 500);
        }

        ob_start();
        try {
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

    private function renderPartial(string $name): Response|RedirectResponse
    {
        $path = __DIR__ . '/partials/' . $name . '.php';

        if (! file_exists($path)) {
            return response('Legacy partial missing: ' . $name, 500);
        }

        ob_start();
        try {
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

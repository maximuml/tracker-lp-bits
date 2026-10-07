<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Contracts\Repositories\AttachmentRepositoryInterface;
use App\Enums\UserFontsize;
use App\Enums\UserTheme;
use App\Http\Requests\AttachmentUploadRequest;
use App\Services\AttachmentMutationService;
use App\Support\Attachment\AttachmentService;
use App\Support\Cache\NexusCache;
use App\Support\Config\SiteConfig;
use App\Support\CurrentUser;
use App\Support\Html\SafeHtml;
use App\Support\Style;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Http\UploadedFile;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AttachmentController extends LegacyController
{
    public function __construct(
        private readonly AttachmentRepositoryInterface $attachmentRepository,
        private readonly CurrentUser $currentUser,
        private readonly ?NexusCache $cache,
    ) {}

    public function attachment(Request $request): Response
    {
        $currentUser = $this->currentUser->get() ?? [];
        $Attach = new AttachmentService((int) ($this->currentUser->id()));

        return $this->renderAttachment($request, $currentUser, $Attach);
    }

    public function attachmentStore(Request $request): RedirectResponse
    {
        // Renamed endpoint — 308 replays the body + query string unchanged.
        $qs = $request->getQueryString();
        $suffix = $qs !== null && $qs !== '' ? '?'.$qs : '';

        return redirect()->to('/web/attachments/upload'.$suffix, 308);
    }

    public function attachmentUpload(AttachmentUploadRequest $request, AttachmentMutationService $attachmentMutationService): Response
    {
        $currentUser = $this->currentUser->get() ?? [];
        $Attach = new AttachmentService((int) ($this->currentUser->id()));

        $warning = '';
        $script = '';
        $countLeft = null;

        if ($Attach->enable_attachment()) {
            $uploaded = $request->file('file');
            if ($uploaded instanceof UploadedFile) {
                $uploaded = [$uploaded];
            }
            $uploaded = is_array($uploaded) ? $uploaded : [];

            $altsize = (string) $request->input('altsize', '');
            $callbackFunc = (string) $request->input('callback_func', '');

            $warnings = [];
            foreach ($uploaded as $item) {
                if (! $item instanceof UploadedFile) {
                    continue;
                }
                $file = [
                    'tmp_name' => $item->getPathname(),
                    'size' => $item->getSize(),
                    'type' => $item->getMimeType(),
                    'name' => $item->getClientOriginalName(),
                ];
                $result = $attachmentMutationService->processUpload($currentUser, $Attach, $altsize, $callbackFunc, $file);
                if (($result['warning'] ?? '') !== '') {
                    $warnings[] = (string) $result['warning'];
                }
                $script .= (string) ($result['script'] ?? '');
                $countLeft = isset($result['count_left']) ? (int) $result['count_left'] : $countLeft;
            }
            if ($uploaded === []) {
                $warnings[] = (string) __('attachment.text_nothing_received');
            }
            $warning = implode(' ', $warnings);
        }

        return $this->renderAttachment($request, $currentUser, $Attach, $warning, $script, $countLeft);
    }

    /**
     * @param  array<string, mixed>  $currentUser
     */
    private function renderAttachment(Request $request, array $currentUser, AttachmentService $Attach, string $warning = '', string $script = '', ?int $countLeft = null): Response
    {
        $allowedextsblock = rtrim(implode('/', $Attach->get_allowed_ext()), '/');
        if ($allowedextsblock === '') {
            $allowedextsblock = 'N/A';
        }

        $cspNonce = (string) $request->attributes->get('csp_nonce', '');
        if ($script !== '' && $cspNonce !== '') {
            $script = (string) preg_replace('/<script(?![^>]*\snonce=)/i', '<script nonce="'.htmlspecialchars($cspNonce, ENT_QUOTES).'"', $script);
        }

        $content = view('attachment.index', [
            'CURUSER' => $currentUser,
            'Attach' => $Attach,
            'enableAttachment' => $Attach->enable_attachment(),
            'count_limit' => (int) $Attach->get_count_limit(),
            'count_left' => $countLeft ?? $Attach->get_count_left(),
            'size_limit' => $Attach->get_size_limit_byte(),
            'allowedextsblock' => $allowedextsblock,
            'css_uri' => Style::cssUriWithContext(),
            'altsize' => (string) $request->input('altsize', ''),
            'callback_func' => (string) $request->input('callback_func', ''),
            'warning' => $warning,
            'script' => SafeHtml::fromTrustedHtml($script),
            'theme' => UserTheme::fromStringSafe(is_string($this->currentUser->value('theme', null)) ? $this->currentUser->value('theme') : null)->value,
            'fontSize' => UserFontsize::fromMixed($this->currentUser->value('fontsize', null))->stringValue(),
        ])->render();

        return response($content, 200, ['Content-Type' => 'text/html; charset=utf-8']);
    }

    public function getattachment(Request $request): Response|RedirectResponse|StreamedResponse
    {
        $id = (int) $request->input('id', 0);
        $dlkey = (string) $request->input('dlkey', '');

        if ($id <= 0 || $dlkey === '') {
            return response('Invalid id or key.', 400, ['Content-Type' => 'text/plain; charset=utf-8']);
        }

        $row = $this->attachmentRepository->findByIdAndDlkey($id, $dlkey) ?? [];
        if ($row === []) {
            return response('No attachment found.', 404, ['Content-Type' => 'text/plain; charset=utf-8']);
        }

        $httpdirectory = SiteConfig::current()->attachment->httpDirectory();
        // savedirectory is resolved against ROOT_PATH on upload; resolve the
        // same way here — a bare relative path would resolve against the
        // php-fpm CWD (public/) and miss the real location.
        $basePath = realpath(base_path($httpdirectory));
        $realFile = realpath(base_path($httpdirectory.'/'.$row['location']));

        if ($basePath === false || $realFile === false || ! str_starts_with($realFile, $basePath) || ! is_file($realFile) || ! is_readable($realFile)) {
            return response('File not found or cannot be read.', 404, ['Content-Type' => 'text/plain; charset=utf-8']);
        }

        $filename = basename((string) ($row['filename'] ?? ''));
        $filename = str_replace(['"', '\\', "\r", "\n"], '', $filename);
        if ($filename === '') {
            $filename = 'attachment';
        }

        $this->attachmentRepository->incrementDownloads($id);

        if ($this->cache !== null) {
            $this->cache->forget('attachment_'.$dlkey.'_content');
        }

        return new StreamedResponse(function () use ($realFile) {
            $f = fopen($realFile, 'rb');
            if (! $f) {
                return;
            }

            fpassthru($f);
            fclose($f);
        }, 200, [
            'Content-Type' => 'application/octet-stream',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
            'X-Content-Type-Options' => 'nosniff',
            'X-Accel-Redirect' => '/attachments/'.$row['location'],
        ]);
    }
}

<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\BitbucketService;
use App\Support\Cache\LegacyRedisCache;
use App\Support\CurrentUser;
use App\Support\Globals;
use App\Support\LegacyResponse;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use LogicException;

class BitbucketUploadController extends Controller
{
    public function __construct(
        private readonly BitbucketService $bitbucketService,
        private readonly ?LegacyRedisCache $legacyRedisCache,
        private readonly CurrentUser $currentUser,
        private readonly Globals $globals,
    ) {}

    public function create(Request $request): View|RedirectResponse
    {
        if ($this->legacyRedisCache === null) {
            return redirect('/bitbucket-upload.php?'.$request->getQueryString());
        }

        $user = Auth::guard('nexus-web')->user();
        if (! $user instanceof User) {
            return redirect('/login.php?returnto='.urlencode($request->fullUrl()));
        }

        $currentUser = $this->currentUser->get() ?? $user->toLegacyArray();
        $this->currentUser->set($currentUser);

        if ($currentUser['parked']) {
            LegacyResponse::abort((''), (''), false);
        }

        if ($this->globals->get('enablebitbucket_main', 'no') !== 'yes') {
            LegacyResponse::permissionDenied();
        }

        return view('bitbucket.upload', [
            'pageTitle' => __('legacy/bitbucketupload.head_avatar_upload'),
            'maxFileSize' => 256 * 1024,
            'scaleHeight' => 200,
            'scaleWidth' => 150,
        ]);
    }

    public function store(Request $request): View|RedirectResponse
    {
        if ($this->legacyRedisCache === null) {
            return redirect('/bitbucket-upload.php', 307);
        }

        $user = Auth::guard('nexus-web')->user();
        if (! $user instanceof User) {
            return redirect('/login.php?returnto='.urlencode($request->fullUrl()));
        }

        $currentUser = $this->currentUser->get() ?? $user->toLegacyArray();
        $this->currentUser->set($currentUser);

        if ($currentUser['parked']) {
            LegacyResponse::abort((''), (''), false);
        }

        if ($this->globals->get('enablebitbucket_main', 'no') !== 'yes') {
            LegacyResponse::permissionDenied();
        }

        /** @var UploadedFile|null $file */
        $file = $request->file('file');
        if (! $file instanceof UploadedFile || ! $file->isValid()) {
            LegacyResponse::abort(__('legacy/bitbucketupload.std_upload_failed'), __('legacy/bitbucketupload.std_nothing_received'), false);
        }
        if (! $file instanceof UploadedFile) {
            throw new LogicException('Expected uploaded file.');
        }

        if ($file->getSize() > 256 * 1024) {
            LegacyResponse::abort(__('legacy/bitbucketupload.std_upload_failed'), __('legacy/bitbucketupload.std_file_too_large'), false);
        }

        $allowedMimes = ['image/gif', 'image/jpeg', 'image/png'];
        if (! in_array($file->getMimeType(), $allowedMimes, true)) {
            LegacyResponse::abort(__('legacy/bitbucketupload.std_error'), __('legacy/bitbucketupload.std_invalid_image_format'), false);
        }

        $isPublic = $request->input('public') === 'yes';

        try {
            $result = $this->bitbucketService->uploadAvatar($file, $currentUser, $isPublic);
        } catch (LogicException $e) {
            $message = $e->getMessage();
            // Map known errors back to lang strings where possible
            if (str_starts_with($message, 'Bad file name')) {
                LegacyResponse::abort(__('legacy/bitbucketupload.std_upload_failed'), __('legacy/bitbucketupload.std_bad_file_name'), false);
            }
            if (str_starts_with($message, 'File already exists')) {
                $filename = $file->getClientOriginalName();
                LegacyResponse::abort(
                    __('legacy/bitbucketupload.std_upload_failed'),
                    (('')).htmlspecialchars($filename).(__('legacy/bitbucketupload.std_already_exists')),
                    false,
                );
            }
            if (str_starts_with($message, 'Invalid image format')) {
                LegacyResponse::abort(__('legacy/bitbucketupload.std_error'), __('legacy/bitbucketupload.std_invalid_image_format'), false);
            }
            if (str_starts_with($message, 'Image processing failed') || str_starts_with($message, 'Thumbnail creation failed')) {
                LegacyResponse::abort(
                    __('legacy/bitbucketupload.std_image_processing_failed'),
                    (__('legacy/bitbucketupload.std_sorry_the_uploaded')).(__('legacy/bitbucketupload.std_failed_processing')),
                    false,
                );
            }
            throw $e;
        }

        return view('bitbucket.result', [
            'url' => $result['url'],
            'filename' => $result['filename'],
            'width' => $result['width'],
            'height' => $result['height'],
            'newwidth' => $result['newwidth'],
            'newheight' => $result['newheight'],
        ]);
    }
}

<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\BitbucketService;
use App\Support\Cache\LegacyRedisCache;
use App\Support\CurrentUser;
use App\Support\Globals;
use App\Support\Input;
use App\Support\LegacyResponse;
use App\Support\Locale;
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
    ) {}

    public function create(Request $request): View|RedirectResponse
    {
        if (app(LegacyRedisCache::class) === null) {
            return redirect('/bitbucket-upload.php?'.$request->getQueryString());
        }

        $user = Auth::guard('nexus-web')->user();
        if (! $user instanceof User) {
            return redirect('/login.php?returnto='.urlencode($request->fullUrl()));
        }

        $currentUser = app(CurrentUser::class)->get() ?? $user->toLegacyArray();
        app(CurrentUser::class)->set($currentUser);

        $lang = $this->loadLang();

        if ($currentUser['parked']) {
            LegacyResponse::abort($lang['std_sorry'] ?? '', $lang['std_unauthorized_to_upload'] ?? '', false);
        }

        if (app(Globals::class)->get('enablebitbucket_main', 'no') !== 'yes') {
            LegacyResponse::permissionDenied();
        }

        return view('bitbucket.upload', [
            'pageTitle' => $lang['head_avatar_upload'] ?? '',
            'lang' => $lang,
            'maxFileSize' => 256 * 1024,
            'scaleHeight' => 200,
            'scaleWidth' => 150,
        ]);
    }

    public function store(Request $request): View|RedirectResponse
    {
        if (app(LegacyRedisCache::class) === null) {
            return redirect('/bitbucket-upload.php', 307);
        }

        $user = Auth::guard('nexus-web')->user();
        if (! $user instanceof User) {
            return redirect('/login.php?returnto='.urlencode($request->fullUrl()));
        }

        $currentUser = app(CurrentUser::class)->get() ?? $user->toLegacyArray();
        app(CurrentUser::class)->set($currentUser);

        $lang = $this->loadLang();

        if ($currentUser['parked']) {
            LegacyResponse::abort($lang['std_sorry'] ?? '', $lang['std_unauthorized_to_upload'] ?? '', false);
        }

        if (app(Globals::class)->get('enablebitbucket_main', 'no') !== 'yes') {
            LegacyResponse::permissionDenied();
        }

        /** @var UploadedFile|null $file */
        $file = $request->file('file');
        if (! $file instanceof UploadedFile || ! $file->isValid()) {
            LegacyResponse::abort($lang['std_upload_failed'] ?? '', $lang['std_nothing_received'] ?? '', false);
        }
        if (! $file instanceof UploadedFile) {
            throw new LogicException('Expected uploaded file.');
        }

        if ($file->getSize() > 256 * 1024) {
            LegacyResponse::abort($lang['std_upload_failed'] ?? '', $lang['std_file_too_large'] ?? '', false);
        }

        $allowedMimes = ['image/gif', 'image/jpeg', 'image/png'];
        if (! in_array($file->getMimeType(), $allowedMimes, true)) {
            LegacyResponse::abort($lang['std_error'] ?? '', $lang['std_invalid_image_format'] ?? '', false);
        }

        $isPublic = $request->input('public') === 'yes';

        try {
            $result = $this->bitbucketService->uploadAvatar($file, $currentUser, $isPublic);
        } catch (LogicException $e) {
            $message = $e->getMessage();
            // Map known errors back to lang strings where possible
            if (str_starts_with($message, 'Bad file name')) {
                LegacyResponse::abort($lang['std_upload_failed'] ?? '', $lang['std_bad_file_name'] ?? '', false);
            }
            if (str_starts_with($message, 'File already exists')) {
                $filename = $file->getClientOriginalName();
                LegacyResponse::abort(
                    $lang['std_upload_failed'] ?? '',
                    ($lang['std_file_already_exists'] ?? '').htmlspecialchars($filename).($lang['std_already_exists'] ?? ''),
                    false,
                );
            }
            if (str_starts_with($message, 'Invalid image format')) {
                LegacyResponse::abort($lang['std_error'] ?? '', $lang['std_invalid_image_format'] ?? '', false);
            }
            if (str_starts_with($message, 'Image processing failed') || str_starts_with($message, 'Thumbnail creation failed')) {
                LegacyResponse::abort(
                    $lang['std_image_processing_failed'] ?? '',
                    ($lang['std_sorry_the_uploaded'] ?? '').($lang['std_failed_processing'] ?? ''),
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
            'lang' => $lang,
        ]);
    }

    /** @return array<string, string> */
    private function loadLang(): array
    {
        if (empty(app(Globals::class)->get('lang_bitbucketupload'))) {
            Input::setServerValue('SCRIPT_NAME', '/bitbucket-upload.php');
            require base_path(Locale::scriptFilePath((string) '', (bool) false, (string) ''));
            app(Globals::class)->set('lang_bitbucketupload', $lang_bitbucketupload ?? []);
        }

        return (array) app(Globals::class)->get('lang_bitbucketupload', []);
    }
}

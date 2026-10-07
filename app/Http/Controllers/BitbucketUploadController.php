<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\BitbucketService;
use App\Support\Cache\NexusCache;
use App\Support\Config\SiteConfig;
use App\Support\CurrentUser;
use App\Support\PageResponses;
use App\Support\Path;
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
        private readonly ?NexusCache $cache,
        private readonly CurrentUser $currentUser,
    ) {}

    public function create(Request $request): View|RedirectResponse
    {
        if ($this->cache === null) {
            return redirect('/web/bitbucket-upload?'.$request->getQueryString());
        }

        $user = Auth::guard('nexus-web')->user();
        if (! $user instanceof User) {
            return redirect('/login?returnto='.urlencode($request->fullUrl()));
        }

        $currentUser = $this->currentUser->get() ?? $user->toLegacyArray();
        $this->currentUser->set($currentUser);

        if ($this->currentUser->value('parked')) {
            PageResponses::abort((''), (''), false);
        }

        if (! SiteConfig::current()->main->enableBitbucket()) {
            PageResponses::permissionDenied();
        }

        $bucketDir = Path::resolve(SiteConfig::current()->main->bitbucket(), public_path());
        $bucketWritable = is_dir($bucketDir) ? is_writable($bucketDir) : is_writable(public_path());

        return view('bitbucket.upload', [
            'pageTitle' => __('bitbucketupload.head_avatar_upload'),
            'maxFileSize' => 256 * 1024,
            'scaleHeight' => 200,
            'scaleWidth' => 150,
            'bucketWritable' => $bucketWritable,
        ]);
    }

    public function store(Request $request): View|RedirectResponse
    {
        if ($this->cache === null) {
            return redirect('/web/bitbucket-upload', 307);
        }

        $user = Auth::guard('nexus-web')->user();
        if (! $user instanceof User) {
            return redirect('/login?returnto='.urlencode($request->fullUrl()));
        }

        $currentUser = $this->currentUser->get() ?? $user->toLegacyArray();
        $this->currentUser->set($currentUser);

        if ($this->currentUser->value('parked')) {
            PageResponses::abort((''), (''), false);
        }

        if (! SiteConfig::current()->main->enableBitbucket()) {
            PageResponses::permissionDenied();
        }

        /** @var UploadedFile|array<int, UploadedFile|null>|null $uploaded */
        $uploaded = $request->file('file');
        if ($uploaded instanceof UploadedFile) {
            $uploaded = [$uploaded];
        }
        $files = is_array($uploaded)
            ? array_values(array_filter($uploaded, fn ($f) => $f instanceof UploadedFile && $f->isValid()))
            : [];
        if ($files === []) {
            PageResponses::abort(__('bitbucketupload.std_upload_failed'), __('bitbucketupload.std_nothing_received'), false);
        }

        $allowedMimes = ['image/gif', 'image/jpeg', 'image/png'];
        $isPublic = $request->input('public') === 'yes';
        $results = [];
        $errors = [];

        foreach ($files as $file) {
            $originalName = $file->getClientOriginalName();
            if ($file->getSize() > 256 * 1024) {
                $errors[] = ['filename' => $originalName, 'message' => __('bitbucketupload.std_file_too_large')];

                continue;
            }
            if (! in_array($file->getMimeType(), $allowedMimes, true)) {
                $errors[] = ['filename' => $originalName, 'message' => __('bitbucketupload.std_invalid_image_format')];

                continue;
            }
            try {
                $results[] = $this->bitbucketService->uploadAvatar($file, $currentUser, $isPublic);
            } catch (LogicException $e) {
                $errors[] = ['filename' => $originalName, 'message' => $this->describeUploadError($e)];
            }
        }

        return view('bitbucket.result', [
            'results' => $results,
            'errors' => $errors,
        ]);
    }

    private function describeUploadError(LogicException $e): string
    {
        $message = $e->getMessage();
        if (str_starts_with($message, 'Bad file name')) {
            return __('bitbucketupload.std_bad_file_name');
        }
        if (str_starts_with($message, 'File already exists')) {
            return __('bitbucketupload.std_already_exists');
        }
        if (str_starts_with($message, 'Upload directory is not writable')) {
            return __('bitbucketupload.text_upload_directory_unwritable');
        }
        if (str_starts_with($message, 'Invalid image format')) {
            return __('bitbucketupload.std_invalid_image_format');
        }
        if (str_starts_with($message, 'Image processing failed') || str_starts_with($message, 'Thumbnail creation failed')) {
            return __('bitbucketupload.std_sorry_the_uploaded').__('bitbucketupload.std_failed_processing');
        }
        throw $e;
    }
}

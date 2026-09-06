<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Exceptions\NexusException;
use App\Models\Category;
use App\Models\Torrent;
use App\Models\User;
use App\Services\UploadMetadataService;
use App\Services\UploadService;
use App\Support\Config\SiteConfig;
use App\Support\Format;
use App\Support\Locale;
use App\Support\Logger;
use App\Support\Url;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class UploadRepository extends BaseRepository
{
    public function __construct(
        private UploadService $uploadService,
        private UploadMetadataService $metadataService,
    ) {}

    /**
     * @return mixed
     *
     * @throws NexusException
     */
    public function upload(Request $request)
    {
        return $this->uploadService->upload($request);
    }

    public function getPrice(Request $request): int
    {
        return $this->metadataService->getPrice($request);
    }

    public function getHitAndRun(Request $request, Category $category): int
    {
        return $this->metadataService->getHitAndRun($request, $category);
    }

    /**
     * @return array<int|string, mixed>
     */
    public function getPosStateInfo(Request $request): array
    {
        return $this->metadataService->getPosStateInfo($request);
    }

    /**
     * @return array<int|string, mixed>
     *
     * @throws NexusException
     */
    public function getSubCategoriesAndTags(Request $request, Category $category, bool $checkUploadPermission = true): array
    {
        return $this->metadataService->getSubCategoriesAndTags($request, $category, $checkUploadPermission);
    }

    public function getCover(Request $request): string
    {
        return $this->metadataService->getCover($request);
    }

    public function saveCustomFields(Request $request, Category $category, int $torrentId): void
    {
        $this->uploadService->saveCustomFields($request, $category, $torrentId);
    }

    /**
     * @param  mixed  $userId
     */
    public function sendEmailNotification(Torrent $torrent, $userId = 0): int
    {
        $logMsg = sprintf('torrent: %s, category: %s', $torrent->id, $torrent->category);
        $siteConfig = SiteConfig::current();
        if (! $siteConfig->smtp->emailNotify() || $siteConfig->smtp->type() == 'none') {
            Logger::writeWithContext((string) "{$logMsg}, not allow user receive email notification or smtp type is none", (string) 'info', (bool) false);

            return 0;
        }
        $page = 1;
        $size = 1000;
        $query = User::query()
            ->where('notifs', 'like', "%[cat$torrent->category]%")
            ->where('notifs', 'like', '%[email]%')
            ->normal();
        if ($userId > 0) {
            $query->where('id', $userId);
        }
        $total = (clone $query)->count();
        if ($total == 0) {
            Logger::writeWithContext((string) sprintf('%s, no user receive email notification', $logMsg), (string) 'info', (bool) false);

            return 0;
        }
        $toolRep = app(ToolRepository::class);
        $categoryName = $torrent->basic_category->name;
        $torrentUploader = $torrent->user;
        $successCount = 0;
        while (true) {
            $logPage = "$logMsg, page: $page";
            $users = (clone $query)->with(['language'])->forPage($page, $size)->get(['id', 'email', 'lang']);
            if ($users->isEmpty()) {
                Logger::writeWithContext((string) sprintf('%s, no more user', $logPage), (string) 'info', (bool) false);
                break;
            }
            foreach ($users as $user) {
                $locale = $user->locale;
                $logUser = "$logPage, user $user->id, locale: $locale";
                $subject = Locale::trans('upload.email_notification_subject', ['site_name' => SiteConfig::current()->basic->siteName()], $locale);
                $uploadByUsername = $torrentUploader instanceof User ? $torrentUploader->username : '';
                $description = $torrent->extra !== null ? ($torrent->extra->descr ?? '') : '';
                $body = Locale::trans('upload.email_notification_body', ['site_name' => SiteConfig::current()->basic->siteName(), 'name' => $torrent->name, 'size' => Format::size($torrent->size), 'category' => $categoryName, 'upload_by' => $this->handleAnonymous($uploadByUsername, $torrentUploader, $user, $torrent), 'description' => Str::limit(strip_tags(Format::formatComment($description)), 500), 'torrent_url' => sprintf('%s/details.php?id=%s&hit=1', Url::baseUrl(), $torrent->id)], $locale);
                $sendResult = $toolRep->sendMail($user->email, $subject, $body);
                Logger::writeWithContext((string) sprintf('%s, send result: %s', $logUser, $sendResult), (string) 'info', (bool) false);
                if ($sendResult) {
                    $successCount++;
                }
            }
            $page++;
        }
        Logger::writeWithContext((string) "{$logMsg}, receive email notification user total: {$total}, successCount: {$successCount}, done!", (string) 'info', (bool) false);

        return $successCount;
    }
}

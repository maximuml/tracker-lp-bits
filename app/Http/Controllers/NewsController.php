<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Auth\Permission;
use App\Enums\Permission\PermissionEnum;
use App\Events\NewsCreated;
use App\Http\Requests\NewsStoreRequest;
use App\Http\Requests\NewsUpdateRequest;
use App\Http\Resources\NewsResource;
use App\Models\News;
use App\Repositories\IndexRepository;
use App\Support\Cache\LegacyRedisCache;
use App\Support\Config\SiteConfig;
use App\Support\CurrentUser;
use App\Support\Http\SafeReturnUrl;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

class NewsController extends LegacyController
{
    public function __construct(
        private readonly CurrentUser $currentUser,
        private readonly ?LegacyRedisCache $legacyRedisCache,
        private readonly IndexRepository $indexRepository,
    ) {}

    public function news(Request $request): Response|RedirectResponse|View
    {
        $baseUrl = SiteConfig::current()->basic->baseUrl();

        $action = (string) ($request->input('action') ?? '');

        if ($action === 'delete') {
            if (! Permission::can(PermissionEnum::NEWS_MANAGE)) {
                return $this->legacyAbortResponse(__('legacy/news.std_error'), ('Permission denied.'));
            }
            if (! $request->isMethod('post')) {
                return $this->legacyAbortResponse(__('legacy/news.std_error'), ('Permission denied.'));
            }
            $newsid = (int) $request->input('newsid', 0);
            if ($newsid <= 0) {
                return $this->legacyAbortResponse(__('legacy/news.std_error'), __('legacy/news.std_invalid_news_id'));
            }

            $returnto = $request->input('returnto') !== null && $request->input('returnto') !== ''
                ? (string) $request->input('returnto')
                : (string) $request->headers->get('referer', '');

            if ((int) $request->input('sure', 0) !== 1) {
                $confirm = (__('legacy/news.std_are_you_sure'))
                    .'<form method="post" action="'.htmlspecialchars((string) url('/news')).'" style="display:inline">'
                    .csrf_field()
                    .'<input type="hidden" name="action" value="delete" />'
                    .'<input type="hidden" name="newsid" value="'.$newsid.'" />'
                    .'<input type="hidden" name="returnto" value="'.htmlspecialchars($returnto).'" />'
                    .'<input type="hidden" name="sure" value="1" />'
                    .'<button type="submit" class="altlink">'.(__('legacy/news.std_here')).'</button>'
                    .'</form>'
                    .(__('legacy/news.std_if_sure'));

                return $this->legacyAbortResponse(__('legacy/news.std_delete_news_item'), $confirm, false);
            }

            News::query()->where('id', $newsid)->delete();
            $this->invalidateNewsCache();

            if ($returnto !== '') {
                return redirect(SafeReturnUrl::filter($returnto));
            }

            return redirect('/');
        }

        if ($action === 'add') {
            if (! Permission::can(PermissionEnum::NEWS_MANAGE)) {
                return $this->legacyAbortResponse(__('legacy/news.std_error'), ('Permission denied.'));
            }
            if (! $request->isMethod('post')) {
                return $this->legacyAbortResponse(__('legacy/news.std_error'), __('legacy/news.std_news_body_empty'));
            }
            $body = (string) $request->input('body', '');
            if ($body === '') {
                return $this->legacyAbortResponse(__('legacy/news.std_error'), __('legacy/news.std_news_body_empty'));
            }
            $title = (string) $request->input('subject', '');
            if ($title === '') {
                return $this->legacyAbortResponse(__('legacy/news.std_error'), __('legacy/news.std_news_title_empty'));
            }
            $added = (int) $request->input('added', 0);
            if ($added <= 0) {
                $added = now()->toDateTimeString();
            }
            $notify = $request->input('notify') === 'yes';

            $currentUser = (array) ($this->currentUser->get() ?? []);
            $newsId = (int) News::query()->insertGetId([
                'userid' => (int) ($currentUser['id'] ?? 0),
                'added' => $added,
                'body' => $body,
                'title' => $title,
                'notify' => $notify,
            ]);

            if (! $newsId) {
                return $this->legacyAbortResponse(__('legacy/news.std_error'), __('legacy/news.std_something_weird_happened'));
            }

            $this->invalidateNewsCache();

            $news = News::query()->find($newsId);
            if (! $news) {
                return $this->legacyAbortResponse(__('legacy/news.std_error'), __('legacy/news.std_something_weird_happened'));
            }
            event(new NewsCreated($news));

            return redirect('/');
        }

        if ($action === 'edit') {
            if (! Permission::can(PermissionEnum::NEWS_MANAGE)) {
                return $this->legacyAbortResponse(__('legacy/news.std_error'), ('Permission denied.'));
            }
            $newsid = (int) $request->input('newsid', 0);
            if ($newsid <= 0) {
                return $this->legacyAbortResponse(__('legacy/news.std_error'), __('legacy/news.std_invalid_news_id'));
            }

            $news = News::query()->where('id', $newsid)->first();
            if (! $news) {
                return $this->legacyAbortResponse(__('legacy/news.std_error'), __('legacy/news.std_invalid_news_id').$newsid);
            }

            if ($request->isMethod('post')) {
                $body = (string) $request->input('body', '');
                if ($body === '') {
                    return $this->legacyAbortResponse(__('legacy/news.std_error'), __('legacy/news.std_news_body_empty'));
                }
                $title = (string) $request->input('subject', '');
                if ($title === '') {
                    return $this->legacyAbortResponse(__('legacy/news.std_error'), __('legacy/news.std_news_title_empty'));
                }
                $notify = $request->input('notify') === 'yes';

                News::query()->where('id', $newsid)->update([
                    'body' => $body,
                    'title' => $title,
                    'notify' => $notify,
                ]);

                $this->invalidateNewsCache();

                return redirect('/');
            }

            $arr = $news->toArray();
            $newsTitle = __('legacy/news.text_edit_site_news');
            $returnto = (string) ($request->input('returnto') ?? $request->headers->get('referer', ''));

            return $this->legacyPageRaw($request, 'news', true, [
                'mode' => 'edit',
                'newsid' => $newsid,
                'body' => $arr['body'] ?? '',
                'subject' => $arr['title'] ?? '',
                'notify' => ($arr['notify'] ?? false) ? 'yes' : 'no',
                'returnto' => $returnto,
                'title' => $newsTitle,
                'composeTitle' => $newsTitle,
                'checked' => ($arr['notify'] ?? false) ? ' checked' : '',
                'actionUrl' => htmlspecialchars('?action=edit&newsid='.$newsid),
            ]);
        }

        // Default: show compose form
        if (! Permission::can(PermissionEnum::NEWS_MANAGE)) {
            return $this->legacyAbortResponse(__('legacy/news.std_error'), ('Permission denied.'));
        }
        $composeTitle = __('legacy/news.text_submit_news_item');

        return $this->legacyPageRaw($request, 'news', true, [
            'mode' => 'add',
            'newsid' => 0,
            'body' => '',
            'subject' => '',
            'notify' => 'no',
            'returnto' => '',
            'title' => $composeTitle,
            'composeTitle' => $composeTitle,
            'checked' => '',
            'actionUrl' => '?action=add',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function index(Request $request): array
    {
        $perPage = (int) $request->input('limit', 20);

        $news = News::query()->with(['user'])->latest('added')->paginate($perPage);

        return $this->success(NewsResource::collection($news));
    }

    /**
     * @return array<string, mixed>
     */
    public function show(News $news): array
    {
        return $this->success(new NewsResource($news->load(['user'])));
    }

    /**
     * @return array<string, mixed>
     */
    public function store(NewsStoreRequest $request): array
    {
        $data = $request->validated();

        $currentUser = (array) ($this->currentUser->get() ?? []);
        $data['userid'] = (int) ($currentUser['id'] ?? 0);
        $data['added'] = now()->toDateTimeString();
        $data['notify'] = ($data['notify'] ?? 'no') === 'yes';

        $news = News::query()->create($data);
        event(new NewsCreated($news));

        $this->invalidateNewsCache();

        return $this->success(new NewsResource($news), 'News created');
    }

    /**
     * @return array<string, mixed>
     */
    public function update(NewsUpdateRequest $request, News $news): array
    {
        $data = $request->validated();

        if (isset($data['notify'])) {
            $data['notify'] = $data['notify'] === 'yes';
        }

        $news->update($data);

        $this->invalidateNewsCache();

        return $this->success(new NewsResource($news->fresh()), 'News updated');
    }

    /**
     * @return array<string, mixed>
     */
    public function destroy(News $news): array
    {
        $news->delete();

        $this->invalidateNewsCache();

        return $this->success(['success' => true], 'News deleted');
    }

    /**
     * @return array<string, mixed>
     */
    public function latest(): array
    {
        $maxNews = SiteConfig::current()->main->maxNewsNum(5);

        $items = $this->indexRepository->getLatestNews($maxNews);

        return $this->success(NewsResource::collection($items));
    }

    private function invalidateNewsCache(): void
    {
        $this->legacyRedisCache?->delete_value('recent_news', true);
        $this->indexRepository->forgetLatestNews(SiteConfig::current()->main->maxNewsNum(5));
    }
}

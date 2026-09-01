<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Auth\Permission;
use App\Enums\Permission\PermissionEnum;
use App\Http\Requests\NewsStoreRequest;
use App\Http\Requests\NewsUpdateRequest;
use App\Http\Resources\NewsResource;
use App\Models\News;
use App\Repositories\IndexRepository;
use App\Support\Cache\LegacyRedisCache;
use App\Support\CurrentUser;
use App\Support\Events;
use App\Support\Globals;
use App\Support\Http\SafeReturnUrl;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

class NewsController extends LegacyController
{
    public function news(Request $request): Response|RedirectResponse|View
    {
        $langNews = (array) (app(Globals::class)->get('lang_news') ?? []);
        $baseUrl = (string) app(Globals::class)->get('BASEURL', '');

        $action = (string) ($request->input('action') ?? '');

        if ($action === 'delete') {
            if (! Permission::can(PermissionEnum::NEWS_MANAGE)) {
                return $this->legacyAbortResponse($langNews['std_error'] ?? 'Error', $langNews['std_permission_denied'] ?? 'Permission denied.');
            }
            if (! $request->isMethod('post')) {
                return $this->legacyAbortResponse($langNews['std_error'] ?? 'Error', $langNews['std_permission_denied'] ?? 'Permission denied.');
            }
            $newsid = (int) $request->input('newsid', 0);
            if ($newsid <= 0) {
                return $this->legacyAbortResponse($langNews['std_error'] ?? 'Error', $langNews['std_invalid_news_id'] ?? 'Invalid news ID.');
            }

            $returnto = $request->input('returnto') !== null && $request->input('returnto') !== ''
                ? (string) $request->input('returnto')
                : (string) $request->headers->get('referer', '');

            if ((int) $request->input('sure', 0) !== 1) {
                $confirm = ($langNews['std_are_you_sure'] ?? 'Are you sure? ')
                    .'<form method="post" action="'.htmlspecialchars((string) url('/news')).'" style="display:inline">'
                    .csrf_field()
                    .'<input type="hidden" name="action" value="delete" />'
                    .'<input type="hidden" name="newsid" value="'.$newsid.'" />'
                    .'<input type="hidden" name="returnto" value="'.htmlspecialchars($returnto).'" />'
                    .'<input type="hidden" name="sure" value="1" />'
                    .'<button type="submit" class="altlink">'.($langNews['std_here'] ?? 'here').'</button>'
                    .'</form>'
                    .($langNews['std_if_sure'] ?? '.');

                return $this->legacyAbortResponse($langNews['std_delete_news_item'] ?? 'Delete news item', $confirm, false);
            }

            News::query()->where('id', $newsid)->delete();
            $cache = app(LegacyRedisCache::class);
            if ($cache !== null) {
                $cache->delete_value('recent_news', true);
            }

            if ($returnto !== '') {
                return redirect(SafeReturnUrl::filter($returnto));
            }

            return redirect('/');
        }

        if ($action === 'add') {
            if (! Permission::can(PermissionEnum::NEWS_MANAGE)) {
                return $this->legacyAbortResponse($langNews['std_error'] ?? 'Error', $langNews['std_permission_denied'] ?? 'Permission denied.');
            }
            if (! $request->isMethod('post')) {
                return $this->legacyAbortResponse($langNews['std_error'] ?? 'Error', $langNews['std_news_body_empty'] ?? 'News body empty.');
            }
            $body = (string) $request->input('body', '');
            if ($body === '') {
                return $this->legacyAbortResponse($langNews['std_error'] ?? 'Error', $langNews['std_news_body_empty'] ?? 'News body empty.');
            }
            $title = (string) $request->input('subject', '');
            if ($title === '') {
                return $this->legacyAbortResponse($langNews['std_error'] ?? 'Error', $langNews['std_news_title_empty'] ?? 'News title empty.');
            }
            $added = (int) $request->input('added', 0);
            if ($added <= 0) {
                $added = now()->toDateTimeString();
            }
            $notify = $request->input('notify') === 'yes';

            $currentUser = (array) (app(CurrentUser::class)->get() ?? []);
            $newsId = (int) News::query()->insertGetId([
                'userid' => (int) ($currentUser['id'] ?? 0),
                'added' => $added,
                'body' => $body,
                'title' => $title,
                'notify' => $notify,
            ]);

            if (! $newsId) {
                return $this->legacyAbortResponse($langNews['std_error'] ?? 'Error', $langNews['std_something_weird_happened'] ?? 'Something weird happened.');
            }

            $cache = app(LegacyRedisCache::class);
            if ($cache !== null) {
                $cache->delete_value('recent_news', true);
            }

            $news = News::query()->find($newsId);
            if (! $news) {
                return $this->legacyAbortResponse($langNews['std_error'] ?? 'Error', $langNews['std_something_weird_happened'] ?? 'Something weird happened.');
            }
            Events::fire('news_created', $news, null);

            return redirect('/');
        }

        if ($action === 'edit') {
            if (! Permission::can(PermissionEnum::NEWS_MANAGE)) {
                return $this->legacyAbortResponse($langNews['std_error'] ?? 'Error', $langNews['std_permission_denied'] ?? 'Permission denied.');
            }
            $newsid = (int) $request->input('newsid', 0);
            if ($newsid <= 0) {
                return $this->legacyAbortResponse($langNews['std_error'] ?? 'Error', $langNews['std_invalid_news_id'] ?? 'Invalid news ID.');
            }

            $news = News::query()->where('id', $newsid)->first();
            if (! $news) {
                return $this->legacyAbortResponse($langNews['std_error'] ?? 'Error', $langNews['std_invalid_news_id'].$newsid);
            }

            if ($request->isMethod('post')) {
                $body = (string) $request->input('body', '');
                if ($body === '') {
                    return $this->legacyAbortResponse($langNews['std_error'] ?? 'Error', $langNews['std_news_body_empty'] ?? 'News body empty.');
                }
                $title = (string) $request->input('subject', '');
                if ($title === '') {
                    return $this->legacyAbortResponse($langNews['std_error'] ?? 'Error', $langNews['std_news_title_empty'] ?? 'News title empty.');
                }
                $notify = $request->input('notify') === 'yes';

                News::query()->where('id', $newsid)->update([
                    'body' => $body,
                    'title' => $title,
                    'notify' => $notify,
                ]);

                $cache = app(LegacyRedisCache::class);
                if ($cache !== null) {
                    $cache->delete_value('recent_news', true);
                }

                return redirect('/');
            }

            $arr = $news->toArray();
            $newsTitle = $langNews['text_edit_site_news'] ?? 'Edit site news';
            $returnto = (string) ($request->input('returnto') ?? $request->headers->get('referer', ''));

            return $this->legacyPageRaw($request, 'news', true, [
                'mode' => 'edit',
                'newsid' => $newsid,
                'body' => $arr['body'] ?? '',
                'subject' => $arr['title'] ?? '',
                'notify' => ($arr['notify'] ?? false) ? 'yes' : 'no',
                'returnto' => $returnto,
                'title' => $newsTitle,
            ]);
        }

        // Default: show compose form
        if (! Permission::can(PermissionEnum::NEWS_MANAGE)) {
            return $this->legacyAbortResponse($langNews['std_error'] ?? 'Error', $langNews['std_permission_denied'] ?? 'Permission denied.');
        }
        $composeTitle = $langNews['text_submit_news_item'] ?? 'Submit news item';

        return $this->legacyPageRaw($request, 'news', true, [
            'mode' => 'add',
            'title' => $composeTitle,
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

        $currentUser = (array) (app(CurrentUser::class)->get() ?? []);
        $data['userid'] = (int) ($currentUser['id'] ?? 0);
        $data['added'] = now()->toDateTimeString();
        $data['notify'] = ($data['notify'] ?? 'no') === 'yes';

        $news = News::query()->create($data);
        Events::fire('news_created', $news, null);

        $cache = app(LegacyRedisCache::class);
        $cache?->delete_value('recent_news', true);

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

        $cache = app(LegacyRedisCache::class);
        $cache?->delete_value('recent_news', true);

        return $this->success(new NewsResource($news->fresh()), 'News updated');
    }

    /**
     * @return array<string, mixed>
     */
    public function destroy(News $news): array
    {
        $news->delete();

        $cache = app(LegacyRedisCache::class);
        $cache?->delete_value('recent_news', true);

        return $this->success(['success' => true], 'News deleted');
    }

    /**
     * @return array<string, mixed>
     */
    public function latest(): array
    {
        $maxNews = (int) app(Globals::class)->get('maxnewsnum_main', 5);

        $items = app(IndexRepository::class)->getLatestNews($maxNews);

        return $this->success(NewsResource::collection($items));
    }
}

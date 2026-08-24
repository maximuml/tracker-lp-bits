<?php

namespace App\Http\Controllers;

use App\Http\Resources\NewsResource;
use App\Models\News;
use App\Repositories\IndexRepository;
use App\Support\Events;
use App\Support\SupportContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

class NewsController extends LegacyController
{
    public function news(Request $request): Response|RedirectResponse|View
    {
        $langNews = (array) (SupportContext::getGlobal('lang_news') ?? []);
        $baseUrl = (string) SupportContext::getGlobal('BASEURL', '');

        $action = htmlspecialchars((string) ($request->input('action') ?? ''));

        if ($action === 'delete') {
            $newsid = (int) $request->input('newsid', 0);
            if ($newsid <= 0) {
                return $this->legacyAbortResponse($langNews['std_error'] ?? 'Error', $langNews['std_invalid_news_id'] ?? 'Invalid news ID.');
            }

            $returnto = $request->input('returnto') !== null && $request->input('returnto') !== ''
                ? htmlspecialchars((string) $request->input('returnto'))
                : htmlspecialchars((string) $request->headers->get('referer', ''));

            if ((int) $request->input('sure', 0) !== 1) {
                $confirm = ($langNews['std_are_you_sure'] ?? 'Are you sure? ')."<a class=altlink href=\"?action=delete&newsid={$newsid}&returnto=".urlencode($returnto).'&sure=1">'.($langNews['std_here'] ?? 'here').'</a>'.($langNews['std_if_sure'] ?? '.');

                return $this->legacyAbortResponse($langNews['std_delete_news_item'] ?? 'Delete news item', $confirm, false);
            }

            News::query()->where('id', $newsid)->delete();
            $cache = SupportContext::getCache();
            if ($cache !== null) {
                $cache->delete_value('recent_news', true);
            }

            if ($returnto !== '') {
                return redirect($returnto);
            }

            return redirect('/');
        }

        if ($action === 'add') {
            if (! $request->isMethod('post')) {
                return $this->legacyAbortResponse($langNews['std_error'] ?? 'Error', $langNews['std_news_body_empty'] ?? 'News body empty.');
            }
            $body = htmlspecialchars((string) $request->input('body'), ENT_QUOTES);
            if ($body === '') {
                return $this->legacyAbortResponse($langNews['std_error'] ?? 'Error', $langNews['std_news_body_empty'] ?? 'News body empty.');
            }
            $title = htmlspecialchars((string) $request->input('subject'));
            if ($title === '') {
                return $this->legacyAbortResponse($langNews['std_error'] ?? 'Error', $langNews['std_news_title_empty'] ?? 'News title empty.');
            }
            $added = (int) $request->input('added', 0);
            if ($added <= 0) {
                $added = now()->toDateTimeString();
            }
            $notify = $request->input('notify') === 'yes' ? 'yes' : 'no';

            $currentUser = (array) (SupportContext::getUser() ?? []);
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

            $cache = SupportContext::getCache();
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
            $newsid = (int) $request->input('newsid', 0);
            if ($newsid <= 0) {
                return $this->legacyAbortResponse($langNews['std_error'] ?? 'Error', $langNews['std_invalid_news_id'] ?? 'Invalid news ID.');
            }

            $news = News::query()->where('id', $newsid)->first();
            if (! $news) {
                return $this->legacyAbortResponse($langNews['std_error'] ?? 'Error', $langNews['std_invalid_news_id'].$newsid);
            }

            if ($request->isMethod('post')) {
                $body = htmlspecialchars((string) $request->input('body'), ENT_QUOTES);
                if ($body === '') {
                    return $this->legacyAbortResponse($langNews['std_error'] ?? 'Error', $langNews['std_news_body_empty'] ?? 'News body empty.');
                }
                $title = htmlspecialchars((string) $request->input('subject'));
                if ($title === '') {
                    return $this->legacyAbortResponse($langNews['std_error'] ?? 'Error', $langNews['std_news_title_empty'] ?? 'News title empty.');
                }
                $notify = $request->input('notify') === 'yes' ? 'yes' : 'no';

                News::query()->where('id', $newsid)->update([
                    'body' => $body,
                    'title' => $title,
                    'notify' => $notify,
                ]);

                $cache = SupportContext::getCache();
                if ($cache !== null) {
                    $cache->delete_value('recent_news', true);
                }

                return redirect('/');
            }

            $arr = $news->toArray();
            $newsTitle = $langNews['text_edit_site_news'] ?? 'Edit site news';
            $returnto = htmlspecialchars((string) ($request->input('returnto') ?? $request->headers->get('referer', '')));

            return $this->legacyPageRaw($request, 'news', true, [
                'mode' => 'edit',
                'newsid' => $newsid,
                'body' => $arr['body'] ?? '',
                'subject' => htmlspecialchars((string) ($arr['title'] ?? '')),
                'notify' => (string) ($arr['notify'] ?? 'no'),
                'returnto' => $returnto,
                'title' => $newsTitle,
            ]);
        }

        // Default: show compose form
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
    public function store(Request $request): array
    {
        $data = $request->validate([
            'title' => 'required|string|max:255',
            'body' => 'required|string',
            'notify' => 'in:yes,no',
        ]);

        $currentUser = (array) (SupportContext::getUser() ?? []);
        $data['userid'] = (int) ($currentUser['id'] ?? 0);
        $data['added'] = now()->toDateTimeString();
        $data['notify'] = $data['notify'] ?? 'no';

        $news = News::query()->create($data);
        Events::fire('news_created', $news, null);

        $cache = SupportContext::getCache();
        $cache?->delete_value('recent_news', true);

        return $this->success(new NewsResource($news), 'News created');
    }

    /**
     * @return array<string, mixed>
     */
    public function update(Request $request, News $news): array
    {
        $data = $request->validate([
            'title' => 'sometimes|string|max:255',
            'body' => 'sometimes|string',
            'notify' => 'sometimes|in:yes,no',
        ]);

        $news->update($data);

        $cache = SupportContext::getCache();
        $cache?->delete_value('recent_news', true);

        return $this->success(new NewsResource($news->fresh()), 'News updated');
    }

    /**
     * @return array<string, mixed>
     */
    public function destroy(News $news): array
    {
        $news->delete();

        $cache = SupportContext::getCache();
        $cache?->delete_value('recent_news', true);

        return $this->success(['success' => true], 'News deleted');
    }

    /**
     * @return array<string, mixed>
     */
    public function latest(): array
    {
        $maxNews = (int) SupportContext::getGlobal('maxnewsnum_main', 5);

        $items = IndexRepository::getLatestNews($maxNews);

        return $this->success(NewsResource::collection($items));
    }
}

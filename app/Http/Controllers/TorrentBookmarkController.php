<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Torrent;
use App\Support\Bonus;
use App\Support\Cache\LegacyRedisCache;
use App\Support\CurrentUser;
use App\Support\Globals;
use App\Support\LegacyResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

class TorrentBookmarkController extends LegacyController
{
    public function bookmark(Request $request): Response
    {
        $headers = [
            'Expires' => 'Mon, 26 Jul 1997 05:00:00 GMT',
            'Last-Modified' => gmdate('D, d M Y H:i:s').' GMT',
            'Cache-Control' => 'no-cache, must-revalidate',
            'Pragma' => 'no-cache',
            'Content-Type' => 'text/xml; charset=utf-8',
        ];

        $user = app(CurrentUser::class)->get();
        if ($user === null) {
            return response('failed', 200, $headers);
        }

        $torrentId = (int) $request->input('torrentid', 0);
        if ($torrentId <= 0) {
            return response('failed', 200, $headers);
        }

        $userId = (int) $user['id'];
        $bookmark = DB::table('bookmarks')->where('torrentid', $torrentId)->where('userid', $userId)->first();

        if ($bookmark) {
            $bookmarkId = (int) $bookmark->id;
            DB::table('bookmarks')->where('id', $bookmarkId)->delete();
            $status = 'deleted';
        } else {
            DB::table('bookmarks')->insertGetId([
                'torrentid' => $torrentId,
                'userid' => $userId,
            ]);
            $status = 'added';
        }

        $cache = app(LegacyRedisCache::class);
        if ($cache !== null) {
            $cache->delete_value('user_'.$userId.'_bookmark_array');
        }

        return response($status, 200, $headers);
    }

    public function thanks(Request $request): Response|RedirectResponse
    {
        if (app(CurrentUser::class)->get() === null) {
            return redirect('/thanks.php'.($request->getQueryString() ? '?'.$request->getQueryString() : ''));
        }

        $curUser = app(CurrentUser::class)->get();
        $userid = (int) ($curUser['id'] ?? 0);

        if ($request->query('id') !== null) {
            LegacyResponse::abort('Party is over!', "This trick doesn't work anymore. You need to click the button!");
        }

        $torrentid = (int) request()->post('id');
        $torrentowner = Torrent::query()->where('id', $torrentid)->value('owner');
        if (! $torrentowner) {
            LegacyResponse::abort('Error', 'Invalid torrent id!');
        }

        $existing = DB::table('thanks')
            ->where('torrentid', $torrentid)
            ->where('userid', $userid)
            ->count();
        if ($existing != 0) {
            LegacyResponse::abort('Error', 'You already said thanks!');
        }

        DB::table('thanks')->insert([
            'torrentid' => $torrentid,
            'userid' => $userid,
        ]);

        $saythanksBonus = (float) app(Globals::class)->get('saythanks_bonus', 0);
        $receivethanksBonus = (float) app(Globals::class)->get('receivethanks_bonus', 0);
        Bonus::updatePoints('+', $saythanksBonus, $userid);
        Bonus::updatePoints('+', $receivethanksBonus, (int) $torrentowner);

        return $this->legacyPageRaw($request, 'thanks', true, [
            'torrentid' => $torrentid,
            'message' => 'Thank you has been recorded.',
        ]);
    }
}

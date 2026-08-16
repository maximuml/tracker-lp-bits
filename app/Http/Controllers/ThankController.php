<?php

namespace App\Http\Controllers;

use App\Http\Resources\ThankResource;
use App\Models\Thank;
use App\Models\Torrent;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ThankController extends Controller
{
    /**
     * Display a listing of the resource.
     * @param  \Illuminate\Http\Request  $request
     * @return  array<string, mixed>
     */
    public function index(Request $request)
    {
        $torrentId = $request->torrent_id;
        $thanks = Thank::query()
            ->where('torrentid', $torrentId)
            ->whereHas('user')
            ->with(['user'])
            ->paginate();
        $resource = ThankResource::collection($thanks);
//        $resource->additional([
//            'page_title' => nexus_trans('thank.index.page_title'),
//        ]);

        return $this->success($resource);
    }

    /**
     * Store a newly created resource in storage.
     * @param  \Illuminate\Http\Request  $request
     * @return  array<string, mixed>
     */
    public function store(Request $request)
    {
        $user = Auth::user();
        if (!$user instanceof User) {
            throw new \RuntimeException("unauthenticated");
        }
        $request->validate(['torrent_id' => 'required']);
        $torrentId = (int) $request->torrent_id;
        $torrent = Torrent::query()->findOrFail($torrentId, Torrent::$commentFields);
        $torrent->checkIsNormal();
        $torrentOwner = User::query()->findOrFail((int) $torrent->owner);
        if ($user->id == $torrentOwner->id) {
            throw new \LogicException("you can't thank to yourself");
        }
        $torrentOwner->checkIsNormal();
        if ($user->thank_torrent_logs()->where('torrentid', $torrentId)->exists()) {
            throw new \LogicException("you already thank this torrent");
        }

        $result = DB::transaction(function () use ($user, $torrentOwner, $torrent) {
            $thank = $user->thank_torrent_logs()->create(['torrentid' => $torrent->id]);
            $sayThanksBonus = \App\Support\Config\SiteConfig::current()->bonus->sayThanks();
            $receiveThanksBonus = \App\Support\Config\SiteConfig::current()->bonus->receiveThanks();
            if ($sayThanksBonus > 0) {
                $affectedRows = User::query()
                    ->where('id', $user->id)
                    ->where('seedbonus', $user->seedbonus)
                    ->increment('seedbonus', $sayThanksBonus);
                if ($affectedRows != 1) {
                    \App\Support\Logger::writeWithContext((string) ("affectedRows: {$affectedRows}, query: " . \App\Support\LegacyDb::lastQuery(false, 'json')), (string) 'error', (bool) false);
                    throw new \RuntimeException("increment user bonus fail.");
                }
            }
            if ($receiveThanksBonus > 0) {
                $affectedRows = User::query()
                    ->where('id', $torrentOwner->id)
                    ->where('seedbonus', $torrentOwner->seedbonus)
                    ->increment('seedbonus', $receiveThanksBonus);
                if ($affectedRows != 1) {
                    \App\Support\Logger::writeWithContext((string) ("affectedRows: {$affectedRows}, query: " . \App\Support\LegacyDb::lastQuery(false, 'json')), (string) 'error', (bool) false);
                    throw new \RuntimeException("increment owner bonus fail.");
                }
            }
            return $thank;
        });
        $resource = new ThankResource($result);
        return $this->success($resource, '说谢谢成功！');
    }

    /**
     * Display the specified resource.
     * @param  mixed  $id
     * @return  \Illuminate\Http\Response
     */
    public function show($id)
    {
        //
    
        return new \Illuminate\Http\Response('');
    }

    /**
     * Update the specified resource in storage.
     * @param  \Illuminate\Http\Request  $request
     * @param  mixed  $id
     * @return  \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        //
    
        return new \Illuminate\Http\Response('');
    }

    /**
     * Remove the specified resource from storage.
     * @param  mixed  $id
     * @return  \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        //
    
        return new \Illuminate\Http\Response('');
    }
}

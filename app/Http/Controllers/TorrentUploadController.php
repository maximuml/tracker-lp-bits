<?php

namespace App\Http\Controllers;

use App\Exceptions\TorrentAlreadyExistsException;
use App\Repositories\UploadRepository;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class TorrentUploadController extends Controller
{
    public function legacyStore(Request $request, UploadRepository $repository): RedirectResponse
    {
        try {
            $torrent = $repository->upload($request);
        } catch (TorrentAlreadyExistsException $e) {
            return redirect('details.php?id=' . $e->getTorrentId() . '&existed=1');
        }

        return redirect('details.php?id=' . $torrent->id . '&uploaded=1');
    }
}

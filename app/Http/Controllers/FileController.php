<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Resources\FileResource;
use App\Models\File;
use App\Repositories\TorrentDetailRepository;
use Illuminate\Http\Request;

class FileController extends Controller
{
    public function __construct(private readonly TorrentDetailRepository $torrentDetailRepository) {}

    /**
     * torrent file list
     *
     * @return array<string, mixed>
     */
    public function index(Request $request): array
    {
        $torrentId = $request->torrent_id;
        $files = $this->torrentDetailRepository->listFilesForTorrent($torrentId);
        $resource = FileResource::collection($files);

        return $this->success($resource);
    }
}

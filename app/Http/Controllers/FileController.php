<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Resources\FileResource;
use App\Models\File;
use Illuminate\Http\Request;

class FileController extends Controller
{
    /**
     * torrent file list
     *
     * @return array<string, mixed>
     */
    public function index(Request $request): array
    {
        $torrentId = $request->torrent_id;
        $files = File::query()->where('torrent', $torrentId)->get();
        $resource = FileResource::collection($files);

        return $this->success($resource);
    }
}

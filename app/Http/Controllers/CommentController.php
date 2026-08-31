<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\PrepareCommentRequest;
use App\Http\Resources\CommentResource;
use App\Models\Comment;
use App\Repositories\CommentRepository;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CommentController extends Controller
{
    /** @var mixed */
    private $repository;

    /**
     * @return mixed
     */
    public function __construct(CommentRepository $repository)
    {
        $this->repository = $repository;
    }

    /**
     * Display a listing of the resource.
     *
     * @return array<string, mixed>
     */
    public function index(Request $request): array
    {
        $comments = $this->repository->getList($request, Auth::user());
        $resource = CommentResource::collection($comments);

        return $this->success($resource);
    }

    /**
     * @return mixed
     */
    private function prepareData(Request $request)
    {
        $allTypes = array_keys(Comment::TYPE_MAPS);
        $data = [
            'type' => $request->type,
            'torrent' => $request->torrent_id,
            'text' => $request->text,
            'ori_text' => $request->text,
            'offer' => $request->offer_id,
            'request' => $request->request_id,
            'anonymous' => $request->boolean('anonymous'),
        ];
        $data = array_filter($data);
        $dataType = $data['type'] ?? '';
        foreach ($allTypes as $type) {
            if ($dataType == $type && empty($data[$type])) {
                throw new \InvalidArgumentException("require {$type}_id");
            }
        }

        return $data;
    }

    /**
     * Store a newly created resource in storage.
     *
     * @return array<string, mixed>
     */
    public function store(PrepareCommentRequest $request): array
    {
        $user = Auth::user();
        $comment = $this->repository->store($this->prepareData($request), $user);
        $resource = new CommentResource($comment);

        return $this->success($resource);
    }
}

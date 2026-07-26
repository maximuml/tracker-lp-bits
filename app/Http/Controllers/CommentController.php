<?php

namespace App\Http\Controllers;

use App\Http\Resources\CommentResource;
use App\Models\Comment;
use App\Repositories\CommentRepository;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class CommentController extends Controller
{
    /** @var  mixed */
    private $repository;

    /**
     * @param  \App\Repositories\CommentRepository  $repository
     * @return  mixed
     */
    public function __construct(CommentRepository $repository)
    {
        $this->repository = $repository;
    }
    /**
     * Display a listing of the resource.
     * @param  \Illuminate\Http\Request  $request
     * @return  array<string, mixed>
     */
    public function index(Request $request)
    {
        $comments = $this->repository->getList($request, Auth::user());
        $resource = CommentResource::collection($comments);
        return $this->success($resource);
    }

    /**
     * @param  \Illuminate\Http\Request  $request
     * @return  mixed
     */
    private function prepareData(Request $request)
    {
        $allTypes = array_keys(Comment::TYPE_MAPS);
        $request->validate([
            'type' => ['required', Rule::in($allTypes)],
            'torrent_id' => 'nullable|integer',
            'text' => 'required',
            'offer_id' => 'nullable|integer',
            'request_id' => 'nullable|integer',
            'anonymous' => 'nullable',
        ]);
        $data = [
            'type' => $request->type,
            'torrent' => $request->torrent_id,
            'text' => $request->text,
            'ori_text' => $request->text,
            'offer' => $request->offer_id,
            'request' => $request->request_id,
            'anonymous' => $request->anonymous,
        ];
        $data =  array_filter($data);
        foreach ($allTypes as $type) {
            if ($data['type'] == $type && empty($data[$type])) {
                throw new \InvalidArgumentException("require {$type}_id");
            }
        }
        return $data;
    }

    /**
     * Store a newly created resource in storage.
     * @param  \Illuminate\Http\Request  $request
     * @return  array<string, mixed>
     */
    public function store(Request $request)
    {
        $user = Auth::user();
        $comment = $this->repository->store($this->prepareData($request), $user);
        $resource = new CommentResource($comment);
        return $this->success($resource);
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

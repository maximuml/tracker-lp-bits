<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Resources\AgentAllowResource;
use App\Models\AgentAllow;
use App\Repositories\AgentAllowRepository;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AgentAllowController extends Controller
{
    private AgentAllowRepository $repository;

    /**
     * @return mixed
     */
    public function __construct(AgentAllowRepository $repository)
    {
        $this->repository = $repository;
    }

    /** @return  array<int|string, mixed> */
    private function getRules(): array
    {
        return [
            'family' => 'required|string',
            'start_name' => 'required|string',

            'peer_id_pattern' => 'required|string',
            'peer_id_match_num' => 'required|numeric',
            'peer_id_matchtype' => ['required', Rule::in(array_keys(AgentAllow::$matchTypes))],
            'peer_id_start' => 'required|string',

            'agent_pattern' => 'required|string',
            'agent_match_num' => 'required|numeric',
            'agent_matchtype' => ['required', Rule::in(array_keys(AgentAllow::$matchTypes))],
            'agent_start' => 'required|string',

            'exception' => ['required', Rule::in(['yes', 'no'])],
            'allowhttps' => ['required', Rule::in(['yes', 'no'])],
        ];
    }

    /**
     * Convert the legacy 'yes'/'no' string values for boolean columns
     * into actual booleans before persisting via the repository.
     *
     * @param  array<int|string, mixed>  $data
     * @return array<int|string, mixed>
     */
    private function normalizeBooleanFields(array $data): array
    {
        $data['exception'] = ($data['exception'] ?? 'no') === 'yes';
        $data['allowhttps'] = ($data['allowhttps'] ?? 'no') === 'yes';

        return $data;
    }

    /**
     * Display a listing of the resource.
     *
     * @return array<string, mixed>
     */
    public function index(Request $request): array
    {
        $result = $this->repository->getList($request->all());
        $resource = AgentAllowResource::collection($result);

        return $this->success($resource);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @return array<string, mixed>
     */
    public function store(Request $request): array
    {
        $request->validate($this->getRules());
        $data = $this->normalizeBooleanFields($request->all());
        $result = $this->repository->store($data);
        $resource = new AgentAllowResource($result);

        return $this->success($resource);
    }

    /**
     * Display the specified resource.
     *
     * @param  mixed  $id
     * @return array<string, mixed>
     */
    public function show($id): array
    {
        $result = AgentAllow::query()->findOrFail($id);
        $resource = new AgentAllowResource($result);

        return $this->success($resource);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  mixed  $id
     * @return array<string, mixed>
     */
    public function update(Request $request, $id): array
    {
        $request->validate($this->getRules());
        $data = $this->normalizeBooleanFields($request->all());
        $result = $this->repository->update($data, $id);
        $resource = new AgentAllowResource($result);

        return $this->success($resource);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  mixed  $id
     * @return array<string, mixed>
     */
    public function destroy($id): array
    {
        $result = $this->repository->delete($id);

        return $this->success($result);
    }

    /** @return  array<string, mixed> */
    public function all(): array
    {
        $result = AgentAllow::query()->orderBy('id', 'desc')->get();
        $resource = AgentAllowResource::collection($result);

        return $this->success($resource);
    }

    /**
     * @return array<string, mixed>
     */
    public function check(Request $request): array
    {
        $request->validate([
            'peer_id' => 'required|string',
            'agent' => 'required|string',
        ]);
        $result = $this->repository->checkClient($request->peer_id, $request->agent, true);

        return $this->success($result->toArray(), sprintf('Congratulations! the client is allowed by ID: %s', $result->id));
    }
}

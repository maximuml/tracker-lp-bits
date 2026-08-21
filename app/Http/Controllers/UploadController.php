<?php

namespace App\Http\Controllers;

use App\Http\Resources\SearchBoxResource;
use App\Models\SearchBox;
use App\Repositories\SearchBoxRepository;
use Illuminate\Http\Request;

class UploadController extends Controller
{
    /** @var mixed */
    private $searchBoxRepository;

    /**
     * @return mixed
     */
    public function __construct(SearchBoxRepository $searchBoxRepository)
    {
        $this->searchBoxRepository = $searchBoxRepository;
    }

    /**
     * @return array<string, mixed>
     */
    public function sections(Request $request)
    {
        $sections = $this->searchBoxRepository->listSections(SearchBox::listAuthorizedSectionId());
        $resource = SearchBoxResource::collection($sections);

        return $this->success($resource);
    }
}

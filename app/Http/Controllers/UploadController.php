<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Resources\SearchBoxResource;
use App\Models\SearchBox;
use App\Repositories\SearchBoxRepository;
use Illuminate\Http\Request;

class UploadController extends Controller
{
    private SearchBoxRepository $searchBoxRepository;

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
    public function sections(Request $request): array
    {
        $sections = $this->searchBoxRepository->listSections(SearchBox::listAuthorizedSectionId());
        $resource = SearchBoxResource::collection($sections);

        return $this->success($resource);
    }
}

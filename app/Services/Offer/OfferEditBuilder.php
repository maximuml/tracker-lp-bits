<?php

declare(strict_types=1);

namespace App\Services\Offer;

use App\Auth\Permission;
use App\Contracts\Repositories\OfferRepositoryInterface;
use App\Enums\Permission\PermissionEnum;
use App\Support\Category;
use App\Support\Input;
use App\Support\PageResponses;
use App\ViewModels\Offer\OfferCategoryOption;
use Illuminate\Http\Request;

/**
 * Builds the "edit offer" form data (ownership check + category options).
 */
final class OfferEditBuilder
{
    public function __construct(
        private readonly OfferRepositoryInterface $offerRepository,
    ) {}

    /**
     * @param  array<string, mixed>  $curUser
     * @return array<string, mixed>
     */
    public function build(array $curUser, int $userId, Request $request, mixed $browsecatmode): array
    {
        $id = (int) $request->query('id', 0);
        $offer = $this->offerRepository->findOffer($id);
        if (! $offer) {
            PageResponses::abort(__('offers.std_error'), __('offers.text_nothing_found'));
        }
        $num = $offer->toArray();

        if ($userId !== (int) ($num['userid'] ?? 0) && ! Permission::can(PermissionEnum::OFFER_MANAGE)) {
            PageResponses::abort(__('offers.std_error'), __('offers.std_cannot_edit_others_offer'));
        }

        $body = htmlspecialchars(Input::unescape((string) ($num['descr'] ?? '')));
        $id2 = (int) ($num['category'] ?? 0);

        $catOptions = [];
        foreach (Category::listByModeWithContext($browsecatmode) as $row) {
            $rowArr = (array) $row;
            $catOptions[] = new OfferCategoryOption((int) $rowArr['id'], (string) $rowArr['name']);
        }

        return [
            'id' => $id,
            'title' => htmlspecialchars(trim((string) ($num['name'] ?? ''))),
            'catId' => $id2,
            'catOptions' => $catOptions,
            'bodyContent' => $body,
        ];
    }
}

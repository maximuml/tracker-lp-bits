<?php

declare(strict_types=1);

namespace App\Services\Offer;

use App\Auth\Permission;
use App\Contracts\Repositories\OfferRepositoryInterface;
use App\DTOs\Auth\ActorContext;
use App\Enums\Permission\PermissionEnum;
use App\Support\Category;
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
     * @return array<string, mixed>
     */
    public function build(ActorContext $actor, int $userId, Request $request, mixed $browsecatmode): array
    {
        $id = (int) $request->query('id', 0);
        $offer = $this->offerRepository->findOffer($id);
        if (! $offer) {
            PageResponses::abort(__('offers.std_error'), __('offers.text_nothing_found'));
        }
        if ($userId !== (int) ($offer->userid ?? 0) && ! Permission::can(PermissionEnum::OFFER_MANAGE)) {
            PageResponses::abort(__('offers.std_error'), __('offers.std_cannot_edit_others_offer'));
        }

        $body = htmlspecialchars((string) ($offer->descr ?? ''));
        $id2 = (int) ($offer->category ?? 0);

        $catOptions = [];
        foreach (Category::listByModeWithContext($browsecatmode) as $row) {
            $rowArr = (array) $row;
            $catOptions[] = new OfferCategoryOption((int) $rowArr['id'], (string) $rowArr['name']);
        }

        return [
            'id' => $id,
            'title' => htmlspecialchars(trim((string) ($offer->name ?? ''))),
            'catId' => $id2,
            'catOptions' => $catOptions,
            'bodyContent' => $body,
        ];
    }
}

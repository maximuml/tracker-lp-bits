<?php

declare(strict_types=1);

namespace App\Services;

use App\Auth\Permission;
use App\Enums\Permission\PermissionEnum;
use App\Repositories\OfferRepository;
use App\Support\Category;
use App\Support\Form;
use App\Support\Html;
use App\Support\Input;
use App\Support\LegacyResponse;
use Illuminate\Http\Request;

final class OfferPageEditBuilder
{
    public function __construct(
        private readonly OfferRepository $offerRepository,
    ) {}

    /**
     * @param  array<string, mixed>  $lang
     * @param  array<string, mixed>  $curUser
     * @return array<string, mixed>
     */
    public function build(array $lang, array $curUser, int $userId, Request $request, mixed $browsecatmode): array
    {
        $id = (int) $request->query('id', 0);
        $offer = $this->offerRepository->findOffer($id);
        if (! $offer) {
            Html::stdMessage((string) ($lang['std_error'] ?? ''), (string) ($lang['text_nothing_found'] ?? ''));

            return [];
        }
        $num = $offer->toArray();

        if ($userId !== (int) ($num['userid'] ?? 0) && ! Permission::can(PermissionEnum::OFFER_MANAGE)) {
            LegacyResponse::abort((string) ($lang['std_error'] ?? ''), (string) ($lang['std_cannot_edit_others_offer'] ?? ''));
        }

        $body = htmlspecialchars(Input::unescape((string) ($num['descr'] ?? '')));
        $id2 = (int) ($num['category'] ?? 0);

        $catSelect = "<select name=\"category\">\n";
        foreach (Category::listByModeWithContext($browsecatmode) as $row) {
            $rowArr = (array) $row;
            $selected = (int) $rowArr['id'] === $id2 ? ' selected="selected"' : '';
            $catSelect .= '<option value="'.(int) $rowArr['id'].'"'.$selected.'>'.htmlspecialchars((string) $rowArr['name'])."</option>\n";
        }
        $catSelect .= "</select>\n";

        return [
            'id' => $id,
            'title' => htmlspecialchars(trim((string) ($num['name'] ?? ''))),
            'catSelect' => $catSelect,
            'bbcodeEditor' => Form::bbcodeEditor('compose', 'body', $body, false, 130, true),
        ];
    }
}

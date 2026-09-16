<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Auth\Permission;
use App\Contracts\Repositories\TagRepositoryInterface;
use App\Enums\Permission\PermissionEnum;
use App\Http\Requests\TorrentEditRequest;
use App\Models\Torrent;
use App\Models\User;
use App\Repositories\HitAndRunRepository;
use App\Repositories\SearchBoxSchemaBuilder;
use App\Repositories\TorrentDetailRepository;
use App\Repositories\TorrentEditRepository;
use App\Support\Category;
use App\Support\Config\SiteConfig;
use App\Support\CurrentUser;
use App\Support\CustomField;
use App\Support\Form;
use App\Support\Format;
use App\Support\Globals;
use App\Support\Html;
use App\Support\Input;
use App\Support\LegacyYesNo;
use App\Support\Locale;
use App\View\Components\BbcodeEditor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class TorrentEditController extends Controller
{
    public function __construct(
        private readonly SearchBoxSchemaBuilder $searchBoxSchemaBuilder,
        private readonly TagRepositoryInterface $tagRepository,
        private readonly HitAndRunRepository $hitAndRunRepository,
        private readonly CurrentUser $currentUser,
        private readonly Globals $globals,
        private readonly TorrentDetailRepository $torrentDetailRepository,
    ) {}

    public function legacy(Request $request): View|RedirectResponse
    {
        $user = Auth::guard('nexus-web')->user();
        if (! $user instanceof User) {
            $qs = $request->getQueryString();

            return redirect('/edit.php'.($qs ? '?'.$qs : ''));
        }

        $id = (int) $request->input('id', 0);
        if ($id <= 0) {
            abort(404);
        }

        $torrent = Torrent::query()->find($id);
        if (! $torrent instanceof Torrent) {
            abort(404);
        }

        $row = $this->torrentDetailRepository->getTorrent($id);
        if (empty($row)) {
            abort(404);
        }
        $sectionmode = (int) ($row['search_box_id'] ?? 0);
        $row['cat_mode'] = $sectionmode;

        if (empty($this->globals->get('lang_edit')) || empty($this->globals->get('lang_functions'))) {
            Input::setServerValue('SCRIPT_NAME', '/edit.php');
            require base_path(Locale::scriptFilePath((string) 'functions.php', (bool) false, (string) ''));
            $this->globals->set('lang_functions', $lang_functions ?? []);
            require base_path(Locale::scriptFilePath((string) '', (bool) false, (string) ''));
            $this->globals->set('lang_edit', $lang_edit ?? []);
        }

        $currentUser = $this->currentUser->get();
        $this->currentUser->set($currentUser);

        $langEdit = $this->globals->get('lang_edit') ?? [];
        $headTitle = ($langEdit['head_edit_torrent'] ?? '').'"'.$row['name'].'"';
        $cats = Category::listByModeWithContext($sectionmode);

        $canEdit = (int) ($currentUser['id'] ?? 0) === (int) ($row['owner'] ?? 0)
            || Permission::can(PermissionEnum::TORRENT_MANAGE);

        $priceRowHtml = null;
        if (Permission::can(PermissionEnum::TORRENT_SET_PRICE) && SiteConfig::current()->torrent->paidTorrentEnabled()) {
            $maxPrice = SiteConfig::current()->torrent->maxPrice();
            $pricePlaceholder = $maxPrice > 0
                ? Locale::trans('label.torrent.max_price_help', ['max_price' => $maxPrice], null)
                : '';
            $priceRowHtml = '<input type="number" min="0" name="price" value="'.$row['price'].'" placeholder="'.$pricePlaceholder.'" />&nbsp;&nbsp;'
                .Locale::trans('label.torrent.price_help', ['tax_factor' => SiteConfig::current()->torrent->taxFactor() * 100 .'%'], null);
        }

        $typeSelect = '<select name="type" data-mode=\''.$sectionmode.'\'>';
        foreach ($cats as $subrow) {
            $typeSelect .= '<option value="'.$subrow['id'].'"'
                .($subrow['id'] == $row['category'] ? ' selected="selected"' : '')
                .'>'.htmlspecialchars((string) $subrow['name']).'</option>'."\n";
        }
        $typeSelect .= '</select>';

        $checkRowHtml = '';
        $rowChecks = [];
        if (Permission::can(PermissionEnum::BE_ANONYMOUS) || Permission::can(PermissionEnum::TORRENT_MANAGE)) {
            $rowChecks[] = '<input type="hidden" name="anonymous" value="0" /><label><input type="checkbox" name="anonymous"'
                .(LegacyYesNo::isYes($row['anonymous'] ?? null) ? ' checked="checked"' : '')
                .' value="1" />'.($langEdit['checkbox_anonymous_note'] ?? '').'</label>';
        }
        if (Permission::can(PermissionEnum::TORRENT_MANAGE)) {
            array_unshift($rowChecks, '<input type="hidden" name="visible" value="0" /><label><input id="visible" type="checkbox" name="visible"'
                .(LegacyYesNo::isYes($row['visible'] ?? null) ? ' checked="checked"' : '')
                .' value="1" />'.($langEdit['checkbox_visible'] ?? '').'</label>');
        }
        if ($rowChecks !== []) {
            $checkRowHtml = implode('&nbsp;&nbsp;', $rowChecks);
        }

        $pickContentHtml = '';
        if (
            Permission::can(PermissionEnum::TORRENT_SET_STICKY)
            || (Permission::can(PermissionEnum::TORRENT_MANAGE) && LegacyYesNo::isYes($currentUser['picker'] ?? null))
        ) {
            if (Permission::can(PermissionEnum::TORRENT_ON_PROMOTION)) {
                $pickContentHtml .= '<b>'.($langEdit['row_special_torrent'] ?? '').'&nbsp;</b>'
                    .'<select name="sel_spstate" style="width: 100px;">'.Html::promotionSelection((int) $row['sp_state'], 0).'</select>&nbsp;&nbsp;&nbsp;'
                    .'<select name="promotion_time_type"><option value="0"'.($row['promotion_time_type'] == 0 ? ' selected="selected"' : '').'>'.($langEdit['select_use_global_setting'] ?? '').'</option><option value="1"'.($row['promotion_time_type'] == 1 ? ' selected="selected"' : '').'>'.($langEdit['select_forever'] ?? '').'</option><option value="2"'.($row['promotion_time_type'] == 2 ? ' selected="selected"' : '').'>'.($langEdit['select_until'] ?? '').'</option></select><span id="promotion_until_note"'.($row['promotion_time_type'] == 2 ? '' : ' class="nx-hidden"').'>';
                $pickContentHtml .= '<input type="text" id="promotionuntiltime" name="promotionuntil" style="width: 120px;" value="'.($row['promotion_until'] > $row['added'] ? $row['promotion_until'] : '').'" />';
                $pickContentHtml .= '&nbsp;('.($langEdit['text_ie_for'] ?? '').'<select name="promotionaddedtime"><option value="'.($row['promotion_until'] > $row['added'] ? $row['promotion_until'] : '').'">'.($langEdit['text_keep_current'] ?? '').'</option>';
                $addedTimeStamp = strtotime((string) $row['added']);
                foreach ([900, 1800, 3600, 5400, 7200, 14400, 21600, 28800, 43200, 64800, 86400, 129600, 259200, 604800, 1296000, 2592000, 7776000, 15552000, 31104000] as $seconds) {
                    $pickContentHtml .= '<option value="'.date('Y-m-d H:i:s', $addedTimeStamp + $seconds).'">'.Format::prettyTimeWithLocale($seconds).'</option>';
                }
                $pickContentHtml .= '</select>)&nbsp;'.($langEdit['text_promotion_until_note'] ?? '').'</span>&nbsp;&nbsp;';
            }
            if (Permission::can(PermissionEnum::TORRENT_SET_STICKY)) {
                if ($pickContentHtml !== '') {
                    $pickContentHtml .= '<br />';
                }
                $options = [];
                foreach (Torrent::listPosStates() as $key => $value) {
                    $options[] = '<option'.($row['pos_state'] == $key ? ' selected="selected"' : '').' value="'.$key.'">'.$value['text'].'</option>';
                }
                $pickContentHtml .= '<b>'.($langEdit['row_torrent_position'] ?? '').'&nbsp;</b>'
                    .'<select name="pos_state" style="width: 100px;">'.implode('', $options).'</select>&nbsp;&nbsp;&nbsp;';
                $pickContentHtml .= Form::datetimepickerInput('pos_state_until', $row['pos_state_until'], Locale::trans('label.deadline', [], null).'&nbsp;', ['require_files' => true]);
            }
        }

        $showDeleteForm = Permission::can(PermissionEnum::TORRENT_DELETE) && Permission::can(PermissionEnum::TORRENT_MANAGE);

        return view('torrent.edit', [
            'torrentId' => $id,
            'torrentRow' => $row,
            'currentUser' => $currentUser,
            'headTitle' => $headTitle,
            'tagIds' => $this->torrentDetailRepository->getTagIds($id),
            'cats' => $cats,
            'returnto' => (string) $request->input('returnto', ''),
            'requestUri' => is_string($request->server('REQUEST_URI')) ? $request->server('REQUEST_URI') : '',
            'taxonomySelect' => $this->searchBoxSchemaBuilder->renderTaxonomySelect($sectionmode, $row),
            'tagCheckbox' => $this->tagRepository->renderCheckbox($sectionmode, (array) $this->torrentDetailRepository->getTagIds($id)),
            'customFieldsHtml' => (new CustomField)->renderOnUploadPage($id, $sectionmode),
            'hitAndRunHtml' => $this->hitAndRunRepository->renderOnUploadPage($row['hr'] ?? 0, $sectionmode),
            'canEdit' => $canEdit,
            'priceRowHtml' => $priceRowHtml,
            'typeSelect' => $typeSelect,
            'checkRowHtml' => $checkRowHtml,
            'pickContentHtml' => $pickContentHtml,
            'showDeleteForm' => $showDeleteForm,
            'bbcodeEditorHtml' => BbcodeEditor::html(['form' => 'edittorrent', 'text' => 'descr', 'content' => (string) ($row['descr'] ?? ''), 'withPreview' => true]),
            'technicalInfoEnabled' => SiteConfig::current()->main->enableTechnicalInfo(),
            'modeClass' => 'mode_'.$sectionmode,
        ]);
    }

    public function legacyUpdate(TorrentEditRequest $request, TorrentEditRepository $repository): RedirectResponse
    {
        $torrent = $repository->update($request);

        $id = $torrent->id;
        $defaultUrl = "details.php?id=$id&edited=1";
        $returl = $request->input('returnto', $defaultUrl);

        return redirect($this->safeReturnUrl((string) $returl, $defaultUrl));
    }

    private function safeReturnUrl(string $returl, string $defaultUrl): string
    {
        $returl = trim($returl);
        if ($returl === '') {
            return $defaultUrl;
        }

        $parsed = parse_url($returl);
        if (! empty($parsed['scheme']) || ! empty($parsed['host']) || str_starts_with($returl, '//')) {
            return $defaultUrl;
        }

        return $returl;
    }
}

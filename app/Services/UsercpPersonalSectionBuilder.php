<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\TrackerUrl;
use App\Models\User;
use App\Repositories\UsercpLookupRepository;
use App\Support\Globals;
use App\Support\Html;
use App\Support\Http;
use App\Support\LegacyYesNo;
use App\Support\Strings;
use App\Support\Url;

/**
 * Builds the personal-settings section of the user control panel.
 * Split out of UsercpPageService to keep the page service under the
 * RepositorySizeTest baseline.
 */
final class UsercpPersonalSectionBuilder
{
    public function __construct(
        private readonly Globals $globals,
        private readonly UsercpLookupRepository $usercpLookupRepository,
    ) {}

    /**
     * @param  array<string, mixed>  $curUser
     * @return array<string, mixed>
     */
    public function build(array $curUser): array
    {
        $countryOptions = '';
        $countryRows = $this->usercpLookupRepository->getCountryOptions();
        foreach ($countryRows as $ct) {
            $countryOptions .= '<option value='.htmlspecialchars((string) $ct->id).''
                .(htmlspecialchars((string) ($curUser['country'] ?? '')) === htmlspecialchars((string) $ct->id) ? ' selected' : '')
                .'>'.htmlspecialchars((string) $ct->name)."</option>\n";
        }

        $trackerUrlOptions = '';
        $trackerUrlList = TrackerUrl::listAll();
        foreach ($trackerUrlList as $item) {
            $trackerUrlOptions .= '<option value='.htmlspecialchars((string) $item->id).''
                .(htmlspecialchars((string) ($curUser['tracker_url_id'] ?? '')) === htmlspecialchars((string) $item->id) ? ' selected' : '')
                .'>'.htmlspecialchars((string) $item->url)."</option>\n";
        }

        $bitbucketRows = $this->usercpLookupRepository->getBitbucketOptions();
        $bitbucketOptions = '';
        $baseUrl = (string) $this->globals->get('BASEURL', '');
        foreach ($bitbucketRows as $sor) {
            $bitbucketOptions .= '<option value="'.Http::protocolPrefix(Url::isSecure()).$baseUrl.'/bitbucket/'.htmlspecialchars((string) $sor->name).'">'.htmlspecialchars((string) $sor->name).'</option>';
        }

        $enableBitbucket = (string) $this->globals->get('enablebitbucket_main', '') === 'yes';
        $selectNoneLabel = __('legacy/usercp.select_none_selected');
        $selectChooseAvatar = __('legacy/usercp.select_choose_avatar');
        $selectNothing = __('legacy/usercp.select_nothing');
        $defaultAvatarUrl = Http::protocolPrefix(Url::isSecure()).$baseUrl.'/pic/default_avatar.png';

        $rowsHtml = '';
        $rowsHtml .= (string) Html::frowSmall(__('legacy/usercp.row_account_parked'), '<input type=checkbox name=parked'.(LegacyYesNo::isYes($curUser['parked'] ?? null) ? ' checked' : '').' value=yes>'.htmlspecialchars(__('legacy/usercp.checkbox_pack_my_account')).'<br /><font class=small size=1>'.(__('legacy/usercp.text_account_pack_note')).'</font>', 1, '', true);

        $pmY = (__('legacy/usercp.text_accept_pms')).'<input type=radio name=acceptpms'.(($curUser['acceptpms'] ?? '') === 'yes' ? ' checked' : '').' value=yes>'.htmlspecialchars(__('legacy/usercp.radio_all_except_blocks')).'<input type=radio name=acceptpms'.(($curUser['acceptpms'] ?? '') === 'friends' ? ' checked' : '').' value=friends>'.htmlspecialchars(__('legacy/usercp.radio_friends_only')).'<input type=radio name=acceptpms'.(($curUser['acceptpms'] ?? '') === 'no' ? ' checked' : '').' value=no>'.htmlspecialchars(__('legacy/usercp.radio_staff_only'))
            .'<br /><input type=checkbox name=deletepms'.(LegacyYesNo::isYes($curUser['deletepms'] ?? null) ? ' checked' : '').'> '.(__('legacy/usercp.checkbox_delete_pms'))
            .'<br /><input type=checkbox name=savepms'.(LegacyYesNo::isYes($curUser['savepms'] ?? null) ? ' checked' : '').'> '.(__('legacy/usercp.checkbox_save_pms'))
            .'<br /><input type=checkbox name=commentpm'.(LegacyYesNo::isYes($curUser['commentpm'] ?? null) ? ' checked' : '').' value=yes> '.(__('legacy/usercp.checkbox_pm_on_comments'));
        $notifs = (string) ($curUser['notifs'] ?? '');
        foreach (User::$notificationOptions as $option) {
            $pmY .= sprintf('<br /><input type="checkbox" name="notifs[%s]"%s value="yes" /> %s', $option, (is_null($curUser['notifs'] ?? null) || str_contains($notifs, "[{$option}]") ? ' checked' : ''), htmlspecialchars((string) __('legacy/usercp.checkbox_pm_on_'.$option)));
        }
        $rowsHtml .= (string) Html::frowSmall(__('legacy/usercp.row_pms'), $pmY, 1, '', true);

        $rowsHtml .= (string) Html::frowSmall(__('legacy/usercp.row_gender'), '<input type=radio name=gender'.(($curUser['gender'] ?? '') === 'N/A' ? ' checked' : '').' value=N/A>'.htmlspecialchars(__('legacy/usercp.radio_not_available')).'
<input type=radio name=gender'.(($curUser['gender'] ?? '') === 'Male' ? ' checked' : '').' value=Male>'.htmlspecialchars(__('legacy/usercp.radio_male')).'<input type=radio name=gender'.(($curUser['gender'] ?? '') === 'Female' ? ' checked' : '').' value=Female>'.htmlspecialchars(__('legacy/usercp.radio_female')), 1, '', true);

        $rowsHtml .= (string) Html::frowSmall(__('legacy/usercp.row_tracker_url'), "<select name=tracker_url_id>\n".$trackerUrlOptions."\n</select>".'<br /><font class=small size=1>'.(__('legacy/usercp.row_tracker_url_help')).'</font>', 1, '', true);
        $rowsHtml .= (string) Html::frowSmall(__('legacy/usercp.row_country'), "<select name=country>\n".'<option value=0>---- '.htmlspecialchars($selectNoneLabel)." ----</option>\n".$countryOptions."\n</select>", 1, '', true);

        $avatarCell = '<img src='.($curUser['avatar'] ?? '' ? "'".htmlspecialchars((string) $curUser['avatar'])."'" : "'".htmlspecialchars($defaultAvatarUrl)."'")." name='avatarimg'><br />
  <select name=savatar >
  <option value='".htmlspecialchars((string) ($curUser['avatar'] ?? ''))."'>".htmlspecialchars($selectChooseAvatar)."</option>
  <option value='".htmlspecialchars($defaultAvatarUrl)."'>".htmlspecialchars($selectNothing).'</option>
  '.$bitbucketOptions.'
  </select><input type=text name=avatar style="width: 400px" value="'.htmlspecialchars((string) ($curUser['avatar'] ?? '')).
  "\"><br />\n".htmlspecialchars(__('legacy/usercp.text_avatar_note')).($enableBitbucket ? (__('legacy/usercp.text_bitbucket_note')) : '');
        $rowsHtml .= (string) Html::frowSmall(__('legacy/usercp.row_avatar_url'), $avatarCell, 1, '', true);

        $rowsHtml .= (string) Html::frow(__('legacy/usercp.row_info'), '<textarea name="info" style="width:700px" rows="10" >'.htmlspecialchars((string) ($curUser['info'] ?? '')).'</textarea><br />'.(__('legacy/usercp.text_info_note')), 1, '', true);

        return [
            'formId' => 'form'.Strings::randomCode(6),
            'rowsHtml' => $rowsHtml,
            'enableBitbucket' => $enableBitbucket,
        ];
    }
}

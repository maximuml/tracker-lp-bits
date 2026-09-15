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
     * @param  array<string, mixed>  $lang
     * @param  array<string, mixed>  $curUser
     * @return array<string, mixed>
     */
    public function build(array $lang, array $curUser): array
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
        $selectNoneLabel = $lang['select_none_selected'] ?? 'None';
        $selectChooseAvatar = $lang['select_choose_avatar'] ?? 'Choose avatar';
        $selectNothing = $lang['select_nothing'] ?? 'Nothing';
        $defaultAvatarUrl = Http::protocolPrefix(Url::isSecure()).$baseUrl.'/pic/default_avatar.png';

        $rowsHtml = '';
        $rowsHtml .= (string) Html::frowSmall($lang['row_account_parked'] ?? 'Account parked', '<input type=checkbox name=parked'.(LegacyYesNo::isYes($curUser['parked'] ?? null) ? ' checked' : '').' value=yes>'.htmlspecialchars($lang['checkbox_pack_my_account'] ?? '').'<br /><font class=small size=1>'.($lang['text_account_pack_note'] ?? '').'</font>', 1, '', true);

        $pmY = ($lang['text_accept_pms'] ?? '').'<input type=radio name=acceptpms'.(($curUser['acceptpms'] ?? '') === 'yes' ? ' checked' : '').' value=yes>'.htmlspecialchars($lang['radio_all_except_blocks'] ?? '').'<input type=radio name=acceptpms'.(($curUser['acceptpms'] ?? '') === 'friends' ? ' checked' : '').' value=friends>'.htmlspecialchars($lang['radio_friends_only'] ?? '').'<input type=radio name=acceptpms'.(($curUser['acceptpms'] ?? '') === 'no' ? ' checked' : '').' value=no>'.htmlspecialchars($lang['radio_staff_only'] ?? '')
            .'<br /><input type=checkbox name=deletepms'.(LegacyYesNo::isYes($curUser['deletepms'] ?? null) ? ' checked' : '').'> '.($lang['checkbox_delete_pms'] ?? '')
            .'<br /><input type=checkbox name=savepms'.(LegacyYesNo::isYes($curUser['savepms'] ?? null) ? ' checked' : '').'> '.($lang['checkbox_save_pms'] ?? '')
            .'<br /><input type=checkbox name=commentpm'.(LegacyYesNo::isYes($curUser['commentpm'] ?? null) ? ' checked' : '').' value=yes> '.($lang['checkbox_pm_on_comments'] ?? '');
        $notifs = (string) ($curUser['notifs'] ?? '');
        foreach (User::$notificationOptions as $option) {
            $pmY .= sprintf('<br /><input type="checkbox" name="notifs[%s]"%s value="yes" /> %s', $option, (is_null($curUser['notifs'] ?? null) || str_contains($notifs, "[{$option}]") ? ' checked' : ''), htmlspecialchars($lang["checkbox_pm_on_{$option}"] ?? $option));
        }
        $rowsHtml .= (string) Html::frowSmall($lang['row_pms'] ?? 'PMs', $pmY, 1, '', true);

        $rowsHtml .= (string) Html::frowSmall($lang['row_gender'] ?? 'Gender', '<input type=radio name=gender'.(($curUser['gender'] ?? '') === 'N/A' ? ' checked' : '').' value=N/A>'.htmlspecialchars($lang['radio_not_available'] ?? '').'
<input type=radio name=gender'.(($curUser['gender'] ?? '') === 'Male' ? ' checked' : '').' value=Male>'.htmlspecialchars($lang['radio_male'] ?? '').'<input type=radio name=gender'.(($curUser['gender'] ?? '') === 'Female' ? ' checked' : '').' value=Female>'.htmlspecialchars($lang['radio_female'] ?? ''), 1, '', true);

        $rowsHtml .= (string) Html::frowSmall($lang['row_tracker_url'] ?? 'Tracker URL', "<select name=tracker_url_id>\n".$trackerUrlOptions."\n</select>".'<br /><font class=small size=1>'.($lang['row_tracker_url_help'] ?? '').'</font>', 1, '', true);
        $rowsHtml .= (string) Html::frowSmall($lang['row_country'] ?? 'Country', "<select name=country>\n".'<option value=0>---- '.htmlspecialchars($selectNoneLabel)." ----</option>\n".$countryOptions."\n</select>", 1, '', true);

        $avatarCell = '<img src='.($curUser['avatar'] ?? '' ? "'".htmlspecialchars((string) $curUser['avatar'])."'" : "'".htmlspecialchars($defaultAvatarUrl)."'")." name='avatarimg'><br />
  <select name=savatar >
  <option value='".htmlspecialchars((string) ($curUser['avatar'] ?? ''))."'>".htmlspecialchars($selectChooseAvatar)."</option>
  <option value='".htmlspecialchars($defaultAvatarUrl)."'>".htmlspecialchars($selectNothing).'</option>
  '.$bitbucketOptions.'
  </select><input type=text name=avatar style="width: 400px" value="'.htmlspecialchars((string) ($curUser['avatar'] ?? '')).
  "\"><br />\n".htmlspecialchars($lang['text_avatar_note'] ?? '').($enableBitbucket ? ($lang['text_bitbucket_note'] ?? '') : '');
        $rowsHtml .= (string) Html::frowSmall($lang['row_avatar_url'] ?? 'Avatar URL', $avatarCell, 1, '', true);

        $rowsHtml .= (string) Html::frow($lang['row_info'] ?? 'Info', '<textarea name="info" style="width:700px" rows="10" >'.htmlspecialchars((string) ($curUser['info'] ?? '')).'</textarea><br />'.($lang['text_info_note'] ?? ''), 1, '', true);

        return [
            'formId' => 'form'.Strings::randomCode(6),
            'rowsHtml' => $rowsHtml,
            'enableBitbucket' => $enableBitbucket,
        ];
    }
}

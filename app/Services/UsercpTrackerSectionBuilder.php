<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\UsercpLookupRepository;
use App\Support\Globals;
use App\Support\Html;
use App\Support\Input;
use App\Support\LegacyYesNo;
use App\Support\Locale;
use App\Support\SearchBox;
use App\Support\Strings;

/**
 * Builds the tracker/browse-settings section of the user control panel.
 * Split out of UsercpPageService to keep the page service under the
 * RepositorySizeTest baseline.
 */
final class UsercpTrackerSectionBuilder
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
        $showTooltipSetting = (string) $this->globals->get('enabletooltip_tweak', '') === 'yes';
        $browsecatmode = (int) $this->globals->get('browsecatmode', 1);

        $notifs = (string) ($curUser['notifs'] ?? '');
        $specialState = 0;
        for ($i = 7; $i >= 0; $i--) {
            if (str_contains($notifs, "[spstate={$i}]")) {
                $specialState = $i;
                break;
            }
        }

        $categories = SearchBox::buildCategoryTableWithContext($browsecatmode, 'yes', 'torrents.php?allsec=1', '', 3, $notifs, ['section_name' => true]);

        $ssSa = $this->usercpLookupRepository->getStylesheetOptions();
        ksort($ssSa);
        $stylesheetOptions = '';
        foreach ($ssSa as $ssName => $ssId) {
            $selected = ((int) $ssId === (int) ($curUser['stylesheet'] ?? 0)) ? ' selected' : '';
            $stylesheetOptions .= "<option value={$ssId}{$selected}>{$ssName}</option>\n";
        }

        $siteLangs = Locale::languageList('site_lang', true);
        $currentFolder = Locale::folderFromCookie((string) Input::cookieValue('c_lang_folder', ''), false);
        $langOptions = '';
        foreach ($siteLangs as $row) {
            $se = ($row['site_lang_folder'] === $currentFolder) ? ' selected' : '';
            $langOptions .= '<option value='.(int) $row['id'].$se.'>'.htmlspecialchars((string) $row['lang_name'])."</option>\n";
        }

        $rowsHtml = '';
        if ((string) $this->globals->get('emailnotify_smtp', '') === 'yes'
            && (string) $this->globals->get('smtptype', '') !== 'none') {
            $rowsHtml .= (string) Html::frowSmall(
                __('legacy/usercp.row_email_notification'),
                '<input type=checkbox name=pmnotif'.(str_contains($notifs, '[pm]') ? ' checked' : '').' value=yes> '.htmlspecialchars(__('legacy/usercp.checkbox_notification_received_pm'))."<br />\n"
                .'<input type=checkbox name=emailnotif'.(str_contains($notifs, '[email]') ? ' checked' : '').' value="yes" /> '.htmlspecialchars(__('legacy/usercp.checkbox_notification_default_categories')),
                1,
                '',
                true
            );
        }

        $categoriesCell = $categories.'<div style="height: 1px;background-color: #eee;margin: 10px 0"></div>'
            ."<table><caption><font class='big'>".htmlspecialchars(__('legacy/usercp.text_additional_selection')).'</font></caption><tr><td class=bottom><b>'.htmlspecialchars(__('legacy/usercp.text_show_dead_active')).'</b><br /><select name="incldead"><option value="0" '.(str_contains($notifs, '[incldead=0]') ? ' selected' : '').'>'.htmlspecialchars(__('legacy/usercp.select_including_dead')).'</option><option value="1" '.(str_contains($notifs, '[incldead=1]') || ! str_contains($notifs, 'incldead') ? ' selected' : '').'>'.htmlspecialchars(__('legacy/usercp.select_active')).'</option><option value="2" '.(str_contains($notifs, '[incldead=2]') ? ' selected' : '').'>'.htmlspecialchars(__('legacy/usercp.select_dead')).'</option></select></td><td class=bottom align=left><b>'.htmlspecialchars(__('legacy/usercp.text_show_special_torrents')).'</b><br /><select name="spstate"><option value="0" '.($specialState === 0 ? ' selected' : '').'>'.htmlspecialchars(__('legacy/usercp.select_all')).'</option>'.Html::promotionSelection($specialState).'</select></td><td class=bottom><b>'.htmlspecialchars(__('legacy/usercp.text_show_bookmarked')).'</b><br /><select name="inclbookmarked"><option value="0" '.(str_contains($notifs, '[inclbookmarked=0]') ? ' selected' : '').'>'.htmlspecialchars(__('legacy/usercp.select_all')).'</option><option value="1" '.(str_contains($notifs, '[inclbookmarked=1]') ? ' selected' : '').' >'.htmlspecialchars(__('legacy/usercp.select_bookmarked')).'</option><option value="2" '.(str_contains($notifs, '[inclbookmarked=2]') ? ' selected' : '').'>'.htmlspecialchars(__('legacy/usercp.select_bookmarked_exclude')).'</option></select></td></tr></table>';
        $rowsHtml .= (string) Html::frowSmall(__('legacy/usercp.row_browse_default_categories'), $categoriesCell, 1, '', true);

        $rowsHtml .= (string) Html::frowSmall(__('legacy/usercp.row_stylesheet'), "<select name=stylesheet>\n".$stylesheetOptions."\n</select>&nbsp;&nbsp;<font class=small>".htmlspecialchars(__('legacy/usercp.text_stylesheet_note')).'<a href="aboutnexus.php#stylesheet" ><b>'.htmlspecialchars(__('legacy/usercp.text_stylesheet_link')).'</b></a></font>.', 1, '', true);

        $rowsHtml .= (string) Html::frowSmall(__('legacy/usercp.row_font_size'), '<select name=fontsize><option value=small '.(($curUser['fontsize'] ?? '') === 'small' ? ' selected' : '').'>'.htmlspecialchars(__('legacy/usercp.select_small')).'</option><option value=medium '.(($curUser['fontsize'] ?? '') === 'medium' ? ' selected' : '').'>'.htmlspecialchars(__('legacy/usercp.select_medium')).'</option><option value=large '.(($curUser['fontsize'] ?? '') === 'large' ? ' selected' : '').'>'.htmlspecialchars(__('legacy/usercp.select_large')).'</option></select>', 1, '', true);

        $rowsHtml .= (string) Html::frowSmall(__('legacy/usercp.row_site_language'), "<select name=\"sitelanguage\">\n".$langOptions."\n</select>&nbsp;&nbsp;<font class=small>".htmlspecialchars(__('legacy/usercp.text_translation_note')).'<a href="aboutnexus.php#translation"><b>'.htmlspecialchars(__('legacy/usercp.text_translation_link')).'</b></a></font>.', 1, '', true);

        $rowsHtml .= (string) Html::frowSmall(__('legacy/usercp.row_pm_boxes'), htmlspecialchars(__('legacy/usercp.text_show')).'<input type=text name=pmnum size=5 value='.(int) ($curUser['pmnum'] ?? 0).' >'.htmlspecialchars(__('legacy/usercp.text_pms_per_page')), 1, '', true);

        if ((string) $this->globals->get('showshoutbox_main', '') === 'yes') {
            $rowsHtml .= (string) Html::frowSmall(__('legacy/usercp.row_shoutbox'), htmlspecialchars(__('legacy/usercp.text_show_last')).'<input type=text name=sbnum size=5 value='.(int) ($curUser['sbnum'] ?? 0).' >'.htmlspecialchars(__('legacy/usercp.text_messages_at_shoutbox')).'<br />'.htmlspecialchars(__('legacy/usercp.text_refresh_shoutbox_every')).'<input type=text name=sbrefresh size=5 value='.(int) ($curUser['sbrefresh'] ?? 0).' >'.htmlspecialchars(__('legacy/usercp.text_seconds')), 1, '', true);
        }

        $rowsHtml .= (string) Html::frowSmall(__('legacy/usercp.row_torrent_detail'), '<input type=checkbox name=showdescription'.(LegacyYesNo::isYes($curUser['showdescription'] ?? null) ? ' checked' : '').' value=yes>'.htmlspecialchars(__('legacy/usercp.text_show_description')), 1, '', true);
        $rowsHtml .= (string) Html::frowSmall(__('legacy/usercp.row_discuss'), '<input type=checkbox name=showcomment'.(LegacyYesNo::isYes($curUser['showcomment'] ?? null) ? ' checked' : '').' value=yes>'.htmlspecialchars(__('legacy/usercp.text_show_comments')), 1, '', true);
        $rowsHtml .= (string) Html::frowSmall(__('legacy/usercp.row_time_type'), '<input type=radio name=timetype '.(($curUser['timetype'] ?? '') === 'timeadded' ? ' checked' : '').' value=timeadded>'.htmlspecialchars(__('legacy/usercp.text_time_added')).'&nbsp;&nbsp;<input type=radio name=timetype '.(($curUser['timetype'] ?? '') === 'timealive' ? ' checked' : '').' value=timealive>'.htmlspecialchars(__('legacy/usercp.text_time_elapsed')).'<br />', 1, '', true);

        $browseCell = (__('legacy/usercp.text_browse_setting_warning')).'
<br /><b>'.(__('legacy/usercp.row_torrent_page')).': </b><br />'.htmlspecialchars(__('legacy/usercp.text_show')).'<input type=text size=5 name=torrentsperpage value='.(int) ($curUser['torrentsperpage'] ?? 0).'> '.htmlspecialchars(__('legacy/usercp.text_torrents_per_page')).htmlspecialchars(__('legacy/usercp.text_zero_equals_default')).'<br />'.
($showTooltipSetting ? '<b>'.htmlspecialchars(__('legacy/usercp.text_tooltip_type')).'</b>: <br /><input type=radio name=tooltip '.(($curUser['tooltip'] ?? '') === 'off' ? ' checked' : '').' value=off>'.htmlspecialchars(__('legacy/usercp.text_off')).'<br />' : '').
'<b>'.htmlspecialchars(__('legacy/usercp.text_append_words_to_torrents')).': </b><br /><input type=checkbox name=appendsticky '.(LegacyYesNo::isYes($curUser['appendsticky'] ?? null) ? ' checked' : '').' value=yes>'.htmlspecialchars(__('legacy/usercp.text_append_sticky')).'<br /><input type=checkbox name=appendnew '.(LegacyYesNo::isYes($curUser['appendnew'] ?? null) ? ' checked' : '').' value=yes>'.htmlspecialchars(__('legacy/usercp.text_append_new')).'<br />'.htmlspecialchars(__('legacy/usercp.text_torrents_on_promotion')).'<input type=radio name=appendpromotion '.(($curUser['appendpromotion'] ?? '') === 'highlight' ? ' checked' : '')." value='highlight'>".htmlspecialchars(__('legacy/usercp.text_highlight')).'<input type=radio name=appendpromotion '.(($curUser['appendpromotion'] ?? '') === 'word' ? ' checked' : '')." value='word'>".htmlspecialchars(__('legacy/usercp.text_append_words')).'<input type=radio name=appendpromotion '.(($curUser['appendpromotion'] ?? '') === 'icon' ? ' checked' : '')." value='icon'>".htmlspecialchars(__('legacy/usercp.text_append_icon')).'<input type=radio name=appendpromotion '.(($curUser['appendpromotion'] ?? '') === 'off' ? ' checked' : '')." value='off'>".htmlspecialchars(__('legacy/usercp.text_no_mark')).'<br /><input type=checkbox name=appendpicked '.(LegacyYesNo::isYes($curUser['appendpicked'] ?? null) ? ' checked' : '').' value=yes>'.htmlspecialchars(__('legacy/usercp.text_append_picked')).'<br />
<b>'.htmlspecialchars(__('legacy/usercp.text_show_action_icons')).': </b><br />'.'<input type=checkbox name=dlicon '.(LegacyYesNo::isYes($curUser['dlicon'] ?? null) ? ' checked' : '').' value=yes>'.htmlspecialchars(__('legacy/usercp.text_show_download_icon')).' <img class="download" src="pic/trans.gif"  alt="Download" /><br /><input type=checkbox name=bmicon '.(LegacyYesNo::isYes($curUser['bmicon'] ?? null) ? ' checked' : '').' value=yes>'.htmlspecialchars(__('legacy/usercp.text_show_bookmark_icon')).' <img class="bookmark" src="pic/trans.gif" alt="Bookmark" /><br />
<b>'.htmlspecialchars(__('legacy/usercp.text_comments_reviews')).': </b><br /><input type=checkbox name=showcomnum '.(LegacyYesNo::isYes($curUser['showcomnum'] ?? null) ? ' checked' : '').' value=yes>'.(__('legacy/usercp.text_show_comment_number')).($showTooltipSetting ? '<select name="showlastcom" style="width: 70px;"><option value="yes" '.(! LegacyYesNo::isNo($curUser['showlastcom'] ?? null) ? ' selected' : '').'>'.htmlspecialchars(__('legacy/usercp.select_with')).'</option><option value="no" '.(LegacyYesNo::isNo($curUser['showlastcom'] ?? null) ? ' selected' : '').'>'.htmlspecialchars(__('legacy/usercp.select_without')).'</option></select>'.(__('legacy/usercp.text_last_comment_on_tooltip')) : '');
        $rowsHtml .= (string) Html::frowSmall(__('legacy/usercp.row_browse_page'), $browseCell, 1, '', true);

        return [
            'formId' => 'form'.Strings::randomCode(6),
            'rowsHtml' => $rowsHtml,
        ];
    }
}

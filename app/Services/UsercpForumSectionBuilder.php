<?php

declare(strict_types=1);

namespace App\Services;

use App\Support\Globals;
use App\Support\Html;
use App\Support\LegacyYesNo;
use App\Support\Strings;

/**
 * Builds the forum-settings section of the user control panel.
 * Split out of UsercpPageService to keep the page service under the
 * RepositorySizeTest baseline.
 */
final class UsercpForumSectionBuilder
{
    public function __construct(
        private readonly Globals $globals,
    ) {}

    /**
     * @param  array<string, mixed>  $lang
     * @param  array<string, mixed>  $curUser
     * @return array<string, mixed>
     */
    public function build(array $lang, array $curUser): array
    {
        $showTooltipSetting = (string) $this->globals->get('enabletooltip_tweak', '') === 'yes';

        $rowsHtml = '';
        $rowsHtml .= (string) Html::trSmall($lang['row_topics_per_page'] ?? 'Topics per page', '<input type=text size=10 name=topicsperpage value='.(int) ($curUser['topicsperpage'] ?? 0).'>'.htmlspecialchars($lang['text_zero_equals_default'] ?? ''), 1, '', true);
        $rowsHtml .= (string) Html::trSmall($lang['row_posts_per_page'] ?? 'Posts per page', '<input type=text size=10 name=postsperpage value='.(int) ($curUser['postsperpage'] ?? 0).'> '.htmlspecialchars($lang['text_zero_equals_default'] ?? ''), 1, '', true);
        $rowsHtml .= (string) Html::trSmall($lang['row_view_avatars'] ?? 'View avatars', '<input type=checkbox name=avatars'.(LegacyYesNo::isYes($curUser['avatars'] ?? null) ? ' checked' : '').'>'.htmlspecialchars($lang['checkbox_low_bandwidth_note'] ?? ''), 1, '', true);
        $rowsHtml .= (string) Html::trSmall($lang['row_view_signatures'] ?? 'View signatures', '<input type=checkbox name=signatures'.(LegacyYesNo::isYes($curUser['signatures'] ?? null) ? ' checked' : '').'>'.htmlspecialchars($lang['checkbox_low_bandwidth_note'] ?? ''), 1, '', true);
        if ($showTooltipSetting) {
            $rowsHtml .= (string) Html::tr($lang['row_tooltip_last_post'] ?? 'Tooltip last post', '<input type=checkbox name=ttlastpost'.(LegacyYesNo::isYes($curUser['showlastpost'] ?? null) ? ' checked' : '').'>'.htmlspecialchars($lang['checkbox_last_post_note'] ?? ''), 1, '', true);
        }
        $rowsHtml .= (string) Html::trSmall($lang['row_click_on_topic'] ?? 'Click on topic', '<input type=radio name=clicktopic'.(($curUser['clicktopic'] ?? '') === 'firstpage' ? ' checked' : '').' value="firstpage">'.htmlspecialchars($lang['text_go_to_first_page'] ?? '').'<input type=radio name=clicktopic'.(($curUser['clicktopic'] ?? '') === 'lastpage' ? ' checked' : '').' value="lastpage">'.htmlspecialchars($lang['text_go_to_last_page'] ?? ''), 1, '', true);
        $rowsHtml .= (string) Html::trSmall($lang['row_forum_signature'] ?? 'Forum signature', '<textarea name=signature style="width:700px" rows=10>'.htmlspecialchars((string) ($curUser['signature'] ?? '')).'</textarea><br />'.($lang['text_signature_note'] ?? ''), 1, '', true);

        return [
            'formId' => 'form'.Strings::randomCode(6),
            'rowsHtml' => $rowsHtml,
            'showTooltipSetting' => $showTooltipSetting,
        ];
    }
}

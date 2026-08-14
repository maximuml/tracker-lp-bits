<?php
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING & ~E_DEPRECATED);

// Auto-generated legacy bridge shims
if (!isset($CURUSER)) $CURUSER = (array) (\App\Support\SupportContext::getUser() ?? []);
if (!isset($Cache)) $Cache = \App\Support\SupportContext::getCache();
if (!isset($BASEURL)) $BASEURL = \App\Support\SupportContext::getGlobal('BASEURL', '');
if (!isset($lang_faq)) $lang_faq = (array) (\App\Support\SupportContext::getGlobal('lang_faq') ?? []);
?>
<?php
        $title = $lang_faq['head_faq'] ?? '';
        \App\Support\Html::stdhead($title);
        \App\Support\Frame::mainFrameOpen();
        ?>
        <?php
$Cache->new_page('faq', 900, true);
$showFaq = ! $Cache->get_page();
$faq_categ = [];
if ($showFaq) {
    $Cache->add_whole_row();
    $lang_id = \App\Support\Locale::guestIdWithContext();
    $is_rulelang = \Nexus\Database\NexusDB::table('language')->where('id', $lang_id)->value('rule_lang');
    if (! $is_rulelang) {
        $lang_id = 6; // English
    }

    $res = \App\Models\Faq::query()->where('type', 'categ')->where('lang_id', $lang_id)->orderBy('order')->get()->toArray();
    foreach ($res as $arr) {
        $faq_categ[$arr['link_id']]['title'] = $arr['question'];
        $faq_categ[$arr['link_id']]['flag'] = $arr['flag'];
        $faq_categ[$arr['link_id']]['link_id'] = $arr['link_id'];
    }

    $res = \App\Models\Faq::query()->where('type', 'item')->where('lang_id', $lang_id)->get()->toArray();
    foreach ($res as $arr) {
        $faq_categ[$arr['categ']]['items'][$arr['id']]['question'] = $arr['question'];
        $faq_categ[$arr['categ']]['items'][$arr['id']]['answer'] = $arr['answer'];
        $faq_categ[$arr['categ']]['items'][$arr['id']]['flag'] = $arr['flag'];
        $faq_categ[$arr['categ']]['items'][$arr['id']]['link_id'] = $arr['link_id'];
    }
}
?>

<?php if ($showFaq && ! empty($faq_categ)): ?>
    <?php \App\Support\Html::beginFrame($lang_faq['text_welcome_to'].$SITENAME." - ".$SLOGAN); ?>
    <?php echo  sprintf($lang_faq['text_welcome_content_one'].sprintf($lang_faq['text_welcome_content_two'], \App\Models\Setting::getSiteName(), \App\Models\Setting::getSiteName())) ; ?>
    <?php \App\Support\Html::endFrame(); ?>

    <?php \App\Support\Html::beginFrame("<span id=\"top\">".$lang_faq['text_contents']."</span>"); ?>
    <ul>
    <?php foreach ($faq_categ as $id => $temp): ?>
        <?php if ($faq_categ[$id]['flag'] == "1"): ?>
            <li><a href="#id<?php echo htmlspecialchars((string) ( $faq_categ[$id]['link_id'] ), ENT_QUOTES, 'UTF-8'); ?>"><b><?php echo htmlspecialchars((string) ( $faq_categ[$id]['title'] ), ENT_QUOTES, 'UTF-8'); ?></b></a>
            <ul>
            <?php if (isset($faq_categ[$id]['items'])): ?>
                <?php foreach ($faq_categ[$id]['items'] as $id2 => $tempItem): ?>
                    <?php if ($faq_categ[$id]['items'][$id2]['flag'] == "1"): ?>
                        <li><a href="#id<?php echo htmlspecialchars((string) ( $faq_categ[$id]['items'][$id2]['link_id'] ), ENT_QUOTES, 'UTF-8'); ?>" class="faqlink"><?php echo htmlspecialchars((string) ( $faq_categ[$id]['items'][$id2]['question'] ), ENT_QUOTES, 'UTF-8'); ?></a></li>
                    <?php elseif ($faq_categ[$id]['items'][$id2]['flag'] == "2"): ?>
                        <li><a href="#id<?php echo htmlspecialchars((string) ( $faq_categ[$id]['items'][$id2]['link_id'] ), ENT_QUOTES, 'UTF-8'); ?>" class="faqlink"><?php echo htmlspecialchars((string) ( $faq_categ[$id]['items'][$id2]['question'] ), ENT_QUOTES, 'UTF-8'); ?></a> <img class="faq_updated" src="pic/trans.gif" alt="Updated" /></li>
                    <?php elseif ($faq_categ[$id]['items'][$id2]['flag'] == "3"): ?>
                        <li><a href="#id<?php echo htmlspecialchars((string) ( $faq_categ[$id]['items'][$id2]['link_id'] ), ENT_QUOTES, 'UTF-8'); ?>" class="faqlink"><?php echo htmlspecialchars((string) ( $faq_categ[$id]['items'][$id2]['question'] ), ENT_QUOTES, 'UTF-8'); ?></a> <img class="faq_new" src="pic/trans.gif" alt="New" /></li>
                    <?php endif; ?>
                <?php endforeach; ?>
            <?php endif; ?>
            </ul></li>
            <br />
        <?php endif; ?>
    <?php endforeach; ?>
    </ul>
    <?php \App\Support\Html::endFrame(); ?>

    <?php foreach ($faq_categ as $id => $temp): ?>
        <?php if ($faq_categ[$id]['flag'] == "1"): ?>
            <?php
            $frame = $faq_categ[$id]['title'] ." - <a href=\"#top\"><img class=\"top\" src=\"pic/trans.gif\" alt=\"Top\" title=\"Top\" /></a>";
            \App\Support\Html::beginFrame($frame);
            ?>
            <span id="id<?php echo htmlspecialchars((string) ( $faq_categ[$id]['link_id'] ), ENT_QUOTES, 'UTF-8'); ?>"></span>
            <?php if (isset($faq_categ[$id]['items'])): ?>
                <?php foreach ($faq_categ[$id]['items'] as $id2 => $tempItem): ?>
                    <?php if ($faq_categ[$id]['items'][$id2]['flag'] != "0"): ?>
                        <br /><span id="id<?php echo htmlspecialchars((string) ( $faq_categ[$id]['items'][$id2]['link_id'] ), ENT_QUOTES, 'UTF-8'); ?>"><b><?php echo htmlspecialchars((string) ( $faq_categ[$id]['items'][$id2]['question'] ), ENT_QUOTES, 'UTF-8'); ?></b></span><br />
                        <br /><?php echo  $faq_categ[$id]['items'][$id2]['answer'] ; ?><br /><br />
                    <?php endif; ?>
                <?php endforeach; ?>
            <?php endif; ?>
            <?php \App\Support\Html::endFrame(); ?>
        <?php endif; ?>
    <?php endforeach; ?>

    <?php
    $Cache->end_whole_row();
    $Cache->cache_page();
    ?>
<?php endif; ?>
<?php echo  $Cache->next_row() ; ?>

        <?php
        \App\Support\Frame::mainFrameClose();
        \App\Support\Html::stdfoot();
        ?>

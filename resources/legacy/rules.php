<?php
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING & ~E_DEPRECATED);

// Auto-generated legacy bridge shims
if (!isset($CURUSER)) $CURUSER = (array) (\App\Support\SupportContext::getUser() ?? []);
if (!isset($Cache)) $Cache = \App\Support\SupportContext::getCache();
if (!isset($BASEURL)) $BASEURL = \App\Support\SupportContext::getGlobal('BASEURL', '');
if (!isset($lang_rules)) $lang_rules = (array) (\App\Support\SupportContext::getGlobal('lang_rules') ?? []);
?>
<?php
        $title = $lang_rules['head_rules'] ?? '';
        \App\Support\Html::stdhead($title);
        \App\Support\Frame::mainFrameOpen();
        ?>
        <?php
$Cache->new_page('rules', 900, true);
$showRules = ! $Cache->get_page();
if ($showRules) {
    $Cache->add_whole_row();
    $lang_id = \App\Support\Locale::guestIdWithContext();
    $is_rulelang = \Nexus\Database\NexusDB::table('language')->where('id', $lang_id)->value('rule_lang');
    if (! $is_rulelang) {
        $lang_id = 6; // English
    }
    $rules = \Nexus\Database\NexusDB::table('rules')->where('lang_id', $lang_id)->orderBy('id')->get();
}
?>

<?php if ($showRules): ?>
    <?php \App\Support\Frame::mainFrameOpen(); ?>
    <?php foreach ($rules as $rule): ?>
        <?php $arr = (array) $rule; ?>
        <?php \App\Support\Html::beginFrame($arr['title'], false); ?>
        <?php echo  \App\Support\Format::formatComment($arr['text']) ; ?>
        <?php \App\Support\Html::endFrame(); ?>
    <?php endforeach; ?>
    <?php \App\Support\Frame::mainFrameClose(); ?>
<?php endif; ?>

        <?php
        \App\Support\Frame::mainFrameClose();
        \App\Support\Html::stdfoot();
        ?>

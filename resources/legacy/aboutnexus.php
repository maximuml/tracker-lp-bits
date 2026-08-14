<?php
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING & ~E_DEPRECATED);

// Auto-generated legacy bridge shims
if (!isset($CURUSER)) $CURUSER = (array) (\App\Support\SupportContext::getUser() ?? []);
if (!isset($Cache)) $Cache = \App\Support\SupportContext::getCache();
if (!isset($BASEURL)) $BASEURL = \App\Support\SupportContext::getGlobal('BASEURL', '');
if (!isset($lang_aboutnexus)) $lang_aboutnexus = (array) (\App\Support\SupportContext::getGlobal('lang_aboutnexus') ?? []);
?>
<?php
        $title = PROJECTNAME;
        \App\Support\Html::stdhead($title);
        \App\Support\Frame::mainFrameOpen();
        ?>
        <?php
\App\Support\Html::beginFrame("<span id=\"version\">".$lang_aboutnexus['text_version']."</span>");
echo sprintf ($lang_aboutnexus['text_version_note'], \App\Models\Setting::getSiteName(), PROJECTNAME);
?>
<table class="main" border="1" cellspacing="0" cellpadding="5" align="center">
    <?php \App\Support\Html::tr($lang_aboutnexus['text_main_version'], PROJECTNAME, 1); ?>
    <?php \App\Support\Html::tr($lang_aboutnexus['text_sub_version'], VERSION_NUMBER, 1); ?>
    <?php \App\Support\Html::tr($lang_aboutnexus['text_release_date'], RELEASE_DATE, 1); ?>
</table>
<br /><br />
<?php \App\Support\Html::endFrame(); ?>

<?php
$rows = \Nexus\Database\NexusDB::table('language')->orderBy('trans_state')->get();
\App\Support\Html::beginFrame("<span id=\"nexus\">".$lang_aboutnexus['text_nexus'].PROJECTNAME."</span>");
echo sprintf (PROJECTNAME.$lang_aboutnexus['text_nexus_note'], PROJECTNAME);
?>
<br /><br />
<?php \App\Support\Html::endFrame(); ?>

<?php
\App\Support\Html::beginFrame("<span id=\"authorization\">".$lang_aboutnexus['text_authorization']."</span>");
echo sprintf ($lang_aboutnexus['text_authorization_note'], PROJECTNAME);
?>
<br /><br />
<?php \App\Support\Html::endFrame(); ?>

<?php
\App\Support\Html::beginFrame("<span id=\"translation\">".$lang_aboutnexus['text_translation']."</span>");
print (PROJECTNAME.$lang_aboutnexus['text_translation_note']);
?>
<br /><br />
<table class="main" border="1" cellspacing="0" cellpadding="5" align="center">
    <tr>
        <td class="colhead"><?php echo htmlspecialchars((string) ( $lang_aboutnexus['text_flag'] ), ENT_QUOTES, 'UTF-8'); ?></td>
        <td class="colhead"><?php echo htmlspecialchars((string) ( $lang_aboutnexus['text_language'] ), ENT_QUOTES, 'UTF-8'); ?></td>
        <td class="colhead"><?php echo htmlspecialchars((string) ( $lang_aboutnexus['text_state'] ), ENT_QUOTES, 'UTF-8'); ?></td>
    </tr>
    <?php foreach (\Nexus\Database\NexusDB::table('language')->orderBy('trans_state')->get() as $row): ?>
        <?php $arr = (array) $row; ?>
        <tr>
            <td class="rowfollow"><img width="24" height="15" src="pic/flag/<?php echo htmlspecialchars((string) ( $arr['flagpic'] ), ENT_QUOTES, 'UTF-8'); ?>" alt="<?php echo htmlspecialchars((string) ( $arr['lang_name'] ), ENT_QUOTES, 'UTF-8'); ?>" title="<?php echo htmlspecialchars((string) ( $arr['lang_name'] ), ENT_QUOTES, 'UTF-8'); ?>" style="padding-bottom:1px;" /></td>
            <td class="rowfollow"><?php echo htmlspecialchars((string) ( $arr['lang_name'] ), ENT_QUOTES, 'UTF-8'); ?></td>
            <td class="rowfollow"><?php echo htmlspecialchars((string) ( $arr['trans_state'] ), ENT_QUOTES, 'UTF-8'); ?></td>
        </tr>
    <?php endforeach; ?>
</table>
<br /><br />
<?php \App\Support\Html::endFrame(); ?>

<?php
$rows = \Nexus\Database\NexusDB::table('stylesheets')->orderBy('id')->get();
\App\Support\Html::beginFrame("<span id=\"stylesheet\">".$lang_aboutnexus['text_stylesheet']."</span>");
echo sprintf ($lang_aboutnexus['text_stylesheet_note'], PROJECTNAME, \App\Models\Setting::getSiteName());
?>
<br /><br />
<table class="main" border="1" cellspacing="0" cellpadding="5" align="center">
    <tr>
        <td class="colhead"><?php echo htmlspecialchars((string) ( $lang_aboutnexus['text_name'] ), ENT_QUOTES, 'UTF-8'); ?></td>
        <td class="colhead"><?php echo htmlspecialchars((string) ( $lang_aboutnexus['text_designer'] ), ENT_QUOTES, 'UTF-8'); ?></td>
        <td class="colhead"><?php echo htmlspecialchars((string) ( $lang_aboutnexus['text_comment'] ), ENT_QUOTES, 'UTF-8'); ?></td>
    </tr>
    <?php foreach (\Nexus\Database\NexusDB::table('stylesheets')->orderBy('id')->get() as $row): ?>
        <?php $arr = (array) $row; ?>
        <tr>
            <td class="rowfollow"><?php echo htmlspecialchars((string) ( $arr['name'] ), ENT_QUOTES, 'UTF-8'); ?></td>
            <td class="rowfollow"><?php echo htmlspecialchars((string) ( $arr['designer'] ), ENT_QUOTES, 'UTF-8'); ?></td>
            <td class="rowfollow"><?php echo htmlspecialchars((string) ( $arr['comment'] ), ENT_QUOTES, 'UTF-8'); ?></td>
        </tr>
    <?php endforeach; ?>
</table>
<br /><br />
<?php \App\Support\Html::endFrame(); ?>

<?php
\App\Support\Html::beginFrame("<span id=\"contact\">".$lang_aboutnexus['text_contact'].PROJECTNAME."</span>");
print ($lang_aboutnexus['text_contact_note']);
?>
<br /><br />
<table class="main" border="1" cellspacing="0" cellpadding="5" align="center">
    <?php \App\Support\Html::tr($lang_aboutnexus['text_web_site'], '<a href="' . NEXUSPHPURL . '" target="_blank">' . NEXUSPHPURL . '</a>', 1); ?>
</table>
<br /><br />
<?php \App\Support\Html::endFrame(); ?>

        <?php
        \App\Support\Frame::mainFrameClose();
        \App\Support\Html::stdfoot();
        ?>

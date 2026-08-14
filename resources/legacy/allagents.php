<?php
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING & ~E_DEPRECATED);

// Auto-generated legacy bridge shims
if (!isset($CURUSER)) $CURUSER = (array) (\App\Support\SupportContext::getUser() ?? []);
if (!isset($Cache)) $Cache = \App\Support\SupportContext::getCache();
if (!isset($BASEURL)) $BASEURL = \App\Support\SupportContext::getGlobal('BASEURL', '');
if (!isset($lang_allagents)) $lang_allagents = (array) (\App\Support\SupportContext::getGlobal('lang_allagents') ?? []);
?>
<?php
        $title = 'All Clients';
        \App\Support\Html::stdhead($title);
        \App\Support\Frame::mainFrameOpen();
        ?>
        <?php
if (\App\Support\UserDisplay::currentClass() < UC_MODERATOR) {
    \App\Support\LegacyResponse::abort('Error', 'Permission denied.');
}
$agents = \Nexus\Database\NexusDB::table('peers')
    ->selectRaw('agent, count(*) as counts')
    ->groupBy('agent')
    ->orderBy('agent')
    ->get();
?>

<table align="center" border="3" cellspacing="0" cellpadding="5">
    <tr><td class="colhead">Client</td><td class="colhead">Counts</td></tr>
    <?php foreach ($agents as $row): ?>
        <?php $arr2 = (array) $row; ?>
        <tr><td align="left"><?php echo htmlspecialchars((string) ( $arr2['agent'] ), ENT_QUOTES, 'UTF-8'); ?></td><td align="left"><?php echo htmlspecialchars((string) ( $arr2['counts'] ), ENT_QUOTES, 'UTF-8'); ?></td></tr>
    <?php endforeach; ?>
</table>

        <?php
        \App\Support\Frame::mainFrameClose();
        \App\Support\Html::stdfoot();
        ?>

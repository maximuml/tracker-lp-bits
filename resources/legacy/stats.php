<?php
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING & ~E_DEPRECATED);

// Auto-generated legacy bridge shims
if (!isset($CURUSER)) $CURUSER = (array) (\App\Support\SupportContext::getUser() ?? []);
if (!isset($Cache)) $Cache = \App\Support\SupportContext::getCache();
if (!isset($BASEURL)) $BASEURL = \App\Support\SupportContext::getGlobal('BASEURL', '');
if (!isset($lang_stats)) $lang_stats = (array) (\App\Support\SupportContext::getGlobal('lang_stats') ?? []);
?>
<?php
        $title = 'Stats';
        \App\Support\Html::stdhead($title);
        \App\Support\Frame::mainFrameOpen();
        ?>
        <?php
$__server_PHP_SELF = \App\Support\SupportContext::getServerValue('PHP_SELF');
if (\App\Support\UserDisplay::currentClass() < UC_MODERATOR) {
    \App\Support\LegacyResponse::abort('Error', 'Permission denied.');
}
$n_tor = \Nexus\Database\NexusDB::table('torrents')->count();
$n_peers = \Nexus\Database\NexusDB::table('peers')->count();
$uporder = \App\Support\SupportContext::getQuery('uporder') ?? '';
$catorder = \App\Support\SupportContext::getQuery('catorder') ?? '';

if ($uporder == 'lastul') {
    $orderby = 'last DESC, name';
} elseif ($uporder == 'torrents') {
    $orderby = 'n_t DESC, name';
} elseif ($uporder == 'peers') {
    $orderby = 'n_p DESC, name';
} else {
    $orderby = 'name';
}

$uploaderQueryBase = \Nexus\Database\NexusDB::table('users as u')
    ->selectRaw('u.id, u.username AS name, MAX(t.added) AS last, COUNT(DISTINCT t.id) AS n_t, COUNT(p.id) AS n_p')
    ->leftJoin('torrents as t', 'u.id', '=', 't.owner')
    ->leftJoin('peers as p', 't.id', '=', 'p.torrent');
$first = clone $uploaderQueryBase;
$first->where('u.class', 3)->groupBy('u.id');
$second = clone $uploaderQueryBase;
$second->where('u.class', '>', 3)->groupBy('u.id');
$upers = $first->union($second)->orderByRaw($orderby)->get();
?>

<STYLE TYPE="text/css" MEDIA=screen>
  a.colheadlink:link, a.colheadlink:visited{
    font-weight: bold;
    color: #FFFFFF;
    text-decoration: none;
}

a.colheadlink:hover {
    text-decoration: underline;
}
</STYLE>

<?php if ($upers->isEmpty()): ?>
    <?php \App\Support\Html::stdMessage('Sorry...', 'No uploaders.'); ?>
<?php else: ?>
    <?php \App\Support\Html::beginFrame('Uploader Activity', true); ?>
    <?php \App\Support\Html::beginTable(); ?>
    <tr>
        <td class="colhead"><a href="<?php echo htmlspecialchars((string) ( $__server_PHP_SELF ), ENT_QUOTES, 'UTF-8'); ?>?uporder=uploader&catorder=<?php echo htmlspecialchars((string) ( $catorder ), ENT_QUOTES, 'UTF-8'); ?>" class="colheadlink">Uploader</a></td>
        <td class="colhead"><a href="<?php echo htmlspecialchars((string) ( $__server_PHP_SELF ), ENT_QUOTES, 'UTF-8'); ?>?uporder=lastul&catorder=<?php echo htmlspecialchars((string) ( $catorder ), ENT_QUOTES, 'UTF-8'); ?>" class="colheadlink">Last Upload</a></td>
        <td class="colhead"><a href="<?php echo htmlspecialchars((string) ( $__server_PHP_SELF ), ENT_QUOTES, 'UTF-8'); ?>?uporder=torrents&catorder=<?php echo htmlspecialchars((string) ( $catorder ), ENT_QUOTES, 'UTF-8'); ?>" class="colheadlink">Torrents</a></td>
        <td class="colhead">Perc.</td>
        <td class="colhead"><a href="<?php echo htmlspecialchars((string) ( $__server_PHP_SELF ), ENT_QUOTES, 'UTF-8'); ?>?uporder=peers&catorder=<?php echo htmlspecialchars((string) ( $catorder ), ENT_QUOTES, 'UTF-8'); ?>" class="colheadlink">Peers</a></td>
        <td class="colhead">Perc.</td>
    </tr>
    <?php foreach ($upers as $uper): ?>
        <?php
        $uper = (array) $uper;
        $lastCell = $uper['last'] ? $uper['last'] . ' (' . \App\Support\Format::getElapsedTime(strtotime($uper['last'])) . ' ago)' : '---';
        $nT = $uper['n_t'];
        $nP = $uper['n_p'];
        $percT = $n_tor > 0 ? number_format(100 * $nT / $n_tor, 1) . '%' : '---';
        $percP = $n_peers > 0 ? number_format(100 * $nP / $n_peers, 1) . '%' : '---';
        ?>
        <tr>
            <td><?php echo  \App\Support\UserDisplay::username($uper['id']) ; ?></td>
            <td><?php echo htmlspecialchars((string) ( $lastCell ), ENT_QUOTES, 'UTF-8'); ?></td>
            <td align="right"><?php echo htmlspecialchars((string) ( $nT ), ENT_QUOTES, 'UTF-8'); ?></td>
            <td align="right"><?php echo htmlspecialchars((string) ( $percT ), ENT_QUOTES, 'UTF-8'); ?></td>
            <td align="right"><?php echo htmlspecialchars((string) ( $nP ), ENT_QUOTES, 'UTF-8'); ?></td>
            <td align="right"><?php echo htmlspecialchars((string) ( $percP ), ENT_QUOTES, 'UTF-8'); ?></td>
        </tr>
    <?php endforeach; ?>
    <?php \App\Support\Html::endTable(); ?>
    <?php \App\Support\Html::endFrame(); ?>
<?php endif; ?>

<?php
if ($n_tor == 0) {
    $showCategories = false;
} else {
    $showCategories = true;
    if ($catorder == 'lastul') {
        $orderby = 'last DESC, c.name';
    } elseif ($catorder == 'torrents') {
        $orderby = 'n_t DESC, c.name';
    } elseif ($catorder == 'peers') {
        $orderby = 'n_p DESC, name';
    } else {
        $orderby = 'c.name';
    }
    $cats = \Nexus\Database\NexusDB::table('categories as c')
        ->selectRaw('c.name, MAX(t.added) AS last, COUNT(DISTINCT t.id) AS n_t, COUNT(p.id) AS n_p')
        ->leftJoin('torrents as t', 't.category', '=', 'c.id')
        ->leftJoin('peers as p', 't.id', '=', 'p.torrent')
        ->groupBy('c.id')
        ->orderByRaw($orderby)
        ->get();
}
?>

<?php if (! $showCategories): ?>
    <?php \App\Support\Html::stdMessage('Sorry...', 'No categories defined!'); ?>
<?php else: ?>
    <?php \App\Support\Html::beginFrame('Category Activity', true); ?>
    <?php \App\Support\Html::beginTable(); ?>
    <tr>
        <td class="colhead"><a href="<?php echo htmlspecialchars((string) ( $__server_PHP_SELF ), ENT_QUOTES, 'UTF-8'); ?>?uporder=<?php echo htmlspecialchars((string) ( $uporder ), ENT_QUOTES, 'UTF-8'); ?>&catorder=category" class="colheadlink">Category</a></td>
        <td class="colhead"><a href="<?php echo htmlspecialchars((string) ( $__server_PHP_SELF ), ENT_QUOTES, 'UTF-8'); ?>?uporder=<?php echo htmlspecialchars((string) ( $uporder ), ENT_QUOTES, 'UTF-8'); ?>&catorder=lastul" class="colheadlink">Last Upload</a></td>
        <td class="colhead"><a href="<?php echo htmlspecialchars((string) ( $__server_PHP_SELF ), ENT_QUOTES, 'UTF-8'); ?>?uporder=<?php echo htmlspecialchars((string) ( $uporder ), ENT_QUOTES, 'UTF-8'); ?>&catorder=torrents" class="colheadlink">Torrents</a></td>
        <td class="colhead">Perc.</td>
        <td class="colhead"><a href="<?php echo htmlspecialchars((string) ( $__server_PHP_SELF ), ENT_QUOTES, 'UTF-8'); ?>?uporder=<?php echo htmlspecialchars((string) ( $uporder ), ENT_QUOTES, 'UTF-8'); ?>&catorder=peers" class="colheadlink">Peers</a></td>
        <td class="colhead">Perc.</td>
    </tr>
    <?php foreach ($cats as $cat): ?>
        <?php
        $cat = (array) $cat;
        $lastCell = $cat['last'] ? $cat['last'] . ' (' . \App\Support\Format::getElapsedTime(strtotime($cat['last'])) . ' ago)' : '---';
        $nT = $cat['n_t'];
        $nP = $cat['n_p'];
        $percT = $n_tor > 0 ? number_format(100 * $nT / $n_tor, 1) . '%' : '---';
        $percP = $n_peers > 0 ? number_format(100 * $nP / $n_peers, 1) . '%' : '---';
        ?>
        <tr>
            <td class="rowhead"><?php echo htmlspecialchars((string) ( $cat['name'] ), ENT_QUOTES, 'UTF-8'); ?></td>
            <td><?php echo htmlspecialchars((string) ( $lastCell ), ENT_QUOTES, 'UTF-8'); ?></td>
            <td align="right"><?php echo htmlspecialchars((string) ( $nT ), ENT_QUOTES, 'UTF-8'); ?></td>
            <td align="right"><?php echo htmlspecialchars((string) ( $percT ), ENT_QUOTES, 'UTF-8'); ?></td>
            <td align="right"><?php echo htmlspecialchars((string) ( $nP ), ENT_QUOTES, 'UTF-8'); ?></td>
            <td align="right"><?php echo htmlspecialchars((string) ( $percP ), ENT_QUOTES, 'UTF-8'); ?></td>
        </tr>
    <?php endforeach; ?>
    <?php \App\Support\Html::endTable(); ?>
    <?php \App\Support\Html::endFrame(); ?>
<?php endif; ?>

        <?php
        \App\Support\Frame::mainFrameClose();
        \App\Support\Html::stdfoot();
        ?>

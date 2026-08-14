<?php
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING & ~E_DEPRECATED);

// Auto-generated legacy bridge shims
if (!isset($CURUSER)) $CURUSER = (array) (\App\Support\SupportContext::getUser() ?? []);
if (!isset($Cache)) $Cache = \App\Support\SupportContext::getCache();
if (!isset($BASEURL)) $BASEURL = \App\Support\SupportContext::getGlobal('BASEURL', '');
if (!isset($lang_warned)) $lang_warned = (array) (\App\Support\SupportContext::getGlobal('lang_warned') ?? []);
?>
<?php
        $title = 'Warned Users';
        \App\Support\Html::stdhead($title);
        \App\Support\Frame::mainFrameOpen();
        ?>
        <?php
if (\App\Support\UserDisplay::currentClass() < UC_MODERATOR) {
    \App\Support\LegacyResponse::abort('Sorry', 'Access denied.');
}
$count = \App\Models\User::query()->where('warned', 'yes')->count();
$warned = number_format($count);
\App\Support\Html::beginFrame("Warned Users: ({$warned})", true);
\App\Support\Html::beginTable();
$rows = \App\Models\User::query()
    ->where('warned', 1)
    ->where('enabled', 'yes')
    ->orderByRaw('(uploaded/downloaded)')
    ->get()
    ->map(fn ($r) => $r->getAttributes());
?>

<table border="1" width="675" cellspacing="0" cellpadding="2">
    <form action="nowarn.php" method="post">
        <tr align="center">
            <td class="colhead" width="90">User Name</td>
            <td class="colhead" width="70">Registered</td>
            <td class="colhead" width="75">Last access</td>
            <td class="colhead" width="75">User Class</td>
            <td class="colhead" width="70">Downloaded</td>
            <td class="colhead" width="70">UpLoaded</td>
            <td class="colhead" width="45">Ratio</td>
            <td class="colhead" width="125">End<br>Of Warning</td>
            <td class="colhead" width="65">Remove<br>Warning</td>
            <td class="colhead" width="65">Disable<br>Account</td>
        </tr>
        <?php foreach ($rows as $arr): ?>
            <?php
            if ($arr['added'] == '0000-00-00 00:00:00' || $arr['added'] == null) {
                $arr['added'] = '-';
            }
            if ($arr['last_access'] == '0000-00-00 00:00:00' || $arr['added'] == null) {
                $arr['last_access'] = '-';
            }
            if ($arr["downloaded"] != 0) {
                $ratio = number_format($arr["uploaded"] / $arr["downloaded"], 3);
            } else {
                $ratio = "---";
            }
            $ratio = "<font color=" . \App\Support\Ratio::color($ratio) . ">$ratio</font>";
            $uploaded = \App\Support\Format::size($arr["uploaded"]);
            $downloaded = \App\Support\Format::size($arr["downloaded"]);
            $added = substr($arr['added'],0,10);
            $last_access = substr($arr['last_access'],0,10);
            $class = \App\Support\UserClass::name($arr["class"], false, true, true);
            ?>
            <tr>
                <td align="left"><?php echo  \App\Support\UserDisplay::username($arr['id']) ; ?></td>
                <td align="center"><?php echo htmlspecialchars((string) ( $added ), ENT_QUOTES, 'UTF-8'); ?></td>
                <td align="center"><?php echo htmlspecialchars((string) ( $last_access ), ENT_QUOTES, 'UTF-8'); ?></td>
                <td align="center"><?php echo  $class ; ?></td>
                <td align="center"><?php echo  $downloaded ; ?></td>
                <td align="center"><?php echo  $uploaded ; ?></td>
                <td align="center"><?php echo  $ratio ; ?></td>
                <td align="center"><?php echo htmlspecialchars((string) ( $arr['warneduntil'] ), ENT_QUOTES, 'UTF-8'); ?></td>
                <td bgcolor="#008000" align="center"><input type="checkbox" name="usernw[]" value="<?php echo htmlspecialchars((string) ( $arr['id'] ), ENT_QUOTES, 'UTF-8'); ?>"></td>
                <td bgcolor="#FF000" align="center"><input type="checkbox" name="desact[]" value="<?php echo htmlspecialchars((string) ( $arr['id'] ), ENT_QUOTES, 'UTF-8'); ?>"></td>
            </tr>
        <?php endforeach; ?>
        <?php if (\App\Support\UserDisplay::currentClass() >= UC_ADMINISTRATOR): ?>
            <tr><td colspan="10" align="right"><input type="submit" name="submit" value="Apply Changes"></td></tr>
            <input type="hidden" name="nowarned" value="nowarned">
        <?php endif; ?>
    </form>
</table>
<p><?php echo  $pagemenu ?? '' ; ?><br><?php echo  $browsemenu ?? '' ; ?></p>

        <?php
        \App\Support\Frame::mainFrameClose();
        \App\Support\Html::stdfoot();
        ?>

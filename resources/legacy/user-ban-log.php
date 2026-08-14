<?php
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING & ~E_DEPRECATED);

// Auto-generated legacy bridge shims
if (!isset($CURUSER)) $CURUSER = (array) (\App\Support\SupportContext::getUser() ?? []);
if (!isset($Cache)) $Cache = \App\Support\SupportContext::getCache();
if (!isset($BASEURL)) $BASEURL = \App\Support\SupportContext::getGlobal('BASEURL', '');
if (!isset($lang_user_ban_log)) $lang_user_ban_log = (array) (\App\Support\SupportContext::getGlobal('lang_user_ban_log') ?? []);
?>
<?php
        $title = 'User ban log';
        \App\Support\Html::stdhead($title);
        \App\Support\Frame::mainFrameOpen();
        ?>
        <?php
$__server_REQUEST_URI = \App\Support\SupportContext::getServerValue('REQUEST_URI');
$query = \App\Models\UserBanLog::query();
$q = htmlspecialchars(\App\Support\SupportContext::getRequestInput('q') ?? '');
if (! empty($q)) {
    $query->where('username', 'like', "%{$q}%");
}
$total = (clone $query)->count();
$perPage = 50;
[$paginationTop, $paginationBottom, $limit, $offset] = \App\Support\Pagination::pager($perPage, $total, '?');
$rows = (clone $query)->offset($offset)->take($perPage)->orderBy('id', 'desc')->get()->toArray();
$header = [
    'id' => 'ID',
    'uid' => 'UID',
    'username' => 'Username',
    'reason' => 'Reason',
    'created_at' => 'Created at',
];
$table = \App\Support\Html::buildTable($header, $rows);
$q = htmlspecialchars($q);
?>

<div>
    <h1 style="text-align: center">User ban log</h1>
    <form id="filterForm" action="<?php echo htmlspecialchars((string) ( $__server_REQUEST_URI ), ENT_QUOTES, 'UTF-8'); ?>" method="get">
        <input id="q" type="text" name="q" value="<?php echo htmlspecialchars((string) ( $q ), ENT_QUOTES, 'UTF-8'); ?>" placeholder="username">
        <input type="submit">
        <input type="reset" onclick="document.getElementById('q').value='';document.getElementById('filterForm').submit();">
    </form>
</div>

<?php echo  $table ; ?>
<?php echo  $paginationBottom ; ?>

        <?php
        \App\Support\Frame::mainFrameClose();
        \App\Support\Html::stdfoot();
        ?>

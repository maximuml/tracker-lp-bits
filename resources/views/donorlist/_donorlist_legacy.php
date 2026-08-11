<?php
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING & ~E_DEPRECATED);
if (\App\Support\UserDisplay::currentClass() > UC_MODERATOR) {
	$count = \App\Models\User::query()->where('donor', 'yes')->count();

	list($pagertop, $pagerbottom, , $offset, $rpp) = \App\Support\Pagination::pager(50, $count, "donorlist.php?");
	\App\Support\Html::stdhead("Donorlist");
	if ($count == 0)
	\App\Support\Frame::mainFrameOpen();
	// ===================================
	$users = number_format($count);
	\App\Support\Html::beginFrame("Donor List ($users)", true);
	\App\Support\Html::beginTable();
	echo $pagerbottom;
?>
<form method="post">
<tr><td class="colhead">ID</td><td class="colhead" align="left">Username</td><td class="colhead" align="left">e-mail</td><td class="colhead" align="left">Joined</td><td class="colhead" align="left">How much?</td></tr>
<?php

$rows = \App\Models\User::query()
    ->where('donor', 'yes')
    ->orderByDesc('id')
    ->offset($offset)
    ->limit($rpp)
    ->get(['id', 'username', 'email', 'added', 'donated'])
    ->map(fn ($r) => (array) $r);
// ------------------
foreach ($rows as $arr) {
	echo "<tr><td>" . $arr['id'] . "</td><td align=\"left\">" . \App\Support\UserDisplay::username($arr['id']) . "</td><td align=\"left\"><a href=mailto:" . $arr['email'] . ">" . $arr['email'] . "</a></td><td align=\"left\">" . $arr['added'] . "</a></td><td align=\"left\">$" . $arr['donated'] . "</td></tr>";
}
?>

</form>
<?php
// ------------------
\App\Support\Html::endTable();
\App\Support\Html::endFrame();
// ===================================
\App\Support\Frame::mainFrameClose();
\App\Support\Html::stdfoot();
}
else {
	\App\Support\LegacyResponse::abort("Sorry", "Access denied!");
}

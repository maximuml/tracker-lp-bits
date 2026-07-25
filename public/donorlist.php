<?php
require "../include/bittorrent.php";
dbconn();
loggedinorreturn();

if (get_user_class() > UC_MODERATOR) {
	$count = \App\Models\User::query()->where('donor', 'yes')->count();

	list($pagertop, $pagerbottom, , $offset, $rpp) = pager(50, $count, "donorlist.php?");
	stdhead("Donorlist");
	if ($count == 0)
	begin_main_frame();
	// ===================================
	$users = number_format($count);
	begin_frame("Donor List ($users)", true);
	begin_table();
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
	echo "<tr><td>" . $arr['id'] . "</td><td align=\"left\">" . get_username($arr['id']) . "</td><td align=\"left\"><a href=mailto:" . $arr['email'] . ">" . $arr['email'] . "</a></td><td align=\"left\">" . $arr['added'] . "</a></td><td align=\"left\">$" . $arr['donated'] . "</td></tr>";
}
?>

</form>
<?php
// ------------------
end_table();
end_frame();
// ===================================
end_main_frame();
stdfoot();
}
else {
	stderr("Sorry", "Access denied!");
}

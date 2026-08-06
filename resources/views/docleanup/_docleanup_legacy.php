<?php
extract($GLOBALS, EXTR_SKIP);
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING & ~E_DEPRECATED);


if (get_user_class() < UC_SYSOP) {
echo 'forbidden';
return;
}

echo "<html><head><title>".$lang_docleanup['title']."</title></head><body>";
echo "<p>";
echo $lang_docleanup['running'] . "<br />";
if (isset($_GET['forceall']) && $_GET['forceall']) {
	$forceall = 1;
} else {
	$forceall = 0;
    echo $lang_docleanup['force'] . '<br />';
}
echo "</p>";
$tstart = getmicrotime();
require_once("include/cleanup.php");
print("<p>".docleanup($forceall, 1)."</p>");
$tend = getmicrotime();
$totaltime = ($tend - $tstart);
printf ($lang_docleanup['time_consumed']."<br />", $totaltime);
echo $lang_docleanup['done']."<br />";
echo "</body></html>";

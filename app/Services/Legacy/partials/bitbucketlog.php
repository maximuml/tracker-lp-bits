<?php
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING & ~E_DEPRECATED);

if (!isset($CURUSER)) $CURUSER = (array) (\App\Support\SupportContext::getUser() ?? []);
if (!isset($BASEURL)) $BASEURL = \App\Support\SupportContext::getGlobal('BASEURL', '');
if (!isset($lang_functions)) $lang_functions = (array) (\App\Support\SupportContext::getGlobal('lang_functions') ?? []);

$rows = (array) ($rows ?? []);
$count = (int) ($count ?? 0);
$pagertop = (string) ($pagertop ?? '');
$pagerbottom = (string) ($pagerbottom ?? '');
$userDisplayMap = (array) ($userDisplayMap ?? []);
$imageDimensions = (array) ($imageDimensions ?? []);

$isModerator = \App\Support\UserDisplay::currentClass() >= (defined('UC_MODERATOR') ? \constant('UC_MODERATOR') : 0);

print("<h1>BitBucket Log</h1>\n");
print("Total Images Stored: $count");
echo $pagertop;

if (empty($rows)) {
    print("<b>BitBucket Log is empty</b>\n");
} else {
    print("<table align='center' border='0' cellspacing='0' cellpadding='5'>\n");
    foreach ($rows as $row) {
        $id = (int) ($row['id'] ?? 0);
        $name = (string) ($row['name'] ?? '');
        $owner = (int) ($row['owner'] ?? 0);
        $added = (string) ($row['added'] ?? '');
        $date = substr($added, 0, strpos($added, ' '));
        $time = substr($added, strpos($added, ' ') + 1);
        $url = str_replace(' ', '%20', htmlspecialchars("bitbucket/$name"));
        $dim = (array) ($imageDimensions[$id] ?? ['width' => 0, 'height' => 0]);
        $width = (int) ($dim['width'] ?? 0);
        $height = (int) ($dim['height'] ?? 0);

        print("<tr>");
        print("<td><center><a href=$url><img src=\"" . $url . "\" border=0 onLoad='SetSize(this, 400)'></a></center>");
        print("Uploaded by: " . ($userDisplayMap[$owner] ?? \App\Support\UserDisplay::username($owner)) . "<br />");
        print("(#{$id}) Filename: $name ($width&nbsp;x&nbsp;$height)");
        if ($isModerator) {
            print(" <b><a href=\"?delete={$id}\">[Delete]</a></b><br />");
        }
        print("Added: $date $time");
        print("</tr>");
    }
    print("</table>");
}
echo $pagerbottom;

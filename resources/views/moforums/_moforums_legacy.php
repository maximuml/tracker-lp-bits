<?php
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING & ~E_DEPRECATED);

$__server_PHP_SELF = \App\Support\SupportContext::getServerValue('PHP_SELF');
\App\Auth\Permission::assertCan(\App\Enums\Permission\PermissionEnum::FORUM_MANAGE);

$act = \App\Support\SupportContext::getQuery('action') ?? '';
if (!$act) {
    $act = "forum";
}
$id = intval(\App\Support\SupportContext::getQuery('id') ?? 0);
$PHP_SELF = $__server_PHP_SELF;
$user = $CURUSER;
$prefix = '';

if ($act == "del") {
    \App\Auth\Permission::assertCan(\App\Enums\Permission\PermissionEnum::FORUM_MANAGE);
    if (!$id) { header("Location: $PHP_SELF?action=forum");
	return;}
    \Nexus\Database\NexusDB::table('overforums')->where('id', $id)->delete();
    $Cache->delete_value('overforums_list');
    header("Location: $PHP_SELF?action=forum");
	return;
}

if (((\App\Support\SupportContext::getPost('action') !== null)) && \App\Support\SupportContext::getPost('action') == "editforum") {
    \App\Auth\Permission::assertCan(\App\Enums\Permission\PermissionEnum::FORUM_MANAGE);
    $name = \App\Support\SupportContext::getPost('name');
    $desc = \App\Support\SupportContext::getPost('desc');
    if (!$name && !$desc && !$id) { header("Location: $PHP_SELF?action=forum");
	return;}
    \Nexus\Database\NexusDB::table('overforums')->where('id', (int)\App\Support\SupportContext::getPost('id'))->update([
        'sort' => \App\Support\SupportContext::getPost('sort'),
        'name' => \App\Support\SupportContext::getPost('name'),
        'description' => \App\Support\SupportContext::getPost('desc'),
        'minclassview' => \App\Support\SupportContext::getPost('viewclass'),
    ]);
    $Cache->delete_value('overforums_list');
    header("Location: $PHP_SELF?action=forum");
	return;
}

if (((\App\Support\SupportContext::getPost('action') !== null)) && \App\Support\SupportContext::getPost('action') == "addforum") {
    \App\Auth\Permission::assertCan(\App\Enums\Permission\PermissionEnum::FORUM_MANAGE);
    $name = trim(\App\Support\SupportContext::getPost('name'));
    $desc = trim(\App\Support\SupportContext::getPost('desc'));
    if (!$name && !$desc) {
        header("Location: $PHP_SELF?action=forum");
	return;
    }
    \Nexus\Database\NexusDB::table('overforums')->insert([
        'sort' => \App\Support\SupportContext::getPost('sort'),
        'name' => \App\Support\SupportContext::getPost('name'),
        'description' => \App\Support\SupportContext::getPost('desc'),
        'minclassview' => \App\Support\SupportContext::getPost('viewclass'),
    ]);
    $Cache->delete_value('overforums_list');
    header("Location: $PHP_SELF?action=forum");
	return;
}

stdhead($lang_moforums['head_overforum_management']);
begin_main_frame();

$maxSort = \Nexus\Database\NexusDB::table('overforums')->count();

if ($act == "forum") {
    ?>
<h2 class=transparentbg align=center><a class=faqlink href=forummanage.php><?php echo $lang_moforums['text_forum_management']?></a><b>--></b><?php echo $lang_moforums['text_overforum_management']?></h2>
<br />
<?php
    echo '<table width="100%"  border="0" align="center" cellpadding="2" cellspacing="0">';
    echo "<tr><td class=colhead align=left>".$lang_moforums['col_name']."</td><td class=colhead>".$lang_moforums['col_viewed_by']."</td><td class=colhead>".$lang_moforums['col_modify']."</td></tr>";
    $overforums = \Nexus\Database\NexusDB::table('overforums')->orderBy('sort')->get();
    if ($overforums->isEmpty()) {
        print "<tr><td colspan=3>".$lang_moforums['text_no_records_found']."</td></tr>";
    } else {
        foreach ($overforums as $forumRow) {
            $row = (array) $forumRow;
            echo "<tr><td><a href=forums.php?action=forumview&forid=".$row["id"]."><b>".htmlspecialchars($row["name"])."</b></a><br />".$row["description"]."</td>";
            echo "<td>" . \App\Support\UserClass::name($row["minclassview"],false,true,true) . "</td><td><b><a href=\"".$PHP_SELF."?action=editforum&id=".$row["id"]."\">".$lang_moforums['text_edit']."</a>&nbsp;|&nbsp;<a href=\"javascript:confirm_delete('".$row["id"]."', '".$lang_moforums['js_sure_to_delete_overforum']."', '');\"><font color=red>".$lang_moforums['text_delete']."</font></a></b></td></tr>";
        }
    }
    echo "</table>";
    ?>
<br /><br />
<form method=post action="<?php echo $PHP_SELF;?>">
<table width="100%"  border="0" cellspacing="0" cellpadding="3" align="center">
<tr align="center">
    <td colspan="2" class=colhead><?php echo $lang_moforums['text_new_overforum']?></td>
  </tr>
  <tr>
    <td><b><?php echo $lang_moforums['text_overforum_name']?></td>
    <td><input name="name" type="text" style="width: 200px" maxlength="60"></td>
  </tr>
  <tr>
    <td><b><?php echo $lang_moforums['text_overforum_description']?></td>
    <td><input name="desc" type="text" style="width: 400px" maxlength="200"></td>
  </tr>
    <tr>
    <td><b><?php echo $lang_moforums['text_minimum_view_permission']?></td>
    <td>
    <select name=viewclass>
<?php
    $maxclass = \App\Support\UserDisplay::currentClass();
    for ($i = 0; $i <= $maxclass; ++$i)
        print("<option value=$i" . ($user["class"] == $i ? " selected" : "") . ">$prefix" . \App\Support\UserClass::name($i,false,true,true) . "\n");
?>
	</select>
    </td>
  </tr>
    <tr>
    <td><b><?php echo $lang_moforums['text_overforum_order']?></td>
    <td>
    <select name=sort>
<?php
    $maxclass = $maxSort + 1;
    for ($i = 0; $i <= $maxclass; ++$i)
        print("<option value=$i>$i \n");
?>
	</select>
    <?php echo $lang_moforums['text_overforum_order_note']?></td>
  </tr>
  <tr align="center">
    <td colspan="2"><input type="hidden" name="action" value="addforum"><input type="submit" name="Submit" value="<?php echo $lang_moforums['submit_make_overforum']?>"></td>
  </tr>
</table>
</form>
<?php
}

if ($act == "editforum") {
    $id = intval(\App\Support\SupportContext::getQuery("id") ?? 0);
    $row = (array) \Nexus\Database\NexusDB::table('overforums')->where('id', $id)->first();
    if (!$row) {
        print $lang_moforums['text_no_records_found'];
    } else {
?>
<h2 class=transparentbg align=center><a class=faqlink href=forummanage.php><?php echo $lang_moforums['text_forum_management']?></a><b>--></b><a class=faqlink href=moforums.php><?php echo $lang_moforums['text_overforum_management']?></a><b>--></b><?php echo $lang_moforums['text_edit_overforum']?></h2><br />
<form method=post action="<?php echo $PHP_SELF;?>">
<table width="100%"  border="0" cellspacing="0" cellpadding="3" align="center">
<tr align="center">
    <td colspan="2" class=colhead><?php echo $lang_moforums['text_edit_overforum']?> -- <?php echo htmlspecialchars($row["name"]);?></td>
  </tr>
    <td><b><?php echo $lang_moforums['text_overforum_name']?></td>
    <td><input name="name" type="text" style="width: 200px" maxlength="60" value="<?php echo $row["name"];?>"></td>
  </tr>
  <tr>
    <td><b><?php echo $lang_moforums['text_overforum_description']?></td>
    <td><input name="desc" type="text" style="width: 400px" maxlength="200" value="<?php echo $row["description"];?>"></td>
  </tr>
    <tr>
    <td><b><?php echo $lang_moforums['text_minimum_view_permission']?></td>
    <td>
    <select name=viewclass>
<?php
    $maxclass = \App\Support\UserDisplay::currentClass();
    for ($i = 0; $i <= $maxclass; ++$i)
        print("<option value=$i" . ($row["minclassview"] == $i ? " selected" : "") . ">$prefix" . \App\Support\UserClass::name($i,false,true,true) . "\n");
?>
	</select>
    </td>
  </tr>
    <tr>
    <td><b><?php echo $lang_moforums['text_overforum_order']?></td>
    <td>
    <select name=sort>
<?php
    $maxclass = $maxSort + 1;
    for ($i = 0; $i <= $maxclass; ++$i)
        print("<option value=$i" . ($row["sort"] == $i ? " selected" : "") . ">$i \n");
?>
	</select>
	<?php echo $lang_moforums['text_overforum_order_note']?>
    </td>
  </tr>
  <tr align="center">
    <td colspan="2"><input type="hidden" name="action" value="editforum"><input type="hidden" name="id" value="<?php echo $id;?>"><input type="submit" name="Submit" value="<?php echo $lang_moforums['submit_edit_overforum']?>"></td>
  </tr>
</table>
<?php
    }
}
end_main_frame();
stdfoot();

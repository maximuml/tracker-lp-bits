<?php
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING & ~E_DEPRECATED);


$__server_PHP_SELF = \App\Support\SupportContext::getServerValue('PHP_SELF');
$prefix = '';
$user = $CURUSER;
$PHP_SELF = $__server_PHP_SELF;

\App\Auth\Permission::assertCan(\App\Enums\Permission\PermissionEnum::FORUM_MANAGE);

$overforums = \Nexus\Database\NexusDB::table('overforums')->orderBy('sort')->get(['id', 'name']);
$maxSort = \Nexus\Database\NexusDB::table('forums')->count();

// DELETE FORUM ACTION
if (((\App\Support\SupportContext::getQuery('action') !== null)) && \App\Support\SupportContext::getQuery('action') == "del") {
	$id = intval(\App\Support\SupportContext::getQuery('id') ?? 0);
	if (!$id) {
		header("Location: forummanage.php");
	return;
	}
	$topics = \Nexus\Database\NexusDB::table('topics')->where('forumid', $id)->get(['id']);
	foreach ($topics as $topic) {
		\Nexus\Database\NexusDB::table('posts')->where('topicid', $topic->id)->delete();
	}
	\Nexus\Database\NexusDB::table('topics')->where('forumid', $id)->delete();
	\Nexus\Database\NexusDB::table('forums')->where('id', $id)->delete();
	\Nexus\Database\NexusDB::table('forummods')->where('forumid', $id)->delete();
	$Cache->delete_value('forums_list');
	$Cache->delete_value('forum_moderator_array');
	header("Location: forummanage.php");
	return;
}

//EDIT FORUM ACTION
elseif (((\App\Support\SupportContext::getPost('action') !== null)) && \App\Support\SupportContext::getPost('action') == "editforum") {
	$name = \App\Support\SupportContext::getPost('name');
	$desc = \App\Support\SupportContext::getPost('desc');
	$id = \App\Support\SupportContext::getPost('id');
	if (!$name && !$desc && !$id) {
		header("Location: " . get_protocol_prefix() . "$BASEURL/forummanage.php");
	return;
	}
	if (!empty(\App\Support\SupportContext::getPost("moderator"))) {
		$moderator = \App\Support\SupportContext::getPost("moderator");
		\App\Support\Forum::setModerators($moderator,$id);
	}
	else{
		\Nexus\Database\NexusDB::table('forummods')->where('forumid', $id)->delete();
	}
	\Nexus\Database\NexusDB::table('forums')->where('id', $id)->update([
	    'sort' => \App\Support\SupportContext::getPost('sort'),
	    'name' => \App\Support\SupportContext::getPost('name'),
	    'description' => \App\Support\SupportContext::getPost('desc'),
	    'forid' => \App\Support\SupportContext::getPost('overforums'),
	    'minclassread' => \App\Support\SupportContext::getPost('readclass'),
	    'minclasswrite' => \App\Support\SupportContext::getPost('writeclass'),
	    'minclasscreate' => \App\Support\SupportContext::getPost('createclass'),
	]);
	$Cache->delete_value('forums_list');
	$Cache->delete_value('forum_moderator_array');
	header("Location: forummanage.php");
	return;
}

//ADD FORUM ACTION
elseif (((\App\Support\SupportContext::getPost('action') !== null)) && \App\Support\SupportContext::getPost('action') == "addforum") {
	$name = (\App\Support\SupportContext::getPost('name'));
	$desc = (\App\Support\SupportContext::getPost('desc'));
	if (!$name && !$desc) {
		header("Location: " . get_protocol_prefix() . "$BASEURL/forummanage.php");
	return;
	}
	$id = \Nexus\Database\NexusDB::table('forums')->insertGetId([
	    'sort' => \App\Support\SupportContext::getPost('sort'),
	    'name' => \App\Support\SupportContext::getPost('name'),
	    'description' => \App\Support\SupportContext::getPost('desc'),
	    'minclassread' => \App\Support\SupportContext::getPost('readclass'),
	    'minclasswrite' => \App\Support\SupportContext::getPost('writeclass'),
	    'minclasscreate' => \App\Support\SupportContext::getPost('createclass'),
	    'forid' => \App\Support\SupportContext::getPost('overforums'),
	]);
	$Cache->delete_value('forums_list');
	if (\App\Support\SupportContext::getPost("moderator")){
		$moderator = \App\Support\SupportContext::getPost("moderator");
		\App\Support\Forum::setModerators($moderator,$id);
	}
	header("Location: forummanage.php");
	return;
}

// SHOW FORUMS WITH FORUM MANAGEMENT TOOLS
\App\Support\Html::stdhead($lang_forummanage['head_forum_management']);
\App\Support\Frame::mainFrameOpen();
if (((\App\Support\SupportContext::getQuery('action') !== null)) && \App\Support\SupportContext::getQuery('action') == "editforum") {
	//EDIT PAGE FOR THE FORUMS
	$id = intval(\App\Support\SupportContext::getQuery("id") ?? 0);
	$row = (array) \Nexus\Database\NexusDB::table('forums')->where('id', $id)->first();
	if (!$row) {
		print ($lang_forummanage['text_no_records_found']);
	} else {
?>
<h1 align=center><a class=faqlink href=forummanage.php><?php echo $lang_forummanage['text_forum_management']?></a><b>--></b><?php echo $lang_forummanage['text_edit_forum']?></h2>
<br />
<form method=post action="<?php echo $__server_PHP_SELF;?>">
<table width="100%"  border="0" cellspacing="0" cellpadding="3" align="center">
<tr align="center">
    <td colspan="2" class=colhead><?php echo $lang_forummanage['text_edit_forum']?> -- <?php echo htmlspecialchars($row["name"]);?></td>
  </tr>

    <td><b><?php echo $lang_forummanage['row_forum_name']?></td>
    <td><input name="name" type="text" style="width: 200px" maxlength="60" value="<?php echo $row["name"];?>"></td>
  </tr>
  <tr>
    <td><b><?php echo $lang_forummanage['row_forum_description']?></td>
    <td><input name="desc" type="text" style="width: 400px" maxlength="200" value="<?php echo $row["description"];?>"></td>
  </tr>


    <tr>
    <td><b><?php echo $lang_forummanage['row_overforum']?></td>
    <td>
    <select name=overforums>
    <?php
            $forid = $row["forid"];
            foreach ($overforums as $arr) {
             $name = $arr->name;
             $i = $arr->id;
            print("<option value=$i" . ($forid == $i ? " selected" : "") . ">$prefix" . $name . "\n");
            }
?>
        </select>
    </td>
  </tr>
<?php
		$username = \App\Support\Forum::moderatorsWithContext($row['id'],true);
?>
  <tr><td><b><?php echo $lang_forummanage['row_moderator']?></b></td><td><input name="moderator" type="text" style="width: 200px" maxlength="200" value="<?php echo $username?>">&nbsp;<?php echo $lang_forummanage['text_moderator_note']?></td></tr>
    <tr>
    <td><b><?php echo $lang_forummanage['row_minimum_read_permission']?></td>
    <td>
    <select name=readclass>
<?php
             $maxclass = \App\Support\UserDisplay::currentClass();
          for ($i = 0; $i <= $maxclass; ++$i)
            print("<option value=$i" . ($row["minclassread"] == $i ? " selected" : "") . ">$prefix" . \App\Support\UserClass::name($i,false,true,true));
?>
        </select>
    </td>
  </tr>
  <tr>
    <td><b><?php echo $lang_forummanage['row_minimum_write_permission']?></td>
    <td><select name=writeclass>
<?php
              $maxclass = \App\Support\UserDisplay::currentClass();
          for ($i = 0; $i <= $maxclass; ++$i)
            print("<option value=$i" . ($row["minclasswrite"] == $i ? " selected" : "") . ">$prefix" . \App\Support\UserClass::name($i,false,true,true) . "\n");
?>
        </select></td>
  </tr>
  <tr>
    <td><b><?php echo $lang_forummanage['row_minimum_create_topic_permission']?></td>
    <td><select name=createclass>
<?php
            $maxclass = \App\Support\UserDisplay::currentClass();
          for ($i = 0; $i <= $maxclass; ++$i)
            print("<option value=$i" . ($row["minclasscreate"] == $i ? " selected" : "") . ">$prefix" . \App\Support\UserClass::name($i,false,true,true) . "\n");
?>
        </select></td>
  </tr>
    <tr>
    <td><b><?php echo $lang_forummanage['row_forum_order']?></td>
    <td>
    <select name=sort>
<?php
            $maxclass = $maxSort + 1;
          for ($i = 0; $i <= $maxclass; ++$i)
            print("<option value=$i" . ($row["sort"] == $i ? " selected" : "") . ">$i \n");
?>
        </select>
    <?php echo $lang_forummanage['text_forum_order_note']?></td>
  </tr>

  <tr align="center">
    <td colspan="2"><input type="hidden" name="action" value="editforum"><input type="hidden" name="id" value="<?php echo $id;?>"><input type="submit" name="Submit" value="<?php echo $lang_forummanage['submit_edit_forum']?>" class="btn"></td>
  </tr>
</table>
<?php
	}
}
//
elseif (((\App\Support\SupportContext::getQuery('action') !== null)) && \App\Support\SupportContext::getQuery('action') == "newforum"){
?>
<h2 class=transparentbg align=center><a class=faqlink href=forummanage.php><?php echo $lang_forummanage['text_forum_management']?></a><b>--></b><?php echo $lang_forummanage['text_add_forum']?></h2>
<br />
<form method=post action="<?php echo $__server_PHP_SELF;?>">
<table width="100%"  border="0" cellspacing="0" cellpadding="3" align="center">
<tr align="center">
    <td colspan="2" class=colhead><?php echo $lang_forummanage['text_make_new_forum']?></td>
  </tr>
  <tr>
    <td><b><?php echo $lang_forummanage['row_forum_name']?></td>
    <td><input name="name" type="text" style="width: 200px" maxlength="60"></td>
  </tr>
  <tr>
    <td><b><?php echo $lang_forummanage['row_forum_description']?></td>
    <td><input name="desc" type="text" style="width: 400px" maxlength="200"></td>
  </tr>
  <tr>
    <td><b><?php echo $lang_forummanage['row_overforum']?></td>
    <td>
    <select name=overforums>
<?php
            $forid = 0;
            foreach ($overforums as $arr) {
             $name = $arr->name;
             $i = $arr->id;
            print("<option value=$i" . ($forid == $i ? " selected" : "") . ">$prefix" . $name . "\n");
            }
?>
        </select>
    </td>
  </tr>
	<tr><td><b><?php echo $lang_forummanage['row_moderator']?></b></td><td><input name="moderator" type="text" style="width: 200px" maxlength="200">&nbsp;<?php echo $lang_forummanage['text_moderator_note']?></td></tr>
    <tr>
    <td><b><?php echo $lang_forummanage['row_minimum_read_permission']?></td>
    <td>
    <select name=readclass>
<?php
             $maxclass = \App\Support\UserDisplay::currentClass();
          for ($i = 0; $i <= $maxclass; ++$i)
            print("<option value=$i" . ($user["class"] == $i ? " selected" : "") . ">$prefix" . \App\Support\UserClass::name($i,false,true,true) . "\n");
?>
        </select>
    </td>
  </tr>
  <tr>
    <td><b><?php echo $lang_forummanage['row_minimum_write_permission']?></td>
    <td><select name=writeclass>
<?php
              $maxclass = \App\Support\UserDisplay::currentClass();
          for ($i = 0; $i <= $maxclass; ++$i)
            print("<option value=$i" . ($user["class"] == $i ? " selected" : "") . ">$prefix" . \App\Support\UserClass::name($i,false,true,true) . "\n");
?>
        </select></td>
  </tr>
  <tr>
    <td><b><?php echo $lang_forummanage['row_minimum_create_topic_permission']?></td>
    <td><select name=createclass>
<?php
            $maxclass = \App\Support\UserDisplay::currentClass();
          for ($i = 0; $i <= $maxclass; ++$i)
            print("<option value=$i" . ($user["class"] == $i ? " selected" : "") . ">$prefix" . \App\Support\UserClass::name($i,false,true,true) . "\n");
?>
        </select></td>
  </tr>
    <tr>
    <td><b><?php echo $lang_forummanage['row_forum_order']?></td>
    <td>
    <select name=sort>
<?php
            $maxclass = $maxSort + 1;
          for ($i = 0; $i <= $maxclass; ++$i)
            print("<option value=$i>$i \n");
?>
        </select>
    <?php echo $lang_forummanage['text_forum_order_note']?></td>
  </tr>

  <tr align="center">
    <td colspan="2"><input type="hidden" name="action" value="addforum"><input type="submit" name="Submit" value="<?php echo $lang_forummanage['submit_make_forum']?>" class=btn></td>
  </tr>
</table>
<?php
}
else {
?>
<h2 class=transparentbg align=center><?php echo $lang_forummanage['text_forum_management']?></h2>
<table border=0 class=main cellspacing=0 cellpadding=5 width=1%><tr>
<td class=embedded align=left><form method="get" action="moforums.php"><input type="submit" value="<?php echo $lang_forummanage['submit_overforum_management']?>" class="btn"></form></td><td class=embedded align=left><form method="get" action="forummanage.php"><input type=hidden name="action" value="newforum"><input type="submit" value="<?php echo $lang_forummanage['submit_add_forum']?>" class="btn"></form></td>
</tr></table>
<?php
echo '<table width="100%"  border="0" align="center" cellpadding="2" cellspacing="0">';
echo "<tr><td class=colhead align=left>".$lang_forummanage['col_name']."</td><td class=colhead>".$lang_forummanage['col_overforum']."</td><td class=colhead>".$lang_forummanage['col_read']."</td><td class=colhead>".$lang_forummanage['col_write']."</td><td class=colhead>".$lang_forummanage['col_create_topic']."</td><td class=colhead>".$lang_forummanage['col_moderator']."</td><td class=colhead>".$lang_forummanage['col_modify']."</td></tr>";
$forums = \Nexus\Database\NexusDB::table('forums')
    ->leftJoin('overforums', 'forums.forid', '=', 'overforums.id')
    ->orderBy('forums.sort')
    ->get(['forums.*', 'overforums.name AS of_name']);
if ($forums->isEmpty()) {
    print "<tr><td colspan=6>".$lang_forummanage['text_no_records_found']."</td></tr>";
} else {
    foreach ($forums as $forumRow) {
        $row = (array) $forumRow;
        $name = $row['of_name'];
        $moderators = \App\Support\Forum::moderatorsWithContext($row['id'],false);
        if (!$moderators)
            $moderators = $lang_forummanage['text_not_available'];
        echo "<tr><td><a href=forums.php?action=viewforum&forumid=".$row["id"]."><b>".htmlspecialchars($row["name"])."</b></a><br />".htmlspecialchars($row["description"])."</td>";
        echo "<td>".htmlspecialchars($name)."</td><td>" . \App\Support\UserClass::name($row["minclassread"],false,true,true) . "</td><td>" . \App\Support\UserClass::name($row["minclasswrite"],false,true,true) . "</td><td>" . \App\Support\UserClass::name($row["minclasscreate"],false,true,true) . "</td><td>".$moderators."</td><td><b><a href=\"".$PHP_SELF."?action=editforum&id=".$row["id"]."\">".$lang_forummanage['text_edit']."</a>&nbsp;|&nbsp;<a href=\"javascript:confirm_delete('".$row["id"]."', '".$lang_forummanage['js_sure_to_delete_forum']."', '');\"><font color=red>".$lang_forummanage['text_delete']."</font></a></b></td></tr>";
    }
}
echo "</table>";
}

\App\Support\Frame::mainFrameClose();
\App\Support\Html::stdfoot();

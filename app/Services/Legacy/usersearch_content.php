<?php
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING & ~E_DEPRECATED);

// Auto-generated legacy bridge shims
if (!isset($CURUSER)) $CURUSER = (array) (\App\Support\SupportContext::getUser() ?? []);
$__server_REQUEST_URI = \App\Support\SupportContext::getServerValue('REQUEST_URI');
$hasModcomment = \Illuminate\Support\Facades\Schema::hasColumn('users', 'modcomment');
$pagemenu = $pagemenu ?? '';
$browsemenu = $browsemenu ?? '';

// 0 - No debug; 1 - Show and run SQL query; 2 - Show SQL query only
$DEBUG_MODE = 0;
if (\App\Support\UserDisplay::currentClass() < UC_MODERATOR)
	\App\Support\LegacyResponse::abort("Error", "Permission denied.");

echo "<h1>Administrative User Search</h1>\n";

if (!empty(\App\Support\SupportContext::getQuery('h')))
{
	echo "<table width=65% border=0 align=center><tr><td class=embedded bgcolor='#F5F4EA'><div align=left>\n
	Fields left blank will be ignored;\n
	Wildcards * and ? may be used in Name, Email and Comments, as well as multiple values\n
	separated by spaces (e.g. 'wyz Max*' in Name will list both users named\n
	'wyz' and those whose names start by 'Max'. Similarly  '~' can be used for\n
	negation, e.g. '~alfiest' in comments will restrict the search to users\n
	that do not have 'alfiest' in their comments).<br /><br />\n
    The Ratio field accepts 'Inf' and '---' besides the usual numeric values.<br /><br />\n
	The subnet mask may be entered either in dotted decimal or CIDR notation\n
	(e.g. 255.255.255.0 is the same as /24).<br /><br />\n
    Uploaded and Downloaded should be entered in GB.<br /><br />\n
	For search parameters with multiple text fields the second will be\n
	ignored unless relevant for the type of search chosen. <br /><br />\n
	'Active only' restricts the search to users currently leeching or seeding,\n
	'Disabled IPs' to those whose IPs also show up in disabled accounts.<br /><br />\n
	The 'p' columns in the results show partial stats, that is, those\n
	of the torrents in progress. <br /><br />\n
	The History column lists the number of forum posts and torrent comments,\n
	respectively, as well as linking to the history page.\n
	</div></td></tr></table><br /><br />\n";
}
else
{
	echo "<p align=center>(<a href='".$__server_REQUEST_URI."?h=1'>Instructions</a>)";
	echo "&nbsp;-&nbsp;(<a href='".$__server_REQUEST_URI."'>Reset</a>)</p>\n";
}

$highlight = " bgcolor=#BBAF9B";

?>

<form method=get action=<?php echo $__server_REQUEST_URI?>>
<table border="1" cellspacing="0" cellpadding="5">
<tr>

  <td valign="middle" class=rowhead>Name:</td>
  <td<?php echo \App\Support\SupportContext::getQuery('n')?$highlight:""?>><input name="n" type="text" value="<?php echo htmlspecialchars(\App\Support\SupportContext::getQuery('n'))?>" size=35></td>

  <td valign="middle" class=rowhead>Ratio:</td>
  <td<?php echo \App\Support\SupportContext::getQuery('r')?$highlight:""?>><select name="rt">
<?php
	$options = array("equal","above","below","between");
	for ($i = 0; $i < count($options); $i++){
	    echo "<option value=$i ".((\App\Support\SupportContext::getQuery('rt')=="$i")?"selected":"").">".$options[$i]."</option>\n";
	}
	?>
    </select>
    <input name="r" type="text" value="<?php echo htmlspecialchars(\App\Support\SupportContext::getQuery('r'))?>" size="5" maxlength="4">
    <input name="r2" type="text" value="<?php echo htmlspecialchars(\App\Support\SupportContext::getQuery('r2'))?>" size="5" maxlength="4"></td>

  <td valign="middle" class=rowhead>Member status:</td>
  <td<?php echo \App\Support\SupportContext::getQuery('st')?$highlight:""?>><select name="st">
<?php
	$options = array("(any)","confirmed","pending");
	for ($i = 0; $i < count($options); $i++){
	    echo "<option value=$i ".((\App\Support\SupportContext::getQuery('st')=="$i")?"selected":"").">".$options[$i]."</option>\n";
	}
    ?>
    </select></td></tr>
<tr><td valign="middle" class=rowhead>Email:</td>
  <td<?php echo \App\Support\SupportContext::getQuery('em')?$highlight:""?>><input name="em" type="text" value="<?php echo htmlspecialchars(\App\Support\SupportContext::getQuery('em'))?>" size="35"></td>
  <td valign="middle" class=rowhead>IP:</td>
  <td<?php echo \App\Support\SupportContext::getQuery('ip')?$highlight:""?>><input name="ip" type="text" value="<?php echo htmlspecialchars(\App\Support\SupportContext::getQuery('ip'))?>" maxlength="64"></td>

  <td valign="middle" class=rowhead>Account status:</td>
  <td<?php echo \App\Support\SupportContext::getQuery('as')?$highlight:""?>><select name="as">
<?php
    $options = array("(any)","enabled","disabled");
    for ($i = 0; $i < count($options); $i++){
      echo "<option value=$i ".((\App\Support\SupportContext::getQuery('as')=="$i")?"selected":"").">".$options[$i]."</option>\n";
    }
?>
    </select></td></tr>
<tr>
  <td valign="middle" class=rowhead>Comment:</td>
  <td<?php echo \App\Support\SupportContext::getQuery('co')?$highlight:""?>><input name="co" type="text" value="<?php echo htmlspecialchars(\App\Support\SupportContext::getQuery('co'))?>" size="35"></td>
  <td valign="middle" class=rowhead>Mask:</td>
  <td<?php echo \App\Support\SupportContext::getQuery('ma')?$highlight:""?>><input name="ma" type="text" value="<?php echo htmlspecialchars(\App\Support\SupportContext::getQuery('ma'))?>" maxlength="17"></td>
  <td valign="middle" class=rowhead>Class:</td>
  <td<?php echo (\App\Support\SupportContext::getQuery('c') && \App\Support\SupportContext::getQuery('c') != 1)?$highlight:""?>><select name="c"><option value='1'>(any)</option>
<?php
  $class = \App\Support\SupportContext::getQuery('c');
  if (!\App\Support\Validators::isId($class))
  	$class = '';
  for ($i = 2;;++$i) {
		if ($c = \App\Support\UserClass::name($i-2,false,true,true))
       	 print("<option value=" . $i . ($class && $class == $i? " selected" : "") . ">$c</option>\n");
	  else
	   	break;
	}
?>
    </select></td></tr>
<tr>

    <td valign="middle" class=rowhead>Joined:</td>

  <td<?php echo \App\Support\SupportContext::getQuery('d')?$highlight:""?>><select name="dt">
<?php
	$options = array("on","before","after","between");
	for ($i = 0; $i < count($options); $i++){
	  echo "<option value=$i ".((\App\Support\SupportContext::getQuery('dt')=="$i")?"selected":"").">".$options[$i]."</option>\n";
	}
?>
    </select>

    <input name="d" type="text" value="<?php echo htmlspecialchars(\App\Support\SupportContext::getQuery('d'))?>" size="12" maxlength="10">

    <input name="d2" type="text" value="<?php echo htmlspecialchars(\App\Support\SupportContext::getQuery('d2'))?>" size="12" maxlength="10"></td>


  <td valign="middle" class=rowhead>Uploaded:</td>

  <td<?php echo \App\Support\SupportContext::getQuery('ul')?$highlight:""?>><select name="ult" id="ult">
<?php
    $options = array("equal","above","below","between");
    for ($i = 0; $i < count($options); $i++){
  	  echo "<option value=$i ".((\App\Support\SupportContext::getQuery('ult')=="$i")?"selected":"").">".$options[$i]."</option>\n";
    }
?>
    </select>

    <input name="ul" type="text" id="ul" size="8" maxlength="7" value="<?php echo htmlspecialchars(\App\Support\SupportContext::getQuery('ul'))?>">

    <input name="ul2" type="text" id="ul2" size="8" maxlength="7" value="<?php echo htmlspecialchars(\App\Support\SupportContext::getQuery('ul2'))?>"></td>
  <td valign="middle" class="rowhead">Donor:</td>

  <td<?php echo \App\Support\SupportContext::getQuery('do')?$highlight:""?>><select name="do">
<?php
    $options = array("(any)","Yes","No");
	for ($i = 0; $i < count($options); $i++){
	  echo "<option value=$i ".((\App\Support\SupportContext::getQuery('do')=="$i")?"selected":"").">".$options[$i]."</option>\n";
    }
?>
	</select></td></tr>
<tr>

<td valign="middle" class=rowhead>Last seen:</td>

  <td <?php echo \App\Support\SupportContext::getQuery('ls')?$highlight:""?>><select name="lst">
<?php
  $options = array("on","before","after","between");
  for ($i = 0; $i < count($options); $i++){
    echo "<option value=$i ".((\App\Support\SupportContext::getQuery('lst')=="$i")?"selected":"").">".$options[$i]."</option>\n";
  }
?>
  </select>

  <input name="ls" type="text" value="<?php echo htmlspecialchars(\App\Support\SupportContext::getQuery('ls'))?>" size="12" maxlength="10">

  <input name="ls2" type="text" value="<?php echo htmlspecialchars(\App\Support\SupportContext::getQuery('ls2'))?>" size="12" maxlength="10"></td>
	  <td valign="middle" class=rowhead>Downloaded:</td>

  <td<?php echo \App\Support\SupportContext::getQuery('dl')?$highlight:""?>><select name="dlt" id="dlt">
<?php
	$options = array("equal","above","below","between");
	for ($i = 0; $i < count($options); $i++){
	  echo "<option value=$i ".((\App\Support\SupportContext::getQuery('dlt')=="$i")?"selected":"").">".$options[$i]."</option>\n";
	}
?>
    </select>

    <input name="dl" type="text" id="dl" size="8" maxlength="7" value="<?php echo htmlspecialchars(\App\Support\SupportContext::getQuery('dl'))?>">

    <input name="dl2" type="text" id="dl2" size="8" maxlength="7" value="<?php echo htmlspecialchars(\App\Support\SupportContext::getQuery('dl2'))?>"></td>

	<td valign="middle" class=rowhead>Warned:</td>

	<td<?php echo \App\Support\SupportContext::getQuery('w')?$highlight:""?>><select name="w">
<?php
  $options = array("(any)","Yes","No");
	for ($i = 0; $i < count($options); $i++){
		echo "<option value=$i ".((\App\Support\SupportContext::getQuery('w')=="$i")?"selected":"").">".$options[$i]."</option>\n";
  }
?>
	</select></td></tr>

<tr><td class="rowhead"></td><td></td>
  <td valign="middle" class=rowhead>Active only:</td>
	<td<?php echo \App\Support\SupportContext::getQuery('ac')?$highlight:""?>><input name="ac" type="checkbox" value="1" <?php echo (\App\Support\SupportContext::getQuery('ac'))?"checked":"" ?>></td>
  <td valign="middle" class=rowhead>Disabled IP: </td>
  <td<?php echo \App\Support\SupportContext::getQuery('dip')?$highlight:""?>><input name="dip" type="checkbox" value="1" <?php echo (\App\Support\SupportContext::getQuery('dip'))?"checked":"" ?>></td>
  </tr>
<tr><td colspan="6" align=center><input name="submit" type=submit class=btn></td></tr>
</table>
<br /><br />
</form>

<?php

// ratio as a string
if (!function_exists('ratios')) { function ratios(float $up, float $down, bool $color = true): string
{
	if ($down > 0)
	{
		$r = number_format($up / $down, 2);
    if ($color)
			$r = "<font color=".\App\Support\Ratio::color($r).">$r</font>";
	}
	else
		if ($up > 0)
	  	$r = "Inf.";
	  else
	  	$r = "---";
	return $r;
} }

if (count(\App\Support\SupportContext::allQuery()) > 0 && empty(\App\Support\SupportContext::getQuery('h')))
{
try {
    $searchResult = \App\Repositories\UserSearchRepository::administrativeSearch((array) \App\Support\SupportContext::allQuery(), (bool) $hasModcomment, 30);
} catch (\InvalidArgumentException $e) {
    \App\Support\Html::stdMessage("Error", $e->getMessage());
    return;
}
$count = $searchResult['count'];
$q = $searchResult['q'];
$perpage = 30;
list($pagertop, $pagerbottom, , $offset, $rpp, ) = \App\Support\Pagination::pager($perpage, $count, $__server_REQUEST_URI."?".$q);
$res = $searchResult['rows'];

$userIds = array_map(fn ($row) => (int) ($row['id'] ?? 0), $res);
$ips = array_map(fn ($row) => (string) ($row['ip'] ?? ''), $res);
$extraStats = \App\Repositories\UserListingRepository::getSearchExtraStats($userIds, $ips, (int) $CURUSER['class']);
$peerTotals = $extraStats['peers'];
$postCounts = $extraStats['posts'];
$commentCounts = $extraStats['comments'];
$bannedIps = $extraStats['bannedIps'];

  if (count($res) == 0)
  	\App\Support\Html::stdMessage("Warning", "No user was found.");
  else
  {
  	if ($count > $perpage)
  		echo $pagertop;
    echo "<table border=1 cellspacing=0 cellpadding=5>\n";
    echo "<tr><td class=colhead align=left>Name</td>
    		<td class=colhead align=left>Ratio</td>
        <td class=colhead align=left>IP</td>
        <td class=colhead align=left>Email</td>".
        "<td class=colhead align=left>Joined:</td>".
        "<td class=colhead align=left>Last seen:</td>".
        "<td class=colhead align=left>Status</td>".
        "<td class=colhead align=left>Enabled</td>".
        "<td class=colhead>pR</td>".
        "<td class=colhead>pUL</td>".
        "<td class=colhead>pDL</td>".
        "<td class=colhead>History</td></tr>";
    foreach ($res as $user) { $user = (array) $user;
    	if ($user['added'] == '0000-00-00 00:00:00' || $user['added'] == null)
      	$user['added'] = '---';
      if ($user['last_access'] == '0000-00-00 00:00:00' || $user['last_access'] == null)
      	$user['last_access'] = '---';

      if ($user['ip']) {
          $ipstr = $user['ip'];
          if (filter_var($user['ip'], FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) && isset($bannedIps[$user['ip']])) {
              $ipstr = "<a href='testip.php?ip=" . $user['ip'] . "'><font color='#FF0000'><b>" . $user['ip'] . "</b></font></a>";
          }
      } else {
          $ipstr = "---";
      }

      $peerTotal = $peerTotals[(int) $user['id']] ?? ['pul' => 0, 'pdl' => 0];
      $pul = $peerTotal['pul'];
      $pdl = $peerTotal['pdl'];

      $n_posts = $postCounts[(int) $user['id']] ?? 0;
      $n_comments = $commentCounts[(int) $user['id']] ?? 0;

    	echo "<tr><td>" .
      		\App\Support\UserDisplay::username($user['id']) . "</td>" .
          "<td>" . ratios($user['uploaded'], $user['downloaded']) . "</td>
          <td>" . $ipstr . "</td><td>" . $user['email'] . "</td>
          <td><div align=center>" . $user['added'] . "</div></td>
          <td><div align=center>" . $user['last_access'] . "</div></td>
          <td><div align=center>" . $user['status'] . "</div></td>
          <td><div align=center>" . $user['enabled']."</div></td>
          <td><div align=center>" . ratios($pul,$pdl) . "</div></td>" .
          "<td><div align=right>" . \App\Support\Format::size($pul) . "</div></td>
          <td><div align=right>" . \App\Support\Format::size($pdl) . "</div></td>
          <td><div align=center>".($n_posts?"<a href=userhistory.php?action=viewposts&id=".$user['id'].">$n_posts</a>":$n_posts).
          "|".($n_comments?"<a href=userhistory.php?action=viewcomments&id=".$user['id'].">$n_comments</a>":$n_comments).
          "</div></td></tr>\n";
    }
    echo "</table>";
    if ($count > $perpage)
    	echo "$pagerbottom";

	/*
    <br /><br />
    <form method=post action=/sendmessage.php>
      <table border="1" cellpadding="5" cellspacing="0">
        <tr>
          <td>
            <div align="center">
              <input name="pmees" type="hidden" value="<?php echo $querypm?>" size=10>
              <input name="PM" type="submit" value="PM" class=btn>
              <input name="n_pms" type="hidden" value="<?php echo $count?>" size=10>
            </div></td>
        </tr>
      </table>
    </form>
    */

  }
}

print("<p>$pagemenu<br />$browsemenu</p>");
return;
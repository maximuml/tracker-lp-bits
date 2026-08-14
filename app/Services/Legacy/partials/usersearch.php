<?php
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING & ~E_DEPRECATED);

// Auto-generated legacy bridge shims
if (!isset($CURUSER)) $CURUSER = (array) (\App\Support\SupportContext::getUser() ?? []);
$__server_REQUEST_URI = \App\Support\SupportContext::getServerValue('REQUEST_URI');
$hasModcomment = \Illuminate\Support\Facades\Schema::hasColumn('users', 'modcomment');

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

// Validates date in the form [yy]yy-mm-dd;
// Returns date if valid, 0 otherwise.
if (!function_exists('mkdate')) { function mkdate($date){
  if (strpos($date,'-'))
  	$a = explode('-', $date);
  elseif (strpos($date,'/'))
  	$a = explode('/', $date);
  else
  	return 0;
  for ($i=0;$i<3;$i++)
  	if (!is_numeric($a[$i]))
    	return 0;
    if (checkdate($a[1], $a[2], $a[0]))
    	return  date ("Y-m-d", mktime (0,0,0,$a[1],$a[2],$a[0]));
    else
			return 0;
} }

// ratio as a string
if (!function_exists('ratios')) { function ratios($up,$down, $color = True)
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

// checks for the usual wildcards *, ? plus mySQL ones
if (!function_exists('haswildcard')) { function haswildcard($text){
	if (strpos($text,'*') === False && strpos($text,'?') === False
			&& strpos($text,'%') === False && strpos($text,'_') === False)
  	return False;
  else
  	return True;
} }

$userQuery = \Nexus\Database\NexusDB::table('users as u');
$q = '';
if (count(\App\Support\SupportContext::allQuery()) > 0 && !\App\Support\SupportContext::getQuery('h'))
{
	// name
  $names = explode(' ',trim(\App\Support\SupportContext::getQuery('n')));
  if ($names[0] !== "")
  {
		$names_inc = [];
		$names_exc = [];
		foreach($names as $name)
		{
	  	if (substr($name,0,1) == '~')
	  	{
      	if ($name == '~') continue;
    	    $names_exc[] = substr($name,1);
      }
	    else
	    	$names_inc[] = $name;
	  }

    if (!empty($names_inc))
    {
	  	$userQuery->where(function ($query) use ($names_inc) {
	  		$first = true;
		    foreach($names_inc as $name)
		    {
      		if (!haswildcard($name))
	        {
	        	$method = $first ? 'where' : 'orWhere';
	        	$query->$method('u.username', $name);
	        }
	      else
	      {
	        $name = str_replace(array('?','*'), array('_','%'), $name);
	        $method = $first ? 'where' : 'orWhere';
	        $query->$method('u.username', 'like', $name);
	      }
	      $first = false;
	    }
	  	});
	  }

    if (!empty($names_exc))
    {
	  	$userQuery->where(function ($query) use ($names_exc) {
		    foreach($names_exc as $name)
		    {
	    		if (!haswildcard($name))
	    		{
	      		$query->where('u.username', '!=', $name);
	      }
	      else
	      {
	      	$name = str_replace(array('?','*'), array('_','%'), $name);
	        $query->where('u.username', 'not like', $name);
	      }
	    }
	  	});
	  }
	  $q .= ($q ? "&" : "") . "n=".rawurlencode(trim(\App\Support\SupportContext::getQuery('n')));
  }

  // email
  $emaila = explode(' ', trim(\App\Support\SupportContext::getQuery('em')));
  if ($emaila[0] !== "")
  {
  	$userQuery->where(function ($query) use ($emaila) {
  		$first = true;
    foreach($emaila as $email)
    {
	  	if (strpos($email,'*') === False && strpos($email,'?') === False
	    		&& strpos($email,'%') === False)
	    {
      	if (!\App\Support\Validators::isEmail($email))
      	{
	        \App\Support\Html::stdMessage("Error", "Bad email.");
	      	return;
	      }
	      $method = $first ? 'where' : 'orWhere';
	      $query->$method('u.email', $email);
      }
      else
      {
    		$sql_email = str_replace(array('?','*'), array('_','%'), $email);
    		$method = $first ? 'where' : 'orWhere';
        $query->$method('u.email', 'like', $sql_email);
    	}
    	$first = false;
    }
  	});
	$q .= ($q ? "&" : "") . "em=".rawurlencode(trim(\App\Support\SupportContext::getQuery('em')));
  }

  //class
  // NB: the c parameter is passed as two units above the real one
  $class = \App\Support\SupportContext::getQuery('c') - 2;
	if (\App\Support\Validators::isId($class + 1))
	{
  	$userQuery->where('u.class', $class);
    $q .= ($q ? "&" : "") . "c=".($class+2);
  }

  // IP
  $ip = trim(\App\Support\SupportContext::getQuery('ip'));
  if ($ip)
  {
  	$regex = "/^(((1?\\d{1,2})|(2[0-4]\\d)|(25[0-5]))(\\.\\b|$)){4}$/";
  	if (!filter_var($ip, FILTER_VALIDATE_IP))
    {
    	\App\Support\Html::stdMessage("Error", "Bad IP.");
      return;
    }

    $mask = trim(\App\Support\SupportContext::getQuery('ma'));
    if ($mask == "" || $mask == "255.255.255.255")
    	$userQuery->where('u.ip', $ip);
    else
    {
    	if (substr($mask,0,1) == "/")
    	{
      	$n = substr($mask, 1, strlen($mask) - 1);
        if (!is_numeric($n) or $n < 0 or $n > 32)
        {
        	\App\Support\Html::stdMessage("Error", "Bad subnet mask.");
          return;
        }
        else
	      	$mask = long2ip(pow(2,32) - pow(2,32-$n));
      }
      elseif (!preg_match($regex, $mask))
      {
				\App\Support\Html::stdMessage("Error", "Bad subnet mask.");
	      return;
      }
      $userQuery->whereRaw("INET_ATON(u.ip) & INET_ATON(?) = INET_ATON(?) & INET_ATON(?)", [$mask, $ip, $mask]);
      $q .= ($q ? "&" : "") . "ma=$mask";
    }
    $q .= ($q ? "&" : "") . "ip=$ip";
  }

  // ratio
  $ratio = trim(\App\Support\SupportContext::getQuery('r'));
  if ($ratio)
  {
  	if ($ratio == '---')
  	{
      $userQuery->where('u.uploaded', 0)->where('u.downloaded', 0);
    }
    elseif (strtolower(substr($ratio,0,3)) == 'inf')
    {
    	$userQuery->where('u.uploaded', '>', 0)->where('u.downloaded', 0);
    }
    else
    {
    	if (!is_numeric($ratio) || $ratio < 0)
      {
      	\App\Support\Html::stdMessage("Error", "Bad ratio.");
        return;
      }
      $ratiotype = \App\Support\SupportContext::getQuery('rt');
      $q .= ($q ? "&" : "") . "rt=$ratiotype";
      $userQuery->where('u.downloaded', '>', 0);
      if ($ratiotype == "3")
      {
      	$ratio2 = trim(\App\Support\SupportContext::getQuery('r2'));
        if(!$ratio2)
        {
        	\App\Support\Html::stdMessage("Error", "Two ratios needed for this type of search.");
          return;
        }
        if (!is_numeric($ratio2) or $ratio2 < $ratio)
        {
        	\App\Support\Html::stdMessage("Error", "Bad second ratio.");
        	return;
        }
        $userQuery->whereRaw('(u.uploaded/u.downloaded) BETWEEN ? AND ?', [(float)$ratio, (float)$ratio2]);
        $q .= ($q ? "&" : "") . "r2=$ratio2";
      }
      elseif ($ratiotype == "2")
      	$userQuery->whereRaw('(u.uploaded/u.downloaded) < ?', [(float)$ratio]);
      elseif ($ratiotype == "1")
      	$userQuery->whereRaw('(u.uploaded/u.downloaded) > ?', [(float)$ratio]);
      else
      	$userQuery->whereRaw('(u.uploaded/u.downloaded) BETWEEN ? AND ?', [max(0, (float)$ratio - 0.004), (float)$ratio + 0.004]);
    }
    $q .= ($q ? "&" : "") . "r=$ratio";
  }

  // comment
  $comments = explode(' ',trim(\App\Support\SupportContext::getQuery('co')));
  if ($comments[0] !== "" && $hasModcomment)
  {
		$comments_inc = [];
		$comments_exc = [];
		foreach($comments as $comment)
		{
	    if (substr($comment,0,1) == '~')
	    {
      	if ($comment == '~') continue;
    	    $comments_exc[] = substr($comment,1);
      }
      else
    		$comments_inc[] = $comment;
	  }

    if (!empty($comments_inc))
    {
	  	$userQuery->where(function ($query) use ($comments_inc) {
	  		$first = true;
		    foreach($comments_inc as $comment)
		    {
	    		if (!haswildcard($comment))
			    {
		    		$method = $first ? 'where' : 'orWhere';
				    $query->$method('u.modcomment', 'like', '%'.$comment.'%');
		    	}
        else
        {
	      		$comment = str_replace(array('?','*'), array('_','%'), $comment);
	      		$method = $first ? 'where' : 'orWhere';
	        $query->$method('u.modcomment', 'like', $comment);
	      }
	      $first = false;
	    }
	  	});
    }

    if (!empty($comments_exc))
    {
	  	$userQuery->where(function ($query) use ($comments_exc) {
		    foreach($comments_exc as $comment)
		    {
	    		if (!haswildcard($comment))
	    		{
	      		$query->where('u.modcomment', 'not like', '%'.$comment.'%');
        }
        else
        {
	      		$comment = str_replace(array('?','*'), array('_','%'), $comment);
	        $query->where('u.modcomment', 'not like', $comment);
	      }
	    }
	  	});
	  }
    $q .= ($q ? "&" : "") . "co=".rawurlencode(trim(\App\Support\SupportContext::getQuery('co')));
  }

  $unit = 1073741824;		// 1GB

  // uploaded
  $ul = trim(\App\Support\SupportContext::getQuery('ul'));
  if ($ul)
  {
  	if (!is_numeric($ul) || $ul < 0)
  	{
    	\App\Support\Html::stdMessage("Error", "Bad uploaded amount.");
      return;
    }
    $ultype = \App\Support\SupportContext::getQuery('ult');
    $q .= ($q ? "&" : "") . "ult=$ultype";
    if ($ultype == "3")
    {
	    $ul2 = trim(\App\Support\SupportContext::getQuery('ul2'));
    	if(!$ul2)
    	{
      	\App\Support\Html::stdMessage("Error", "Two uploaded amounts needed for this type of search.");
        return;
      }
      if (!is_numeric($ul2) or $ul2 < $ul)
      {
      	\App\Support\Html::stdMessage("Error", "Bad second uploaded amount.");
        return;
      }
      $userQuery->whereBetween('u.uploaded', [(float)$ul*$unit, (float)$ul2*$unit]);
      $q .= ($q ? "&" : "") . "ul2=$ul2";
    }
    elseif ($ultype == "2")
    	$userQuery->where('u.uploaded', '<', (float)$ul*$unit);
    elseif ($ultype == "1")
    	$userQuery->where('u.uploaded', '>', (float)$ul*$unit);
    else
    	$userQuery->whereBetween('u.uploaded', [max(0, ((float)$ul - 0.004)*$unit), ((float)$ul + 0.004)*$unit]);
    $q .= ($q ? "&" : "") . "ul=$ul";
  }

  // downloaded
  $dl = trim(\App\Support\SupportContext::getQuery('dl'));
  if ($dl)
  {
  	if (!is_numeric($dl) || $dl < 0)
  	{
    	\App\Support\Html::stdMessage("Error", "Bad downloaded amount.");
      return;
    }
    $dltype = \App\Support\SupportContext::getQuery('dlt');
    $q .= ($q ? "&" : "") . "dlt=$dltype";
    if ($dltype == "3")
    {
    	$dl2 = trim(\App\Support\SupportContext::getQuery('dl2'));
      if(!$dl2)
      {
      	\App\Support\Html::stdMessage("Error", "Two downloaded amounts needed for this type of search.");
        return;
      }
      if (!is_numeric($dl2) or $dl2 < $dl)
      {
      	\App\Support\Html::stdMessage("Error", "Bad second downloaded amount.");
        return;
      }
      $userQuery->whereBetween('u.downloaded', [(float)$dl*$unit, (float)$dl2*$unit]);
      $q .= ($q ? "&" : "") . "dl2=$dl2";
    }
    elseif ($dltype == "2")
    	$userQuery->where('u.downloaded', '<', (float)$dl*$unit);
    elseif ($dltype == "1")
      	$userQuery->where('u.downloaded', '>', (float)$dl*$unit);
    else
      	$userQuery->whereBetween('u.downloaded', [max(0, ((float)$dl - 0.004)*$unit), ((float)$dl + 0.004)*$unit]);
    $q .= ($q ? "&" : "") . "dl=$dl";
  }

  // date joined
  $date = trim(\App\Support\SupportContext::getQuery('d'));
  if ($date)
  {
  	if (!$date = mkdate($date))
  	{
    	\App\Support\Html::stdMessage("Error", "Invalid date.");
      return;
    }
    $q .= ($q ? "&" : "") . "d=$date";
    $datetype = \App\Support\SupportContext::getQuery('dt');
		$q .= ($q ? "&" : "") . "dt=$datetype";
    if ($datetype == "0")
    {
    	$userQuery->whereBetween('u.added', [$date, date('Y-m-d H:i:s', strtotime($date) + 86400)]);
    }
    else
    {
      if ($datetype == "3")
      {
        $date2 = mkdate(trim(\App\Support\SupportContext::getQuery('d2')));
        if ($date2)
        {
          $q .= ($q ? "&" : "") . "d2=$date2";
          $userQuery->whereBetween('u.added', [$date, $date2]);
        }
        else
        {
          \App\Support\Html::stdMessage("Error", "Two dates needed for this type of search.");
          return;
        }
      }
      elseif ($datetype == "1")
        $userQuery->where('u.added', '<', $date);
      elseif ($datetype == "2")
        $userQuery->where('u.added', '>', $date);
    }
  }

	// date last seen
  $last = trim(\App\Support\SupportContext::getQuery('ls'));
  if ($last)
  {
  	if (!$last = mkdate($last))
  	{
    	\App\Support\Html::stdMessage("Error", "Invalid date.");
      return;
    }
    $q .= ($q ? "&" : "") . "ls=$last";
    $lasttype = \App\Support\SupportContext::getQuery('lst');
    $q .= ($q ? "&" : "") . "lst=$lasttype";
    if ($lasttype == "0")
    {
    	$userQuery->whereBetween('u.last_access', [$last, date('Y-m-d H:i:s', strtotime($last) + 86400)]);
    }
    else
    {
      if ($lasttype == "3")
      {
      	$last2 = mkdate(trim(\App\Support\SupportContext::getQuery('ls2')));
        if ($last2)
        {
        	$q .= ($q ? "&" : "") . "ls2=$last2";
          $userQuery->whereBetween('u.last_access', [$last, $last2]);
        }
        else
        {
        	\App\Support\Html::stdMessage("Error", "The second date is not valid.");
        	return;
        }
      }
      elseif ($lasttype == "1")
    		$userQuery->where('u.last_access', '<', $last);
      elseif ($lasttype == "2")
      	$userQuery->where('u.last_access', '>', $last);
    }
  }

  // status
  $status = \App\Support\SupportContext::getQuery('st');
  if ($status)
  {
  	$userQuery->where('u.status', $status == "1" ? 'confirmed' : 'pending');
    $q .= ($q ? "&" : "") . "st=$status";
  }

  // account status
  $accountstatus = \App\Support\SupportContext::getQuery('as');
  if ($accountstatus)
  {
  	$userQuery->where('u.enabled', $accountstatus == "1" ? 'yes' : 'no');
    $q .= ($q ? "&" : "") . "as=$accountstatus";
  }

  //donor
	$donor = \App\Support\SupportContext::getQuery('do');
  if ($donor)
  {
		$userQuery->where('u.donor', $donor == 1 ? 'yes' : 'no');
    $q .= ($q ? "&" : "") . "do=$donor";
  }

  //warned
	$warned = \App\Support\SupportContext::getQuery('w');
  if ($warned)
  {
		$userQuery->where('u.warned', $warned == 1 ? 'yes' : 'no');
    $q .= ($q ? "&" : "") . "w=$warned";
  }

  // disabled IP
  $disabled = \App\Support\SupportContext::getQuery('dip');
  if ($disabled)
  {
    $userQuery->leftJoin('users as u2', 'u.ip', '=', 'u2.ip')->where('u2.enabled', 'no');
    $q .= ($q ? "&" : "") . "dip=$disabled";
  }

  // active
  $active = \App\Support\SupportContext::getQuery('ac');
  if ($active == "1")
  {
    $userQuery->leftJoin('peers as p', 'u.id', '=', 'p.userid');
    $q .= ($q ? "&" : "") . "ac=$active";
  }

$select_is = "u.id, u.username, u.email, u.status, u.added, u.last_access, u.ip,
	u.class, u.uploaded, u.downloaded, u.donor, u.enabled, u.warned";
if ($hasModcomment) {
    $select_is = str_replace('u.donor, u.enabled', 'u.donor, u.modcomment, u.enabled', $select_is);
}

$count = (int) (clone $userQuery)->selectRaw('count(distinct u.id) as count')->value('count');

$q = (isset($q))?($q."&"):"";

$perpage = 30;

list($pagertop, $pagerbottom, , $offset, $rpp, ) = \App\Support\Pagination::pager($perpage, $count, $__server_REQUEST_URI."?".$q);

$res = (clone $userQuery)->distinct()->selectRaw($select_is)->offset($offset)->limit($rpp)->get()->map(fn ($row) => (array)$row)->all();

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
          if (filter_var($user['ip'], FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
              $nip = ip2long($user['ip']);
              $array = (array) \Nexus\Database\NexusDB::table('bans')
                  ->where('first', '<=', $nip)
                  ->where('last', '>=', $nip)
                  ->first(['count' => \Nexus\Database\NexusDB::raw('COUNT(*)')]);
              if ($array['count'] > 0) {
                  $ipstr = "<a href='testip.php?ip=" . $user['ip'] . "'><font color='#FF0000'><b>" . $user['ip'] . "</b></font></a>";
              }
          }
      } else {
          $ipstr = "---";
      }
      $array = (array) (\Nexus\Database\NexusDB::table('peers')
          ->where('userid', $user['id'])
          ->selectRaw('SUM(uploaded) AS pul, SUM(downloaded) AS pdl')
          ->first() ?? []);

      $pul = $array['pul'] ?? 0;
      $pdl = $array['pdl'] ?? 0;

      $n = (array) \Nexus\Database\NexusDB::table('posts as p')
          ->leftJoin('topics as t', 'p.topicid', '=', 't.id')
          ->leftJoin('forums as f', 't.forumid', '=', 'f.id')
          ->where('p.userid', $user['id'])
          ->where('f.minclassread', '<=', $CURUSER['class'])
          ->selectRaw('COUNT(DISTINCT p.id) AS count')
          ->first();

      $n_posts = $n['count'] ?? 0;

      $n = (array) \Nexus\Database\NexusDB::table('comments')
          ->where('user', $user['id'])
          ->selectRaw('COUNT(id) AS count')
          ->first();
      $n_comments = $n['count'] ?? 0;

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
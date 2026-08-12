<?php
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING & ~E_DEPRECATED);

$__server_REQUEST_URI = \App\Support\SupportContext::getServerValue('REQUEST_URI');
// mod_cheat for torrentbits based tracker
// Copy this file to the same dir as the rest of the tracker stuff...

$top = 100;  // Only look at the top xxx most likely...




if (\App\Support\UserDisplay::currentClass() < UC_MODERATOR) \App\Support\LegacyResponse::abort("Error", "Permission denied.");

\App\Support\Html::stdhead("Cheaters");
\App\Support\Html::beginFrame('Cheaters');

$page = @\App\Support\SupportContext::getQuery('page');
//$perpage = 100; // currently ignored

$class = @\App\Support\SupportContext::getQuery('c');
if (!\App\Support\User::isValidUserClass($class-2)) $class = '';

$ratio = @\App\Support\SupportContext::getQuery('r');
if (!\App\Support\Validators::isId($ratio) && $ratio>=1 && $ratio<=7) $ratio = '';

echo '<center><form method="get" action="'.$__server_REQUEST_URI.'">';
\App\Support\Html::beginTable();

echo '<tr><th colspan="4">Important</th></tr><tr><td colspan="4" class="left">';
echo 'Although the word <b>cheat</b> is used here, it should be kept in mind that this<br />';
echo 'is statistical analysis - "There are lies, damm lies, and statistics!"<br />';
echo 'The value for cheating can and will change quite drastically depending on what<br />';
echo 'is happening, so you should always take into account other factors before<br />';
echo 'issueing a warning.<br />';
echo 'Somebody might get quite a high cheat value, but never cheat in their life - simply<br />';
echo 'from bad luck in when the client updates the tracker - but that will drop again in<br />';
echo 'the future. A true cheater will stay consistantly high...';
echo '</td></tr>';
echo '<tr><th>Class:</th>';
echo '<td><select name="c"><option value="1">(any)</option>';
for ($i = 2; ;++$i)
{
  if ($c = \App\Support\UserClass::name($i-2)) echo '<option value="'.$i.'"'.($class == $i? ' selected' : '').">&lt;= $c</option>\n";
  else break;
}
echo '</select></td>';

echo '<th>Ratio:</th>';
echo '<td><select name="r"><option value="1"'.($ratio == 1?' selected' : '').'>(any)</option>';
echo '<option value="2"'.($ratio == 2?' selected' : '').'>&gt;= 1.000</option>';
echo '<option value="3"'.($ratio == 3?' selected' : '').'>&gt;= 2.000</option>';
echo '<option value="4"'.($ratio == 4?' selected' : '').'>&gt;= 3.000</option>';
echo '<option value="5"'.($ratio == 5?' selected' : '').'>&gt;= 4.000</option>';
echo '<option value="6"'.($ratio == 6?' selected' : '').'>&gt;= 5.000</option>';
echo '</select></td>';

echo '</tr><tr><td colspan="4"><input name="submit" type="submit"></td></tr>';
\App\Support\Html::endTable();
echo '</form>';

$baseQuery = \Nexus\Database\NexusDB::table('users')
    ->where('enabled', 1)
    ->where('downloaded', '>', 0)
    ->where('uploaded', '>', 0);
if ($class>2) $baseQuery->where('class', '<', ($class - 1));
if ($ratio>1) $baseQuery->whereRaw('(uploaded / downloaded) > ?', [($ratio - 1)]);

$agg = (clone $baseQuery)->selectRaw('COUNT(*) as cnt, MIN(cheat) as minc, MAX(cheat) as maxc')->first();
$top = MIN($top, (int)($agg->cnt ?? 0));
$min = $agg->minc ?? 0;
$max = $agg->maxc ?? 0;

$pages = ceil($top / 20);
if ($page < 1) $page = 1;
elseif ($page > $pages) $page = $pages;

echo $pagertop;
\App\Support\Html::beginTable();
print("<tr><th class=\"left\">User name</th><th>Registered</th><th>Uploaded</th><th>Downloaded</th><th>Ratio</th><th>Cheat Value</th><th>Cheat Spread</th></tr>\n");

list($pagertop, $pagerbottom, , $offset, $rpp) = \App\Support\Pagination::pager(20, $top, "cheaters.php?");
$rows = $baseQuery->orderByDesc('cheat')->offset($offset)->limit($rpp)->get()->map(fn ($r) => (array) $r)->all();
foreach ($rows as $arr)
{
  if ($arr['added'] == "0000-00-00 00:00:00" || $arr['added'] == null) $joindate = 'N/A';
  else $joindate = \App\Support\Format::getElapsedTime(strtotime($arr['added'])).' ago';
  $age = date('U') - date('U',strtotime($arr['added']));
  if ($arr["downloaded"] > 0)
  {
    $ratio = number_format($arr["uploaded"] / $arr["downloaded"], 3);
    $ratio = "<font color=" . \App\Support\Ratio::color($ratio) . ">$ratio</font>";
  } else {
    if ($arr["uploaded"] > 0) $ratio = "Inf.";
    else $ratio = "---";
  }
  if ($arr['added'] == '0000-00-00 00:00:00' || $arr['added'] == null) $arr['added'] = '-';
  echo '<tr><th class="left"><a href="userdetails.php?id='.$arr['id'].'"><b>'.$arr['username'].'</b></a></th>';
  echo '<td>'.$joindate.'</td>';
  echo '<td class="right">'.\App\Support\Format::size($arr['uploaded']).' @ '.\App\Support\Format::size($arr['uploaded'] / $age).'ps</td>';
  echo '<td class="right">'.\App\Support\Format::size($arr['downloaded']).' @ '.\App\Support\Format::size($arr['downloaded'] / $age).'ps</td>';
  echo '<td>'.$ratio.'</td>';
  echo '<td>'.$arr['cheat'].'</td>';
  echo '<td class="right">'.ceil(($arr['cheat'] - $min) / max(1, ($max - $min)) * 100).'%</td></tr>'."\n";
}
\App\Support\Html::endTable();
echo $pagerbottom;
\App\Support\Html::endFrame();

\App\Support\Html::stdfoot();
?>

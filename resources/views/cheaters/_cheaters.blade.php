<?php
\App\Support\Html::beginFrame('Cheaters');
?>
<center><form method="get" action="cheaters.php">
<?php \App\Support\Html::beginTable(); ?>
<tr><th colspan="4">Important</th></tr><tr><td colspan="4" class="left">
Although the word <b>cheat</b> is used here, it should be kept in mind that this<br />
is statistical analysis - "There are lies, damm lies, and statistics!"<br />
The value for cheating can and will change quite drastically depending on what<br />
is happening, so you should always take into account other factors before<br />
issueing a warning.<br />
Somebody might get quite a high cheat value, but never cheat in their life - simply<br />
from bad luck in when the client updates the tracker - but that will drop again in<br />
the future. A true cheater will stay consistantly high...
</td></tr>
<tr><th>Class:</th>
<td><select name="c"><option value="1"{{ $class == 1 ? ' selected' : '' }}>(any)</option>
@foreach ($classOptions as $opt)
  <option value="{{ $opt['value'] }}"{{ $class == $opt['value'] ? ' selected' : '' }}>&lt;= {{ $opt['label'] }}</option>
@endforeach
</select></td>

<th>Ratio:</th>
<td><select name="r">
<option value="1"{{ $ratio == 1 ? ' selected' : '' }}>(any)</option>
<option value="2"{{ $ratio == 2 ? ' selected' : '' }}>&gt;= 1.000</option>
<option value="3"{{ $ratio == 3 ? ' selected' : '' }}>&gt;= 2.000</option>
<option value="4"{{ $ratio == 4 ? ' selected' : '' }}>&gt;= 3.000</option>
<option value="5"{{ $ratio == 5 ? ' selected' : '' }}>&gt;= 4.000</option>
<option value="6"{{ $ratio == 6 ? ' selected' : '' }}>&gt;= 5.000</option>
</select></td>

</tr><tr><td colspan="4"><input name="submit" type="submit"></td></tr>
<?php \App\Support\Html::endTable(); ?>
</form>

{!! $pagertop !!}
<?php \App\Support\Html::beginTable(); ?>
<tr><th class="left">User name</th><th>Registered</th><th>Uploaded</th><th>Downloaded</th><th>Ratio</th><th>Cheat Value</th><th>Cheat Spread</th></tr>

@foreach ($rows as $arr)
<tr><th class="left"><a href="userdetails.php?id={{ $arr['id'] }}"><b>{{ $arr['username'] }}</b></a></th>
<td>{{ $arr['joindate'] }}</td>
<td class="right">{{ \App\Support\Format::size($arr['uploaded']) }} @ {{ $arr['upload_speed'] }}</td>
<td class="right">{{ \App\Support\Format::size($arr['downloaded']) }} @ {{ $arr['download_speed'] }}</td>
<td>{!! $arr['ratio_html'] !!}</td>
<td>{{ $arr['cheat'] }}</td>
<td class="right">{{ $arr['cheat_spread'] }}</td></tr>
@endforeach
<?php \App\Support\Html::endTable(); ?>
{!! $pagerbottom !!}
<?php \App\Support\Html::endFrame(); ?>

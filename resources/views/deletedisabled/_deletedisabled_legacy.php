<?php
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING & ~E_DEPRECATED);


$__server_REQUEST_METHOD = \App\Support\SupportContext::getServerValue('REQUEST_METHOD');
if (get_user_class() < UC_SYSOP)
	permissiondenied();

$shownotice=false;
stderr("Error", "Hard deletion of users is not recommended and can cause many problems.");
if ($__server_REQUEST_METHOD == "POST")
{
	if (\App\Support\SupportContext::getPost('sure'))
	{
		$deletecount = \App\Models\User::query()->where('enabled', 'no')->delete();
		$shownotice=true;
	}
}
if (empty($nexus_legacy_layout)) { stdhead($lang_deletedisabled['head_delete_diasabled']); }
if (empty($nexus_legacy_layout)) { begin_main_frame(); }
?>
<h1 align="center"><?php echo $lang_deletedisabled['text_delete_diasabled']?></h1>
<?php
if ($shownotice)
{
?>
<div style="text-align: center;"><?php echo $deletecount.$lang_deletedisabled['text_users_are_disabled']?></div>
<?php
}
else
{
?>
<div style="text-align: center;"><?php echo $lang_deletedisabled['text_delete_disabled_note']?></div>
<div style="text-align: center; margin-top: 10px;">
<form method="post" action="?">
<input type="hidden" name="sure" value="1" />
<input type="submit" value="<?php echo $lang_deletedisabled['submit_delete_all_disabled_users']?>" />
</form>
</div>
<?php
}
if (empty($nexus_legacy_layout)) { end_main_frame(); }
if (empty($nexus_legacy_layout)) { stdfoot(); }

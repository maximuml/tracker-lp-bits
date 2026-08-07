<?php
extract($context, EXTR_SKIP);
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING & ~E_DEPRECATED);

if (get_user_class() < UC_ADMINISTRATOR)
	stderr("Error", "Permission denied.");

$action = ((\App\Support\SupportContext::getPost('action') !== null)) ? htmlspecialchars(\App\Support\SupportContext::getPost('action')) : (((\App\Support\SupportContext::getQuery('action') !== null)) ? htmlspecialchars(\App\Support\SupportContext::getQuery('action')) : 'main');
if ($action == 'setallfree')
{
	\Nexus\Database\NexusDB::table('torrents_state')->update(['global_sp_state' => 2]);
	$Cache->delete_value('global_promotion_state');
	stderr('Success','All torrents have been set free..');
}
elseif ($action == 'setall2up')
{
	\Nexus\Database\NexusDB::table('torrents_state')->update(['global_sp_state' => 3]);
	$Cache->delete_value('global_promotion_state');
	stderr('Success','All torrents have been set 2x up..');
}
elseif ($action == 'setall2up_free')
{
	\Nexus\Database\NexusDB::table('torrents_state')->update(['global_sp_state' => 4]);
	$Cache->delete_value('global_promotion_state');
	stderr('Success','All torrents have been set 2x up and free..');
}
elseif ($action == 'setallhalf_down')
{
	\Nexus\Database\NexusDB::table('torrents_state')->update(['global_sp_state' => 5]);
	$Cache->delete_value('global_promotion_state');
	stderr('Success','All torrents have been set half down..');
}
elseif ($action == 'setall2up_half_down')
{
	\Nexus\Database\NexusDB::table('torrents_state')->update(['global_sp_state' => 6]);
	$Cache->delete_value('global_promotion_state');
	stderr('Success','All torrents have been set half down..');
}
elseif ($action == 'setallnormal') 
{
	\Nexus\Database\NexusDB::table('torrents_state')->update(['global_sp_state' => 1]);
	$Cache->delete_value('global_promotion_state');
	stderr('Success','All torrents have been set normal..');
}
elseif ($action == 'main')
{
	stderr('Select action','Click <a class=altlink href=freeleech.php?action=setallfree>here</a> to set all torrents free.. <br /> Click <a class=altlink href=freeleech.php?action=setall2up>here</a> to set all torrents 2x up..<br /> Click <a class=altlink href=freeleech.php?action=setall2up_free>here</a> to set all torrents 2x up and free.. <br />Click <a class=altlink href=freeleech.php?action=setallhalf_down>here</a> to set all torrents half down..<br />Click <a class=altlink href=freeleech.php?action=setall2up_half_down>here</a> to set all torrents 2x up and half down..<br />Click <a class=altlink href=freeleech.php?action=setallnormal>here</a> to set all torrents normal..', false);
}

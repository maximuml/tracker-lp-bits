<?php
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING & ~E_DEPRECATED);
//loggedinorreturn();
\App\Support\Html::stdhead($lang_rules['head_rules']);
$Cache->new_page('rules', 900, true);
if (!$Cache->get_page())
{
$Cache->add_whole_row();
//make_folder("cache/" , get_langfolder_cookie());
//cache_check ('rules');
\App\Support\Frame::mainFrameOpen();

$lang_id = \App\Support\Locale::guestIdWithContext();
$is_rulelang = \Nexus\Database\NexusDB::table('language')->where('id', $lang_id)->value('rule_lang');
if (!$is_rulelang){
	$lang_id = 6; //English
}
$rules = \Nexus\Database\NexusDB::table('rules')->where('lang_id', $lang_id)->orderBy('id')->get();
foreach ($rules as $rule){
	$arr = (array) $rule;
	\App\Support\Html::beginFrame($arr['title'], false);
	print(\App\Support\Format::formatComment($arr["text"]));
	\App\Support\Html::endFrame();
}
\App\Support\Frame::mainFrameClose();
}
//cache_save ('rules');
\App\Support\Html::stdfoot();

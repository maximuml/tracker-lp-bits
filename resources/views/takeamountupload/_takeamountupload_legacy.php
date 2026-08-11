<?php
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING & ~E_DEPRECATED);


$__server_REQUEST_METHOD = \App\Support\SupportContext::getServerValue('REQUEST_METHOD');
if (get_user_class() < UC_SYSOP)
	stderr("Sorry", "Permission denied.");

if ($__server_REQUEST_METHOD != "POST") {
	if (((\App\Support\SupportContext::getQuery('sent') !== null)) && \App\Support\SupportContext::getQuery('sent') == 1) {
		stdhead("Add Upload");
		stdmsg("Success", "Upload amount has been added successfully.");
		stdfoot();
		return;
	}
	stderr("Error", "Permission denied!");
}

$sender_id = (\App\Support\SupportContext::getPost('sender') == 'system' ? 0 : (int)$CURUSER['id']);
$added = date("Y-m-d H:i:s");
$msg = trim(\App\Support\SupportContext::getPost('msg'));
$amount = \App\Support\SupportContext::getPost('amount');
if (!$msg || !$amount)
	stderr("Error","Don't leave any fields blank.");
if(!is_numeric($amount))
	stderr("Error","amount must be numeric");
$updateset = (array) \App\Support\SupportContext::getPost('clases');
foreach ($updateset as $class) {
	if (!is_valid_id($class) && $class != 0)
		stderr("Error","Invalid Class");
}
$subject = trim(\App\Support\SupportContext::getPost('subject'));

$amount = \App\Support\Format::bytesFromUnit($amount,"G");
\App\Models\User::query()->whereIn('class', $updateset)->increment('uploaded', $amount);

$userIds = \App\Models\User::query()->whereIn('class', $updateset)->pluck('id')->all();
foreach ($userIds as $userId)
{
	\Nexus\Database\NexusDB::table('messages')->insert([
	    'sender' => $sender_id,
	    'receiver' => $userId,
	    'added' => $added,
	    'subject' => $subject,
	    'msg' => $msg,
	]);
}

header("Location: takeamountupload.php?sent=1");

<?php
extract($GLOBALS, EXTR_SKIP);
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING & ~E_DEPRECATED);

if ($_SERVER["REQUEST_METHOD"] != "POST")
	stderr("Error", "Permission denied!");

if (get_user_class() < UC_SYSOP)
	stderr("Sorry", "Permission denied.");

$sender_id = ($_POST['sender'] == 'system' ? 0 : (int)$CURUSER['id']);
$added = date("Y-m-d H:i:s");
$msg = trim($_POST['msg']);
$amount = $_POST['amount'];
if (!$msg || !$amount)
	stderr("Error","Don't leave any fields blank.");
if(!is_numeric($amount))
	stderr("Error","amount must be numeric");
$updateset = (array) $_POST['clases'];
foreach ($updateset as $class) {
	if (!is_valid_id($class) && $class != 0)
		stderr("Error","Invalid Class");
}
$subject = trim($_POST['subject']);

$amount = getsize_int($amount,"G");
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

header("Location: amountupload.php?sent=1");

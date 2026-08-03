<?php
require_once("../include/bittorrent.php");
header("Content-Type: text/html; charset=utf-8");
if (!mkglobal("username"))
	die();
dbconn();
require_once(get_langfile_path("", false, get_langfolder_cookie()));
failedloginscheck ();
cur_user_check () ;
$ip = getip();
function bark($text = "")
{
  global $lang_takelogin;
  $text =  ($text == "" ? $lang_takelogin['std_login_fail_note'] : $text);
  stderr($lang_takelogin['std_login_fail'], $text,false);
}
if ($iv == "yes") {
    check_code($_POST['imagehash'] ?? null, $_POST['imagestring'] ?? null, 'login.php', true);
}
if (empty($_POST['password'])) {
    failedlogins("Require password parameter.");
}

$user = \App\Models\User::query()->where('username', $username)->first(['id', 'passhash', 'secret', 'auth_key', 'enabled', 'status', 'two_step_secret', 'lang']);
if (!$user)
    failedlogins();
$row = $user->makeVisible(['passhash', 'secret', 'auth_key'])->toArray();

if ($row['status'] == 'pending')
	failedlogins($lang_takelogin['std_user_account_unconfirmed']);
if ($row["enabled"] == "no" && \App\Models\Setting::getSelfEnableBonus() <= 0) {
    bark($lang_takelogin['std_account_disabled']);
}

if (!empty($row['two_step_secret'])) {
    if (empty($_POST['two_step_code'])) {
        failedlogins($lang_takelogin['std_require_two_step_code']);
    }
    if (!\App\Support\TwoFactorAuthHelper::verifyCode($row['two_step_secret'], $_POST['two_step_code'])) {
        failedlogins($lang_takelogin['std_invalid_two_step_code']);
    }
}
$log = "user: {$row['id']}, ip: $ip";
$update = [];
$passwordHash = hash('sha256', $row['secret'] . hash('sha256', $_POST['password']));
$log .= ", passwordHash: $passwordHash";
if (empty($row['auth_key'])) {
    //先使用旧的验证方式验证
    if ($row["passhash"] != md5($row["secret"] . $_POST['password'] . $row["secret"])) {
        do_log("$log, md5 not equal");
        login_failedlogins();
    }
    $log .= ", no auth_key, upgrade password hash";
    //自动升级为新的验证方式
    $update['passhash'] = $row['passhash'] = $passwordHash;
}
//后端用 passhash 验证（与 legacy challenge-response 等价，但由服务端生成）
$challenge = mksecret();
$_POST['response'] = hash_hmac('sha256', $passwordHash, $challenge);
$log .= ", server generate response: " . $_POST['response'];
$expectedResponse = hash_hmac('sha256', $row['passhash'], $challenge);
$log .= ", expectedResponse: $expectedResponse";
if (!hash_equals($expectedResponse, $_POST["response"])) {
    do_log("$log, !hash_equals");
    login_failedlogins();
}
do_log("$log, login successful");
$userRep = new \App\Repositories\UserRepository();
$userRep->saveLoginLog($row['id'], $ip, 'Web', true);

//update user lang
$language = \App\Models\Language::query()->where("site_lang_folder", get_langfolder_cookie())->first();

if ($language && $language->id != $row["lang"]) {
    do_log(sprintf("update user: %s lang: %s => %s", $row["id"], $row["lang"], $language->id));
    $update["lang"] = $language->id;
}
if (empty($row['auth_key'])) {
    $row['auth_key'] = $update['auth_key'] = hash('sha256', mksecret(32));
}
if (!empty($update)) {
    \App\Models\User::query()->where("id", $row["id"])->update($update);
    clear_user_cache($row["id"]);
}

if (isset($_POST["logout"]) && $_POST["logout"] == "yes")
{
	logincookie($row["id"], $row['auth_key'],900);
}
else
{
    logincookie($row["id"], $row['auth_key']);
}

if (!empty($_POST["returnto"]))
	nexus_redirect($_POST['returnto']);
else
	nexus_redirect("index.php");
?>

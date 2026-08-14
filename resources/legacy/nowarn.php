<?php
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING & ~E_DEPRECATED);

// Auto-generated legacy bridge shims
if (!isset($CURUSER)) $CURUSER = (array) (\App\Support\SupportContext::getUser() ?? []);
if (!isset($Cache)) $Cache = \App\Support\SupportContext::getCache();
if (!isset($BASEURL)) $BASEURL = \App\Support\SupportContext::getGlobal('BASEURL', '');
if (!isset($lang_nowarn)) $lang_nowarn = (array) (\App\Support\SupportContext::getGlobal('lang_nowarn') ?? []);
?>
<?php
if ((\App\Support\SupportContext::getPost("nowarned") !== null) && (\App\Support\SupportContext::getPost("nowarned") == "nowarned")) {
    if (\App\Support\UserDisplay::currentClass() < UC_MODERATOR) {
        \App\Support\LegacyResponse::abort("Sorry", "Access denied.");
    }
    if (empty(\App\Support\SupportContext::getPost("usernw")) && empty(\App\Support\SupportContext::getPost("desact")) && empty(\App\Support\SupportContext::getPost("delete"))) {
        \App\Support\LegacyResponse::abort("Update Has Failed !", "You Must Select A User To Edit.");
    }

    if (!empty(\App\Support\SupportContext::getPost("usernw"))) {
        $modcomment = date("Y-m-d") . " - Warning Removed By " . $CURUSER['username'];
        \App\Models\User::query()->whereIn('id', \App\Support\SupportContext::getPost('usernw'))
            ->update([
                'warned' => 'no',
                'warneduntil' => null,
                'modcomment' => \Nexus\Database\NexusDB::raw("if(modcomment = '', '$modcomment', concat_ws('\n', '$modcomment', modcomment))")
            ]);
    }

    if (!empty(\App\Support\SupportContext::getPost("desact"))) {
        \App\Models\User::query()->whereIn('id', \App\Support\SupportContext::getPost('desact'))->update(['enabled' => 'no']);
    }
}

header("Location: warned.php");
return;
?>

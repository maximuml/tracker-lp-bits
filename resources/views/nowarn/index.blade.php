@php
function bark($msg) {
    \App\Support\Html::stdhead();
    \App\Support\Html::stdMessage("Update Has Failed !", $msg);
    \App\Support\Html::stdfoot();
    exit;
}

if ((\App\Support\SupportContext::getPost("nowarned") !== null) && (\App\Support\SupportContext::getPost("nowarned") == "nowarned")) {
    if (\App\Support\UserDisplay::currentClass() < UC_MODERATOR) {
        \App\Support\LegacyResponse::abort("Sorry", "Access denied.");
    }
    if (empty(\App\Support\SupportContext::getPost("usernw")) && empty(\App\Support\SupportContext::getPost("desact")) && empty(\App\Support\SupportContext::getPost("delete"))) {
        bark("You Must Select A User To Edit.");
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
@endphp

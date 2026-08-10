<?php
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING & ~E_DEPRECATED);



$__server_HTTP_REFERER = \App\Support\SupportContext::getServerValue('HTTP_REFERER');
$__server_REQUEST_METHOD = \App\Support\SupportContext::getServerValue('REQUEST_METHOD');
$isLogin = (isset($CURUSER['id']));
$isAdmin = \App\Auth\Permission::can(\App\Enums\Permission\PermissionEnum::STAFF_MEMBER);

if($isLogin && !$isAdmin) {
    permissiondenied();
}
if (!$isAdmin && !\App\Models\Setting::getIsComplainEnabled()) {
    stderr($lang_functions['std_error'], $lang_complains['complain_not_enabled']);
}

$uid = $CURUSER['id'] ?? 0;
if($__server_REQUEST_METHOD === 'POST'){
    switch($action = filter_var(\App\Support\SupportContext::getPost('action'), FILTER_SANITIZE_FULL_SPECIAL_CHARS)){
        case 'new':
            cur_user_check();
            check_code (\App\Support\SupportContext::getPost('imagehash') ?? null, \App\Support\SupportContext::getPost('imagestring') ?? null,'complains.php');
            \Nexus\Database\NexusLock::lockOrFail("complains:lock:" . getip(), 10);
            $email = filter_var(\App\Support\SupportContext::getPost('email'), FILTER_VALIDATE_EMAIL);
            \Nexus\Database\NexusLock::lockOrFail("complains:lock:" . $email, 600);
            $body = filter_var(\App\Support\SupportContext::getPost('body'), FILTER_SANITIZE_FULL_SPECIAL_CHARS);
            if(empty($email) || empty($body)) stderr($lang_functions['std_error'], $lang_complains['text_new_failure']);
            $user = \App\Models\User::query()->where('email', $email)->where('enabled', 'no')->first();
            if (!$user) {
                stderr($lang_functions['std_error'], $lang_complains['text_new_failure']);
            }
            $complainId = \Nexus\Database\NexusDB::table('complains')->insertGetId([
                'uuid' => \Nexus\Database\NexusDB::raw('UUID()'),
                'email' => $email,
                'body' => $body,
                'added' => date('Y-m-d H:i:s'),
                'ip' => getip(),
            ]);
            $Cache->delete_value('COMPLAINTS_COUNT_CACHE');
            nexus_redirect(sprintf('complains.php?action=view&id=%s', \Nexus\Database\NexusDB::table('complains')->where('id', $complainId)->value('uuid')));
            break;
        case 'reply':
            $id = filter_var(\App\Support\SupportContext::getPost('id'), FILTER_VALIDATE_INT);
            $body = filter_var(\App\Support\SupportContext::getPost('body'), FILTER_SANITIZE_FULL_SPECIAL_CHARS);
            $complain = \App\Models\Complain::query()->findOrFail($id);
            if(empty($id) || empty($body)) stderr($lang_functions['std_error'], $lang_complains['text_new_failure']);
            \Nexus\Database\NexusDB::table('complain_replies')->insert([
                'complain' => $id,
                'userid' => $uid,
                'added' => date('Y-m-d H:i:s'),
                'body' => $body,
                'ip' => getip(),
            ]);
            if ($uid > 0) {
                try {
                    $toolRep = new \App\Repositories\ToolRepository();
                    $toolRep->sendMail($complain->email, $lang_complains['reply_notify_subject'], sprintf($lang_complains['reply_notify_body'], \App\Support\Config\SiteConfig::current()->basic->siteName(), getSchemeAndHttpHost() . '/complains.php?action=view&id=' . $complain->uuid));
                } catch (\Exception $exception) {
                    do_log($exception->getMessage(), 'error');
                }
            }
            nexus_redirect($__server_HTTP_REFERER);
            break;
        case 'answered':
        case 'unanswered':
            if(!$isAdmin) permissiondenied();
            $id = filter_var(\App\Support\SupportContext::getPost('id'), FILTER_VALIDATE_INT);
            if(!$id) permissiondenied();
            \Nexus\Database\NexusDB::table('complains')->where('id', $id)->update([
                'answered' => $action == 'answered' ? 1 : 0,
            ]);
            $Cache->delete_value('COMPLAINTS_COUNT_CACHE');
            nexus_redirect($__server_HTTP_REFERER);
            break;
        default:
            permissiondenied();
    }
}else{
    $action = filter_var(\App\Support\SupportContext::getQuery('action'), FILTER_SANITIZE_FULL_SPECIAL_CHARS);
    if (empty($action) && $isAdmin) {
        $action = 'list';
    }
    switch ($action){
        case 'list':
            if(!$isAdmin) permissiondenied();
            $showTable = function($rows){
$lang_complains = (array) (\App\Support\SupportContext::getGlobal('lang_complains') ?? []);
                echo '<table width="100%">';
                echo EchoRow('colhead', $lang_complains['th_complain_at'], $lang_complains['th_complain_account'], $lang_complains['th_action_view']);
                foreach ($rows as $r) {
                    $row = (array) $r;
                    echo EchoRow('rowfollow', gettime($row['added']), htmlspecialchars($row['email']), sprintf('<a href="?action=view&id=%s" class="faqlink">%s</a>', $row['uuid'], $lang_complains['th_action_view']));
                }
                echo '</table>';
            };
            stdhead($lang_complains['text_complain']);
            begin_main_frame();
            if(!((\App\Support\SupportContext::getQuery('page') !== null))){
                $pendingRows = \Nexus\Database\NexusDB::table('complains')->where('answered', 0)->orderByDesc('id')->get(['added', 'uuid', 'email']);
                begin_frame($lang_complains['pending_complaints']);
                if($pendingRows->count()){
                    $showTable($pendingRows);
                }else{
                    echo $lang_complains['no_pending_complaints'];
                }
                end_frame();
            }
            begin_frame($lang_complains['complaints_processed']);
            list($pagertop, $pagerbottom, , $offset, $rpp) = pager(20, \Nexus\Database\NexusDB::table('complains')->where('answered', 1)->count(), '?action=list&');
            $processedRows = \Nexus\Database\NexusDB::table('complains')
                ->where('answered', 1)
                ->orderByDesc('id')
                ->offset($offset)
                ->limit($rpp)
                ->get(['added', 'uuid', 'email']);
            if($processedRows->count()){
                echo $pagertop;
                $showTable($processedRows);
                echo $pagerbottom;
            }else{
                echo $lang_complains['no_complaints_have_been_processed'];
            }
            end_frame();
            end_main_frame();
            stdfoot();
            break;
        case 'view':
            $uuid = filter_var(\App\Support\SupportContext::getQuery('id'), FILTER_SANITIZE_FULL_SPECIAL_CHARS);
            if(strlen($uuid) != 36) permissiondenied();
            $complain = (array) \Nexus\Database\NexusDB::table('complains')->where('uuid', $uuid)->first();
            if(!$complain) permissiondenied();
            $user = \App\Models\User::query()->where('email', $complain['email'])->first();
            stdhead($lang_complains['text_complain']);
            begin_main_frame();
            if(!$isLogin){
                begin_frame($lang_complains['text_created_title']);
                printf('<p style="font-weight: bold; color: red">%s</p>', $lang_complains['text_created_note']);
                end_frame();
            }
            begin_frame($lang_complains['text_new_body']);
            printf('%s：%s<br />%s %s', $lang_complains['text_added'], gettime($complain['added']), $lang_complains['text_new_email'], htmlspecialchars($complain['email']));
            if($isAdmin) {
                if ($user) {
                    printf(' [<a href="userdetails.php?id=%s" class="faqlink" target="_blank">%s</a>]', $user->id, $user->username);
                    printf(' [<a href="user-ban-log.php?q=%s" class="faqlink" target="_blank">%s</a>]', urlencode($user->username), $lang_complains['text_view_band_log']);
                } else {
                    printf(' [<a href="usersearch.php?em=%s" class="faqlink" target="_blank">%s</a>]', urlencode($complain['email']), $lang_complains['text_search_account']);
                }
                printf('<br />IP: ' . htmlspecialchars($complain['ip']));
            }
            echo '<hr />', format_comment($complain['body']);
            end_frame();
            // REPLIES
            begin_frame($lang_complains['text_replies']);
            $replyRows = \Nexus\Database\NexusDB::table('complain_replies')->where('complain', $complain['id'])->orderByDesc('id')->get();
            if($replyRows->count()){
                foreach ($replyRows as $r) {
                    $row = (array) $r;
                    printf('<b>%s @ %s', $row['userid'] ? get_plain_username($row['userid']) : $lang_complains['text_complainer'], gettime($row['added']));
                    if ($isAdmin) {
                        printf(' (%s)', htmlspecialchars($row['ip']));
                    }
                    echo ': </b>';
                    echo format_comment($row['body']) . '<hr />';
                }
            }else{
                printf('<p align="center">%s</p>', $lang_complains['text_no_replies']);
            }
            end_frame();
            // NEW REPLY
            if($complain['answered']){
                printf('<p align="center">%s</p>', $lang_complains['text_closed']);
            }else{
                printf('<br /><br /><table style="border:1px solid #000000;" align="center"><tr><td class="text" align="center"><b>%s</b><br /><br /><form id="reply" method="post" action="" onsubmit="return postvalid(this);"><input type="hidden" name="action" value="reply" /><input type="hidden" name="id" value="%u" /><br />', $lang_complains['text_reply'], $complain['id']);
                quickreply('reply', 'body', $lang_complains['text_reply']);
                echo '</form></td></tr></table>';
            }
            if($isAdmin){
                printf('<form action="" method="post" style="text-align: center; margin-top: 2em"><input type="hidden" name="action" value="%s" /><input type="hidden" name="id" value="%u" /><button>%s</button></form>', $complain['answered'] ? 'unanswered' : 'answered', $complain['id'],$complain['answered'] ? $lang_complains['text_unanswer_it'] : $lang_complains['text_answer_it']);
            }
            end_main_frame();
            stdfoot();
            break;
        case 'compose':
        default:
            cur_user_check();
            stdhead($lang_complains['text_complain']);
            ?>
            <h2><?= $lang_complains['text_new_complain'] ?></h2>
            <form action="" method="post">
                <input type="hidden" name="action" value="new" />
                <?php
                $inputStyle = 'style="width: min(100%, 420px); min-width: 180px; border: 1px solid gray; box-sizing: border-box"';
                $textareaStyle = 'style="width: min(100%, 420px); min-width: 180px; border: 1px solid gray; box-sizing: border-box; height: 250px; resize: vertical;"';
                ?>
                <table border="0" cellpadding="5">
                    <tr><td class="rowhead"><?php echo $lang_complains['text_new_email']?></td><td class="rowfollow" align="left"><input type="email" name="email" <?php echo $inputStyle; ?> autocomplete="email" /></td></tr>
                    <tr><td class="rowhead"><?php echo $lang_complains['text_new_body']?></td><td class="rowfollow" align="left"><textarea name="body" <?php echo $textareaStyle; ?> placeholder="<?= $lang_complains['text_new_body_placeholder'] ?>"></textarea></td></tr>
                    <?php show_image_code (); ?>
                    <tr><td class="toolbox" colspan="2" align="center"><input type="submit" value="<?= $lang_complains['text_new_submit']?>" class="btn" /></td></tr>
                </table>
            </form>
            <?php
            stdfoot();
    }
}

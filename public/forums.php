<?php
require "../include/bittorrent.php";
dbconn();
require_once(get_langfile_path());
loggedinorreturn();
parked();

// ------------- start: functions ------------------//
//print forum stats
function forum_stats ()
{
	global $lang_forums, $Cache, $today_date;

	if (!$activeforumuser_num = $Cache->get_value('active_forum_user_count')){
		$secs = 900;
		$dt = date("Y-m-d H:i:s",(TIMENOW - $secs));
		$activeforumuser_num = \App\Models\User::query()->where('forum_access', '>=', $dt)->count();
		$Cache->cache_value('active_forum_user_count', $activeforumuser_num, 300);
	}
	if ($activeforumuser_num){
		$forumusers = $lang_forums['text_there'].is_or_are($activeforumuser_num)."<b>".$activeforumuser_num."</b>".$lang_forums['text_online_user'].add_s($activeforumuser_num).$lang_forums['text_in_forum_now'];
	}
	else
		$forumusers = $lang_forums['text_no_active_users'];
?>
<h2 align="left"><?php echo $lang_forums['text_stats'] ?></h2>
<table width="100%"><tr><td class="text">
<?php
	if (!$postcount = $Cache->get_value('total_posts_count')){
		$postcount = \App\Models\Post::query()->count();
		$Cache->cache_value('total_posts_count', $postcount, 96400);
	}
	if (!$topiccount = $Cache->get_value('total_topics_count')){
		$topiccount = \App\Models\Topic::query()->count();
		$Cache->cache_value('total_topics_count', $topiccount, 96500);
	}
	if (!$todaypostcount = $Cache->get_value('today_'.$today_date.'_posts_count')) {
		$todaypostcount = \App\Models\Post::query()->where('added', '>', date("Y-m-d"))->count();
		$Cache->cache_value('today_'.$today_date.'_posts_count', $todaypostcount, 700);
	}
	print($lang_forums['text_our_members_have'] ."<b>".$postcount."</b>". $lang_forums['text_posts_in_topics']."<b>".$topiccount."</b>".$lang_forums['text_in_topics']."<b><font class=\"new\">".$todaypostcount."</font></b>".$lang_forums['text_new_post'].add_s($todaypostcount).$lang_forums['text_posts_today']."<br /><br />");
	print($forumusers);
?>
</td></tr></table>
<?php
}

//set all topics as read
function catch_up()
{
	global $CURUSER, $Cache;

	if (!$CURUSER)
		die;
	\Nexus\Database\NexusDB::table('readposts')->where('userid', $CURUSER['id'])->delete();
	$Cache->delete_value('user_'.$CURUSER['id'].'_last_read_post_list');
	$lastpostid = \App\Models\Post::query()->orderByDesc('id')->value('id');
	if ($lastpostid){
		$CURUSER['last_catchup'] = $lastpostid;
		\App\Models\User::query()->where('id', $CURUSER['id'])->update(['last_catchup' => $lastpostid]);
	}
}

//return image
function get_topic_image($status= "read"){
	global $lang_forums;
	switch($status){
		case "read": {
			return "<img class=\"unlocked\" src=\"pic/trans.gif\" alt=\"read\" title=\"".$lang_forums['title_read']."\" />";
			break;
			}
		case "unread": {
			return "<img class=\"unlockednew\" src=\"pic/trans.gif\" alt=\"unread\" title=\"".$lang_forums['title_unread']."\" />";
			break;
		}
		case "locked": {
			return "<img class=\"locked\" src=\"pic/trans.gif\" alt=\"locked\" title=\"".$lang_forums['title_locked']."\" />";
			break;
		}
		case "lockednew": {
			return "<img class=\"lockednew\" src=\"pic/trans.gif\" alt=\"lockednew\" title=\"".$lang_forums['title_locked_new']."\" />";
			break;
		}
	}
}

function highlight_topic($subject, $hlcolor=0)
{
	$colorname=get_hl_color($hlcolor);
	if ($colorname)
		$subject = "<b><font color=\"".$colorname."\">".$subject."</font></b>";
	return $subject;
}

function check_whether_exist($id, $place='forum'){
	global $lang_forums;
	int_check($id,true);
	switch ($place){
		case 'forum':
		{
			if (!\App\Models\Forum::query()->where('id', $id)->exists())
				stderr($lang_forums['std_error'],$lang_forums['std_no_forum_id']);
			break;
		}
		case 'topic':
		{
			$topic = \App\Models\Topic::query()->where('id', $id)->first(['forumid']);
			if (!$topic)
				stderr($lang_forums['std_error'],$lang_forums['std_bad_topic_id']);
			check_whether_exist($topic->forumid, 'forum');
			break;
		}
		case 'post':
		{
			$post = \App\Models\Post::query()->where('id', $id)->first(['topicid']);
			if (!$post)
				stderr($lang_forums['std_error'],$lang_forums['std_no_post_id']);
			check_whether_exist($post->topicid, 'topic');
			break;
		}
	}
}

//update the last post of a topic
function update_topic_last_post($topicid)
{
	global $lang_forums;
	$postid = \App\Models\Post::query()->where('topicid', $topicid)->orderByDesc('id')->value('id');
	if (!$postid) {
		die($lang_forums['std_no_post_found']);
	}
	\App\Models\Topic::query()->where('id', $topicid)->update(['lastpost' => $postid]);
}

function get_forum_row($forumid = 0)
{
	global $Cache;
	if (!$forums = $Cache->get_value('forums_list')){
		$forums = \App\Models\Forum::query()->orderBy('forid')->orderBy('sort')->get()->keyBy('id')->map(fn($f) => $f->toArray())->all();
		$Cache->cache_value('forums_list', $forums, 86400);
	}
	if (!$forumid)
		return $forums;
	else return $forums[$forumid] ?? null;
}
function get_last_read_post_id($topicid) {
	global $CURUSER, $Cache;
	static $ret;
	if (!$ret && !$ret = $Cache->get_value('user_'.$CURUSER['id'].'_last_read_post_list')){
		$ret = [];
		$rows = \Nexus\Database\NexusDB::table('readposts')->where('userid', $CURUSER['id'])->get(['topicid', 'lastpostread']);
		if ($rows->isNotEmpty()){
			foreach ($rows as $row)
				$ret[$row->topicid] = $row->lastpostread;
			$Cache->cache_value('user_'.$CURUSER['id'].'_last_read_post_list', $ret, 900);
		}
		else $Cache->cache_value('user_'.$CURUSER['id'].'_last_read_post_list', 'no record', 900);
	}
	if ($ret != "no record" && isset($ret[$topicid]) && $CURUSER['last_catchup'] < $ret[$topicid]){
		return $ret[$topicid];
	}
	elseif ($CURUSER['last_catchup'])
		return $CURUSER['last_catchup'];
	else return 0;
}

//-------- Inserts a compose frame
function insert_compose_frame($id, $type = 'new')
{
	global $maxsubjectlength, $CURUSER;
	global $lang_forums;
	$hassubject = false;
	$subject = "";
	$body = "";
	print("<form id=\"compose\" method=\"post\" name=\"compose\" action=\"?action=post\">\n");
	switch ($type){
		case 'new':
		{
			$forum = \App\Models\Forum::query()->where('id', $id)->first(['name']);
			$forumname = $forum ? $forum->name : '';
			$title = $lang_forums['text_new_topic_in']." <a href=\"".htmlspecialchars("?action=viewforum&forumid=".$id)."\">".htmlspecialchars($forumname)."</a> ".$lang_forums['text_forum'];
			$hassubject = true;
			break;
		}
		case 'reply':
		{
			$topic = \App\Models\Topic::query()->where('id', $id)->first(['subject']);
			$topicname = $topic ? $topic->subject : '';
			$title = $lang_forums['text_reply_to_topic']." <a href=\"".htmlspecialchars("?action=viewtopic&topicid=".$id)."\">".htmlspecialchars($topicname)."</a> ";
			break;
		}
		case 'quote':
		{
			$post = \App\Models\Post::query()->where('id', $id)->first(['topicid', 'body', 'userid']);
			if (!$post)
				stderr($lang_forums['std_error'], $lang_forums['std_no_post_id']);
			$topicid = $post->topicid;
			$topic = \App\Models\Topic::query()->where('id', $topicid)->first(['subject']);
			$topicname = $topic ? $topic->subject : '';
			$title = $lang_forums['text_reply_to_topic']." <a href=\"".htmlspecialchars("?action=viewtopic&topicid=".$topicid)."\">".htmlspecialchars($topicname)."</a> ";
			$username = \App\Models\User::query()->where('id', $post->userid)->value('username');
			$body = "[quote=".htmlspecialchars($username)."]".htmlspecialchars(unesc($post->body))."[/quote]";
			print("<input type=\"hidden\" name=\"postid\" value=\"".$id."\" />");
			$id = $topicid;
			$type = 'reply';
			break;
		}
		case 'edit':
		{
			$post = \App\Models\Post::query()->where('id', $id)->first(['topicid', 'body']);
			if (!$post)
				die;
			$topicid = $post->topicid;
			$firstpost = \App\Models\Post::query()->where('topicid', $topicid)->min('id');
			if ($firstpost == $id){
				$topic = \App\Models\Topic::query()->where('id', $topicid)->first(['subject']);
				$subject = $topic ? $topic->subject : '';
				$hassubject = true;
			}
			$body = htmlspecialchars(unesc($post->body));
			$title = $lang_forums['text_edit_post'];
			break;
		}
		default:
		{
			die;
		}
	}
	print("<input type=\"hidden\" name=\"id\" value=\"".$id."\" />");
	print("<input type=\"hidden\" name=\"type\" value=\"".$type."\" />");
	begin_compose($title, $type, $body, $hassubject, $subject);
	end_compose();
	print("</form>");
}
// ------------- end: functions ------------------//
// ------------- start: Global variables ------------------//
$maxsubjectlength = 100;
$postsperpage = $CURUSER["postsperpage"];
if (!$postsperpage){
	if (is_numeric($forumpostsperpage))
		$postsperpage = $forumpostsperpage;//system-wide setting
	else $postsperpage = 10;
}
//get topics per page
$topicsperpage = $CURUSER["topicsperpage"];
if (!$topicsperpage){
	if (is_numeric($forumtopicsperpage_main))
		$topicsperpage = $forumtopicsperpage_main;//system-wide setting
	else $topicsperpage = 20;
}
$today_date = date("Y-m-d",TIMENOW);
// ------------- end: Global variables ------------------//

$action = htmlspecialchars(trim($_GET["action"] ?? ''));

//-------- Action: New topic
if ($action == "newtopic")
{
	$forumid = intval($_GET["forumid"] ?? 0);
	check_whether_exist($forumid, 'forum');
	stdhead($lang_forums['head_new_topic']);
	begin_main_frame();
	insert_compose_frame($forumid,'new');
	end_main_frame();
	stdfoot();
	die;
}
if ($action == "quotepost")
{
	$postid = intval($_GET["postid"] ?? 0);
	check_whether_exist($postid, 'post');
    if (!can_view_post($CURUSER['id'], $postid)) {
        permissiondenied();
    }
	stdhead($lang_forums['head_post_reply']);
	begin_main_frame();
	insert_compose_frame($postid, 'quote');
	end_main_frame();
	stdfoot();
	die;
}

//-------- Action: Reply

if ($action == "reply")
{
	$topicid = intval($_GET["topicid"] ?? 0);
	check_whether_exist($topicid, 'topic');
	stdhead($lang_forums['head_post_reply']);
	begin_main_frame();
	insert_compose_frame($topicid, 'reply');
	end_main_frame();
	stdfoot();
	die;
}

//-------- Action: Edit post

if ($action == "editpost")
{
	$postid = intval($_GET["postid"] ?? 0);
	check_whether_exist($postid, 'post');

	$post = \App\Models\Post::query()->where('id', $postid)->first(['userid', 'topicid']);
	if (!$post)
		stderr($lang_forums['std_error'], $lang_forums['std_no_post_id']);

	$topic = \App\Models\Topic::query()->where('id', $post->topicid)->first(['locked']);
	$locked = $topic && ($topic->locked == 'yes');

	$ismod = is_forum_moderator($postid, 'post');
	if (($CURUSER["id"] != $post->userid || $locked) && !user_can('postmanage') && !$ismod)
		permissiondenied();

	stdhead($lang_forums['text_edit_post']);
	begin_main_frame();
	insert_compose_frame($postid, 'edit');
	end_main_frame();
	stdfoot();
	die;
}

//-------- Action: Post
if ($action == "post")
{
	if ($CURUSER["forumpost"] == 'no')
	{
		stderr($lang_forums['std_sorry'], $lang_forums['std_unauthorized_to_post'],false);
		die;
	}
	$id = $_POST["id"];
	$type = $_POST["type"];
	$subject = $_POST["subject"] ?? '';
	$body = trim($_POST["body"]);
	$hassubject = false;
	switch ($type){
		case 'new':
		{
			check_whether_exist($id, 'forum');
			$forumid = $id;
			$hassubject = true;
			break;
		}
		case 'reply':
		{
			check_whether_exist($id, 'topic');
			$topicid = $id;
			$forumid = \App\Models\Topic::query()->where('id', $topicid)->value('forumid');
			$quotepostid = $_POST["postid"];
			break;
		}
		case 'edit':
		{
			check_whether_exist($id, 'post');
			$post = \App\Models\Post::query()->where('id', $id)->first(['topicid']);
			if (!$post) die;
			$topicid = $post->topicid;
			$forum = \App\Models\Topic::query()->where('id', $topicid)->first(['forumid']);
			$forumid = $forum ? $forum->forumid : 0;
			$firstpost = \App\Models\Post::query()->where('topicid', $topicid)->min('id');
			if ($firstpost == $id){
				$hassubject = true;
			}
			break;
		}
		default:
		{
			die;
		}
	}

	if ($hassubject){
		$subject = trim($subject);
		if (!$subject)
			stderr($lang_forums['std_error'], $lang_forums['std_must_enter_subject']);
		if (strlen($subject) > $maxsubjectlength)
			stderr($lang_forums['std_error'], $lang_forums['std_subject_limited']);
	}

	//------ Make sure sure user has write access in forum
	$arr = get_forum_row($forumid) or die($lang_forums['std_bad_forum_id']);

	if (
	    get_user_class() < $arr["minclassread"]
        || get_user_class() < $arr["minclasswrite"]
        || ($type =='new' && get_user_class() < $arr["minclasscreate"])
    ) {
        permissiondenied();
    }
	if ($body == "")
		stderr($lang_forums['std_error'], $lang_forums['std_no_body_text']);

	$userid = intval($CURUSER["id"] ?? 0);
	$date = date("Y-m-d H:i:s");

	if ($type != 'new'){
		//---- Make sure topic is unlocked

		$topicLocked = \App\Models\Topic::query()->where('id', $topicid)->value('locked');
		if ($topicLocked === null)
			die("Topic id n/a");
		if ($topicLocked == 'yes' && !user_can('postmanage') && !is_forum_moderator($topicid, 'topic'))
			stderr($lang_forums['std_error'], $lang_forums['std_topic_locked']);
	}

	if ($type == 'edit')
	{
        $postid = $id;
        $topicInfo = \App\Models\Topic::query()->findOrFail($topicid);
        $postInfo = \App\Models\Post::query()->findOrFail($id);
        if ($postInfo->userid != $CURUSER['id'] && !is_forum_moderator($postid, 'post') && !user_can('postmanage')) {
            permissiondenied();
        }
		if ($hassubject){
			\App\Models\Topic::query()->where('id', $topicid)->update(['subject' => $subject]);
			$forum_last_replied_topic_row = $Cache->get_value('forum_'.$forumid.'_last_replied_topic_content');
			if (is_array($forum_last_replied_topic_row) && ($forum_last_replied_topic_row['id'] ?? null) == $topicid)
				$Cache->delete_value('forum_'.$forumid.'_last_replied_topic_content');
		}
		\App\Models\Post::query()->where('id', $id)->update(['body' => $body, 'editdate' => $date, 'editedby' => $CURUSER['id']]);
		$Cache->delete_value('post_'.$postid.'_content');
        //send pm
        $postUrl = sprintf('[url=forums.php?action=viewtopic&topicid=%s&page=p%s#pid%s]%s[/url]', $topicid, $id, $id, $topicInfo->subject);
        if (!empty($postInfo->userid) && $postInfo->userid != $CURUSER['id']) {
            $receiver = $postInfo->user;
            if ($receiver) {
                $locale = $receiver->locale;
                $notify = [
                    'sender' => 0,
                    'receiver' => $receiver->id,
                    'subject' => nexus_trans('forum.post.edited_notify_subject', [], $locale),
                    'msg' => nexus_trans('forum.post.edited_notify_body', ['topic_subject' => $postUrl, 'editor' => $CURUSER['username']], $locale),
                    'added' => now(),
                ];
                \App\Models\Message::add($notify);
            }
        }
	}
	else
	{
		// Anti Flood Code
		// To ensure that posts are not entered within 10 seconds limiting posts
		// to a maximum of 360*6 per hour.
		if (!user_can('postmanage')) {
			if (strtotime($CURUSER['last_post']) > (TIMENOW - 10))
			{
				$secs = 10 - (TIMENOW - strtotime($CURUSER['last_post']));
				stderr($lang_forums['std_error'],$lang_forums['std_post_flooding'].$secs.$lang_forums['std_seconds_before_making'],false);
			}
		}
		if ($type == 'new'){ //new topic
			//add bonus
			KPS("+",$starttopic_bonus,$userid);

			//---- Create topic
			$topic = \App\Models\Topic::create([
				'userid' => $userid,
				'forumid' => $forumid,
				'subject' => $subject,
				'locked' => 'no',
				'sticky' => 'no',
				'hlcolor' => 0,
				'views' => 0,
				'firstpost' => 0,
				'lastpost' => 0,
			]);
			$topicid = $topic ? $topic->id : 0;
			if (!$topicid)
				stderr($lang_forums['std_error'],$lang_forums['std_no_topic_id_returned']);
			\App\Models\Forum::query()->where('id', $forumid)->increment('topiccount');
			\App\Models\Forum::query()->where('id', $forumid)->increment('postcount');
		}
		else // new post
		{
			//add bonus
			KPS("+",$makepost_bonus,$userid);
			\App\Models\Forum::query()->where('id', $forumid)->increment('postcount');
		}

		$postid = \Nexus\Database\NexusDB::table('posts')->insertGetId([
			'topicid' => $topicid,
			'userid' => $userid,
			'added' => $date,
			'body' => $body,
			'ori_body' => $body,
		]);
		if (!$postid)
			die($lang_forums['std_post_id_not_available']);
		//send pm
        $topicInfo = \App\Models\Topic::query()->findOrFail($topicid);
        $postUrl = sprintf('[url=forums.php?action=viewtopic&topicid=%s&page=p%s#pid%s]%s[/url]', $topicid, $postid, $postid, $topicInfo->subject);

		if ($type == 'reply') {
			/** @var \App\Models\User $receiver */
			if (!empty($topicInfo->userid) && $topicInfo->userid != $CURUSER['id'])
			{
				$receiver = $topicInfo->user;
				if ($receiver && $receiver->acceptNotification('topic_reply')) {
					$locale = $receiver->locale;
					$notify = [
						'sender' => 0,
						'receiver' => $receiver->id,
						'subject' => nexus_trans('forum.topic.replied_notify_subject', [], $locale),
						'msg' => nexus_trans('forum.topic.replied_notify_body', ['topic_subject' => $postUrl], $locale),
						'added' => now(),
					];
                    \App\Models\Message::add($notify);
				}
			}

            if (!empty($quotepostid)) {
                $quotePostInfo = \App\Models\Post::query()->find($quotepostid);
                if ($quotePostInfo && $quotePostInfo->userid != $CURUSER['id']) {
                    $receiver = $quotePostInfo->user;
                    if($receiver && $receiver->acceptNotification('topic_reply')) {
                        $locale = $receiver->locale;
                        $notify = [
                            'sender' => 0,
                            'receiver' => $receiver->id,
                            'subject' => nexus_trans('forum.reply.replied_notify_subject', [], $locale),
                            'msg' => nexus_trans('forum.reply.replied_notify_body', ['topic_subject' => $postUrl, 'replyer' => $CURUSER['username']], $locale),
                            'added' => now(),
                        ];
                        \App\Models\Message::add($notify);
                    }
                }
            }
        }

		$Cache->delete_value('forum_'.$forumid.'_post_'.$today_date.'_count');
		$Cache->delete_value('today_'.$today_date.'_posts_count');
		$Cache->delete_value('forum_'.$forumid.'_last_replied_topic_content');
		$Cache->delete_value('topic_'.$topicid.'_post_count');
		$Cache->delete_value('user_'.$userid.'_post_count');

		if ($type == 'new')
		{
			// update the first post of topic
			\App\Models\Topic::query()->where('id', $topicid)->update(['firstpost' => $postid, 'lastpost' => $postid]);
		}
		else
		{
			\App\Models\Topic::query()->where('id', $topicid)->update(['lastpost' => $postid]);
		}
		\App\Models\User::query()->where('id', $CURUSER['id'])->update(['last_post' => $date]);
	}

	//------ All done, redirect user to the post

	$headerstr = "Location: " . get_protocol_prefix() . "$BASEURL/forums.php?action=viewtopic&topicid=$topicid";

	if ($type == 'edit')
		header($headerstr."&page=p".$postid."#pid".$postid);
	else
		header($headerstr."&page=last#pid$postid");
	die;
}

//-------- Action: View topic

if ($action == "viewtopic")
{
	$highlight = htmlspecialchars(trim($_GET["highlight"] ?? ''));

	$topicid = intval($_GET["topicid"] ?? 0);
	int_check($topicid,true);
	$page = $_GET["page"] ?? 0;
	$authorid = intval($_GET["authorid"] ?? 0);
	$postQuery = \App\Models\Post::query()->where('topicid', $topicid);
	if ($authorid)
	{
		$postQuery->where('userid', $authorid);
		$addparam = "action=viewtopic&topicid=".$topicid."&authorid=".$authorid;
	}
	else
	{
		$addparam = "action=viewtopic&topicid=".$topicid;
	}
	$userid = $CURUSER["id"];

	//------ Get topic info

	$topic = \App\Models\Topic::query()->where('id', $topicid)->first();
	if (!$topic)
		stderr($lang_forums['std_forum_error'], $lang_forums['std_topic_not_found']);
	$arr = $topic->toArray();

	$forumid = $arr['forumid'];
	$locked = $arr['locked'] == "yes";
	$orgsubject = $arr['subject'];
	$subject = htmlspecialchars($arr['subject']);
	if ($highlight){
		$subject = highlight($highlight,$orgsubject);
	}
	$sticky = $arr['sticky'] == "yes";
	$hlcolor = $arr['hlcolor'];
	$views = $arr['views'];
	$forumid = $arr["forumid"];
	$base_posterid = $arr['userid'];

	$row = get_forum_row($forumid);
	//------ Get forum name, moderators
	$forumname = $row['name'];
	$is_forummod = is_forum_moderator($forumid,'forum');

	if (get_user_class() < $row["minclassread"])
		stderr($lang_forums['std_error'], $lang_forums['std_unpermitted_viewing_topic']);
	if (((get_user_class() >= $row["minclasswrite"] && !$locked) || user_can('postmanage') || $is_forummod) && $CURUSER["forumpost"] == 'yes')
		$maypost = true;
	else $maypost = false;

	//------ Update hits column
	\App\Models\Topic::query()->where('id', $topicid)->increment('views');

	//------ Get post count
	$postcount = (clone $postQuery)->count();
	if (!$authorid)
		$Cache->cache_value('topic_'.$topicid.'_post_count', $postcount, 3600);

	//------ Make page menu

	$pagerarr = array();

	$perpage = $postsperpage;

	$pages = ceil($postcount / $perpage);

	if (isset($page[0]) && $page[0] == "p")
	{
		$findpost = substr($page, 1);
		$postIds = (clone $postQuery)->orderBy('added')->pluck('id')->all();
		$i = array_search($findpost, $postIds);
		if ($i === false)
			$i = 0;
		$page = floor($i / $perpage);
	}
	if ($page === "last"){
	$page = $pages-1;
	}
	elseif(isset($page))
	{
		if($page < 0){
		$page = 0;
		}
		elseif ($page > $pages - 1){
		$page = $pages - 1;
		}
	}
	else {if ($CURUSER["clicktopic"] == "firstpage")
		$page = 0;
		else $page = $pages-1;
	}

	$offset = $page * $perpage;
	$dotted = 0;
	$dotspace = 3;
	$dotend = $pages - $dotspace;
	$curdotend = $page - $dotspace;
	$curdotstart = $page + $dotspace;
	for ($i = 0; $i < $pages; ++$i)
	{
		if (($i >= $dotspace && $i <= $curdotend) || ($i >= $curdotstart && $i < $dotend)) {
				if (!$dotted)
				$pagerarr[] = "...";
				$dotted = 1;
				continue;
		}
		$dotted = 0;
		if ($i != $page)
		$pagerarr[] = "<a href=\"".htmlspecialchars("?".$addparam."&page=".$i)."\"><b>".($i+1)."</b></a>\n";
		else
		$pagerarr[] = "<font class=\"gray\"><b>".($i+1)."</b></font>\n";
	}
	if ($page == 0)
	$pager = "<font class=\"gray\"><b>&lt;&lt;".$lang_forums['text_prev']."</b></font>";
	else
	$pager = "<a href=\"".htmlspecialchars("?".$addparam."&page=" . ($page - 1)) .
	"\"><b>&lt;&lt;".$lang_forums['text_prev']."</b></a>";
	$pager .= "&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;";
	if ($page == $pages-1)
	$pager .= "<font class=\"gray\"><b>".$lang_forums['text_next']." &gt;&gt;</b></font>\n";
	else
	$pager .= "<a href=\"".htmlspecialchars("?".$addparam."&page=" . ($page + 1)) .
	"\"><b>".$lang_forums['text_next']." &gt;&gt;</b></a>\n";

	$pagerstr = join(" | ", $pagerarr);
	$pagertop = "<p align=\"center\">".$pager."<br />".$pagerstr."</p>\n";
	$pagerbottom = "<p align=\"center\">".$pagerstr."<br />".$pager."</p>\n";
	//------ Get posts

	$postRows = (clone $postQuery)->orderBy('id')->offset($offset)->limit($perpage)->get();
	$pc = $postRows->count();
	$allPosts = [];
	$uidArr = [];
	foreach ($postRows as $postObj) {
		$arr = $postObj->toArray();
		$allPosts[] = $arr;
		$uidArr[$arr['userid']] = 1;
	}
	$uidArr = array_keys($uidArr);
	unset($arr);

	stdhead($lang_forums['head_view_topic']." \"".$orgsubject."\"");
	begin_main_frame("",true);

	print("<h1 align=\"center\"><a class=\"faqlink\" href=\"forums.php\">".$SITENAME."&nbsp;".$lang_forums['text_forums']."</a>--><a class=\"faqlink\" href=\"".htmlspecialchars("?action=viewforum&forumid=".$forumid)."\">".$forumname."</a><b>--></b><span id=\"top\">".$subject.($locked ? "&nbsp;&nbsp;<b>[<font class=\"striking\">".$lang_forums['text_locked']."</font>]</b>" : "")."</span></h1>\n");
	end_main_frame();
	print($pagertop);

	//------ Print table

	begin_main_frame();
	print("<table border=\"0\" class=\"main\" cellspacing=\"0\" cellpadding=\"5\" width=\"97%\"><tr>\n");
	print("<td class=\"embedded\" width=\"99%\">&nbsp;&nbsp;".$lang_forums['there_is']."<b>".$views."</b>".$lang_forums['hits_on_this_topic']);
	print("</td>\n");
	print("<td class=\"embedded nowrap\" width=\"1%\" align=\"right\">");
	if ($maypost)
	{
		print("<a href=\"".htmlspecialchars("?action=reply&topicid=".$topicid)."\"><img class=\"f_reply\" src=\"pic/trans.gif\" alt=\"Add Reply\" title=\"".$lang_forums['title_reply_directly']."\" /></a>&nbsp;&nbsp;");
	}
	print("</td>");
	print("</tr></table>\n");
	begin_frame();

	$neededColumns = array('id', 'class', 'enabled', 'privacy', 'avatar', 'signature', 'uploaded', 'downloaded', 'last_access', 'username', 'donor', 'leechwarn', 'warned', 'title');
    $userInfoArr = \App\Models\User::query()->find($uidArr, $neededColumns)->keyBy('id');
	$pn = 0;
	$lpr = get_last_read_post_id($topicid);

	//check if privacy protection enabled in this forum
//	$protected_forums = Nexus\Database\NexusDB::remember("setting_protected_forum", 600, function () {
//		return \App\Models\Setting::getByName('misc.protected_forum');
//	});
//
//	if ($protected_forums and in_array(strval($forumid),explode(",",$protected_forums))){
//		$protected_enabled=true;
//	}else{
//		$protected_enabled=false;
//	}

	foreach ($allPosts as $arr)
	{
		++$pn;

		$postid = $arr["id"];
		$posterid = $arr["userid"];

		$added = gettime($arr["added"],true,false);

		//---- Get poster details

//		$arr2 = get_user_row($posterid);
		$userInfo = $userInfoArr->get($posterid) ?: \App\Models\User::defaultUser();

		$arr2 = $userInfo->toArray();

		$uploaded = mksize($arr2["uploaded"]);
		$downloaded = mksize($arr2["downloaded"]);
		$ratio = get_ratio($arr2['id']);

		if (!$forumposts = $Cache->get_value('user_'.$posterid.'_post_count')){
			$forumposts = \App\Models\Post::query()->where('userid', $posterid)->count();
			$Cache->cache_value('user_'.$posterid.'_post_count', $forumposts, 3600);
		}

		$signature = ($CURUSER["signatures"] == "yes" ? $arr2["signature"] : "");
		$avatar = ($CURUSER["avatars"] == "yes" ? htmlspecialchars($arr2["avatar"]) : "");

		$uclass = get_user_class_image($arr2["class"]);
		$by = get_username($posterid,false,true,true,false,false,true);

		if (!$avatar)
			$avatar = "pic/default_avatar.png";

		if ($pn == $pc)
		{
			print("<span id=\"last\"></span>\n");
			if ($postid > $lpr){
				$readPost = \Nexus\Database\NexusDB::table('readposts')
					->where('userid', $userid)
					->where('topicid', $topicid)
					->first();
				if (!$readPost) { // There is no record of this topic
					\Nexus\Database\NexusDB::table('readposts')->insert([
						'userid' => $userid,
						'topicid' => $topicid,
						'lastpostread' => $postid,
					]);
				} elseif ($lpr > $CURUSER['last_catchup']) { //There is record of this topic
					\Nexus\Database\NexusDB::table('readposts')
						->where('userid', $userid)
						->where('topicid', $topicid)
						->update(['lastpostread' => $postid]);
				}
				$Cache->delete_value('user_'.$CURUSER['id'].'_last_read_post_list');
			}
		}

		print("<div style=\"margin-top: 8pt; margin-bottom: 8pt;\"><table id=\"pid".$postid."\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\" width=\"100%\"><tr><td class=\"embedded\" width=\"99%\"><a href=\"".htmlspecialchars("forums.php?action=viewtopic&topicid=".$topicid."&page=p".$postid."#pid".$postid)."\">#".$postid."</a>&nbsp;&nbsp;<font color=\"gray\">".$lang_forums['text_by']."</font>".$by."&nbsp;&nbsp;<font color=\"gray\">".$lang_forums['text_at']."</font>".$added);
		if (is_valid_id($arr['editedby']))
			print("");
		print("&nbsp;&nbsp;<font color=\"gray\">|</font>&nbsp;&nbsp;");
		if ($authorid)
			print("<a href=\"?action=viewtopic&topicid=".$topicid."\">".$lang_forums['text_view_all_posts']."</a>");
		else
			print("<a href=\"".htmlspecialchars("?action=viewtopic&topicid=".$topicid."&authorid=".$posterid)."\">".$lang_forums['text_view_this_author_only']."</a>");
		print("</td><td class=\"embedded nowrap\" width=\"1%\"><font class=\"big\">".$lang_forums['text_number']."<b>".($pn+$offset)."</b>".$lang_forums['text_lou']."&nbsp;&nbsp;</font><a href=\"#top\"><img class=\"top\" src=\"pic/trans.gif\" alt=\"Top\" title=\"".$lang_forums['text_back_to_top']."\" /></a>&nbsp;&nbsp;</td></tr>");

		print("</table></div>\n");

		print("<table class=\"main\" width=\"100%\" border=\"1\" cellspacing=\"0\" cellpadding=\"5\">\n");

		$body = "<div id=\"pid".$postid."body\" style=\"word-break: break-all;\">";
		//hidden content applied to second or higher floor post (for whose user class below Ad , not poster , not mods ,not reply's author)
//		if ($protected_enabled && $pn+$offset>1 && get_user_class()<UC_ADMINISTRATOR && $userid != $base_posterid && $posterid!=$userid && !$is_forummod){
		if ($pn+$offset>1 && !can_view_post($userid, $arr)){
			//enable content protection
			$bodyContent = format_comment($lang_forums["text_post_protected"]);
            $canViewProtected = false;
		}else{
			//display normal content
			$bodyContent = format_comment($arr["body"]);
            $canViewProtected = true;
		}
		if ($highlight){
            $bodyContent = highlight($highlight,$bodyContent);
		}

		if (is_valid_id($arr['editedby']))
		{
			$lastedittime = gettime($arr['editdate'],true,false);
            $bodyContent .= "<br /><p><font class=\"small\">".$lang_forums['text_last_edited_by'].get_username($arr['editedby']).$lang_forums['text_last_edit_at'].$lastedittime."</font></p>\n";
		}
		$bodyContent = apply_filter('post_body', $bodyContent, $arr, $allPosts);
		$body .= $bodyContent . "</div>";
		if ($signature)
		$body .= "<p style='vertical-align:bottom'><br />____________________<br />" . format_comment($signature,false,false,false,true,500,true,false, 1,200) . "</p>";

		$stats = "<br />"."&nbsp;&nbsp;".$lang_forums['text_posts']."$forumposts<br />"."&nbsp;&nbsp;".$lang_forums['text_ul']."$uploaded <br />"."&nbsp;&nbsp;".$lang_forums['text_dl']."$downloaded<br />"."&nbsp;&nbsp;".$lang_forums['text_ratio']."$ratio";
		print("<tr><td class=\"rowfollow\" width=\"150\" valign=\"top\" align=\"left\" style='padding: 0px'>" .
		return_avatar_image($avatar). "<br /><br /><br />&nbsp;&nbsp;<img alt=\"".get_user_class_name($arr2["class"],false,false,true)."\" title=\"".get_user_class_name($arr2["class"],false,false,true)."\" src=\"".$uclass."\" />".$stats."</td><td class=\"rowfollow\" valign=\"top\"><br />".$body."</td></tr>\n");
		$secs = 900;
		$dt = date("Y-m-d H:i:s", TIMENOW - $secs); // calculate date.
		$online = $arr2['last_access'] > $dt;
		print("<tr><td class=\"rowfollow\" align=\"center\" valign=\"middle\">".($online?"<img class=\"f_online\" src=\"pic/trans.gif\" alt=\"Online\" title=\"".$lang_forums['title_online']."\" />":"<img class=\"f_offline\" src=\"pic/trans.gif\" alt=\"Offline\" title=\"".$lang_forums['title_offline']."\" />" )."<a href=\"sendmessage.php?receiver=".htmlspecialchars(trim($arr2["id"]))."\"><img class=\"f_pm\" src=\"pic/trans.gif\" alt=\"PM\" title=\"".$lang_forums['title_send_message_to'].htmlspecialchars($arr2["username"])."\" /></a><a href=\"report.php?forumpost=$postid\"><img class=\"f_report\" src=\"pic/trans.gif\" alt=\"Report\" title=\"".$lang_forums['title_report_this_post']."\" /></a></td>");
		print("<td class=\"toolbox\" align=\"right\">");

		do_action('post_toolbox', $arr, $allPosts, $CURUSER['id']);

		if ($maypost && $canViewProtected)
		print("<a href=\"".htmlspecialchars("?action=quotepost&postid=".$postid)."\"><img class=\"f_quote\" src=\"pic/trans.gif\" alt=\"Quote\" title=\"".$lang_forums['title_reply_with_quote']."\" /></a>");

		if (user_can('postmanage') || $is_forummod)
		print("<a href=\"".htmlspecialchars("?action=deletepost&postid=".$postid)."\"><img class=\"f_delete\" src=\"pic/trans.gif\" alt=\"Delete\" title=\"".$lang_forums['title_delete_post']."\" /></a>");

		if (($CURUSER["id"] == $posterid && !$locked) || user_can('postmanage') || $is_forummod)
		print("<a href=\"".htmlspecialchars("?action=editpost&postid=".$postid)."\"><img class=\"f_edit\" src=\"pic/trans.gif\" alt=\"Edit\" title=\"".$lang_forums['title_edit_post']."\" /></a>");
		print("</td></tr></table>");
	}

	//------ Mod options

	if (user_can('postmanage') || $is_forummod)
	{
		print("</td></tr><tr><td class=\"toolbox\" align=\"center\">\n");
		print("<table border=\"0\" cellspacing=\"0\" cellpadding=\"0\" align=\"left\">\n");
		print("<tr><td class=\"embedded\"><form method=\"post\" action=\"?action=setsticky\">\n");
		print("<input type=\"hidden\" name=\"topicid\" value=\"".$topicid."\" />\n");
		print("<input type=\"hidden\" name=\"returnto\" value=\"".htmlspecialchars($_SERVER['REQUEST_URI'])."\" />\n");
		print("<input type=\"hidden\" name=\"sticky\" value=\"".($sticky ? 'no' : 'yes')."\" /><input type=\"submit\" class=\"medium\" value=\"".($sticky ? $lang_forums['submit_unsticky'] : $lang_forums['submit_sticky'])."\" /></form></td>\n");
		print("<td class=\"embedded\"><form method=\"post\" action=\"?action=setlocked\">\n");
		print("<input type=\"hidden\" name=\"topicid\" value=\"".$topicid."\" />\n");
		print("<input type=\"hidden\" name=\"returnto\" value=\"".htmlspecialchars($_SERVER['REQUEST_URI'])."\" />\n");
		print("<input type=\"hidden\" name=\"locked\" value=\"".($locked ? 'no' : 'yes')."\" /><input type=\"submit\" class=\"medium\" value=\"".($locked ? $lang_forums['submit_unlock'] : $lang_forums['submit_lock'])."\" /></form></td>\n");
		print("<td class=\"embedded\"><form method=\"get\" action=\"?\">\n");
		print("<input type=\"hidden\" name=\"action\" value=\"deletetopic\" />\n");
		print("<input type=\"hidden\" name=\"topicid\" value=\"".$topicid."\" />\n");
		print("<input type=\"hidden\" name=\"forumid\" value=\"".$forumid."\" />\n");
		print("<input type=\"submit\" class=\"medium\" value=\"".$lang_forums['submit_delete_topic']."\" /></form></td>\n");
		print("<td class=\"embedded\"><form method=\"post\" action=\"".htmlspecialchars("?action=movetopic&topicid=".$topicid)."\">\n"."&nbsp;".$lang_forums['text_move_thread_to']."&nbsp;<select class=\"med\" name=\"forumid\">");
		$forums = get_forum_row();
		foreach ($forums as $arr){
			if ($arr["id"] != $forumid && get_user_class() >= $arr["minclasswrite"])
				print("<option value=\"" . $arr["id"] . "\">" . htmlspecialchars($arr["name"]) . "</option>\n");
		}
		print("</select> <input type=\"submit\" class=\"medium\" value=\"".$lang_forums['submit_move']."\" /></form></td>");
		print("<td class=\"embedded\"><form method=\"post\" action=\"".htmlspecialchars("?action=hltopic&topicid=".$topicid)."\">\n"."&nbsp;".$lang_forums['text_highlight_topic']."&nbsp;<select class=\"med\" name=\"color\">");
		print("<option value='0'>".$lang_forums['select_color']."</option>
<option style='background-color: black' value=\"1\">Black</option>
<option style='background-color: sienna' value=\"2\">Sienna</option>
<option style='background-color: darkolivegreen' value=\"3\">Dark Olive Green</option>
<option style='background-color: darkgreen' value=\"4\">Dark Green</option>
<option style='background-color: darkslateblue' value=\"5\">Dark Slate Blue</option>
<option style='background-color: navy' value=\"6\">Navy</option>
<option style='background-color: indigo' value=\"7\">Indigo</option>
<option style='background-color: darkslategray' value=\"8\">Dark Slate Gray</option>
<option style='background-color: darkred' value=\"9\">Dark Red</option>
<option style='background-color: darkorange' value=\"10\">Dark Orange</option>
<option style='background-color: olive' value=\"11\">Olive</option>
<option style='background-color: green' value=\"12\">Green</option>
<option style='background-color: teal' value=\"13\">Teal</option>
<option style='background-color: blue' value=\"14\">Blue</option>
<option style='background-color: slategray' value=\"15\">Slate Gray</option>
<option style='background-color: dimgray' value=\"16\">Dim Gray</option>
<option style='background-color: red' value=\"17\">Red</option>
<option style='background-color: sandybrown' value=\"18\">Sandy Brown</option>
<option style='background-color: yellowgreen' value=\"19\">Yellow Green</option>
<option style='background-color: seagreen' value=\"20\">Sea Green</option>
<option style='background-color: mediumturquoise' value=\"21\">Medium Turquoise</option>
<option style='background-color: royalblue' value=\"22\">Royal Blue</option>
<option style='background-color: purple' value=\"23\">Purple</option>
<option style='background-color: gray' value=\"24\">Gray</option>
<option style='background-color: magenta' value=\"25\">Magenta</option>
<option style='background-color: orange' value=\"26\">Orange</option>
<option style='background-color: yellow' value=\"27\">Yellow</option>
<option style='background-color: lime' value=\"28\">Lime</option>
<option style='background-color: cyan' value=\"29\">Cyan</option>
<option style='background-color: deepskyblue' value=\"30\">Deep Sky Blue</option>
<option style='background-color: darkorchid' value=\"31\">Dark Orchid</option>
<option style='background-color: silver' value=\"32\">Silver</option>
<option style='background-color: pink' value=\"33\">Pink</option>
<option style='background-color: wheat' value=\"34\">Wheat</option>
<option style='background-color: lemonchiffon' value=\"35\">Lemon Chiffon</option>
<option style='background-color: palegreen' value=\"36\">Pale Green</option>
<option style='background-color: paleturquoise' value=\"37\">Pale Turquoise</option>
<option style='background-color: lightblue' value=\"38\">Light Blue</option>
<option style='background-color: plum' value=\"39\">Plum</option>
<option style='background-color: white' value=\"40\">White</option>");
		print("</select>");
		print("<input type=\"hidden\" name=\"returnto\" value=\"".htmlspecialchars($_SERVER['REQUEST_URI'])."\" />\n");
		print("<input type=\"submit\" class=\"medium\" value=\"".$lang_forums['submit_change']."\" /></form></td>");
		print("</tr>\n");
		print("</table>\n");
	}

	end_frame();

	end_main_frame();

	print($pagerbottom);
	if ($maypost){
	print("<br /><table style='border:1px solid #000000;'><tr>".
"<td class=\"text\" align=\"center\"><b>".$lang_forums['text_quick_reply']."</b><br /><br />".
"<form id=\"compose\" name=\"compose\" method=\"post\" action=\"?action=post\" onsubmit=\"return postvalid(this);\">".
"<input type=\"hidden\" name=\"id\" value=\"".$topicid."\" /><input type=\"hidden\" name=\"type\" value=\"reply\" /><br />");
	quickreply('compose', 'body',$lang_forums['submit_add_reply']);
	print("</form></td></tr></table>");
	print("<p align=\"center\"><a class=\"index\" href=\"".htmlspecialchars("?action=reply&topicid=".$topicid)."\">".$lang_forums['text_add_reply']."</a></p>\n");
	}
	elseif ($locked)
		print($lang_forums['text_topic_locked_new_denied']);
	else print($lang_forums['text_unpermitted_posting_here']);

	print(key_shortcut($page,$pages-1));
	stdfoot();
	die;
}

//-------- Action: Move topic

if ($action == "movetopic")
{
	$forumid = intval($_POST["forumid"] ?? 0);

	$topicid = intval($_GET["topicid"] ?? 0);
	$ismod = is_forum_moderator($topicid,'topic');
	if (!is_valid_id($forumid) || !is_valid_id($topicid) || (!user_can('postmanage') && !$ismod))
		permissiondenied();

	// Make sure topic and forum is valid

	$forum = \App\Models\Forum::query()->where('id', $forumid)->first(['minclasswrite']);

	if (!$forum)
	stderr($lang_forums['std_error'], $lang_forums['std_forum_not_found']);

	if (get_user_class() < $forum->minclasswrite)
		permissiondenied();

	$topic = \App\Models\Topic::query()->where('id', $topicid)->first(['forumid']);
	if (!$topic)
		stderr($lang_forums['std_error'], $lang_forums['std_topic_not_found']);
	$old_forumid = $topic->forumid;

	// get posts count
	$nb_posts = \App\Models\Post::query()->where('topicid', $topicid)->count();

	// move topic
	if ($old_forumid != $forumid)
	{
		\App\Models\Topic::query()->where('id', $topicid)->update(['forumid' => $forumid]);
		// update counts
		\App\Models\Forum::query()->where('id', $old_forumid)->decrement('topiccount');
		\App\Models\Forum::query()->where('id', $old_forumid)->decrement('postcount', $nb_posts);
		$Cache->delete_value('forum_'.$old_forumid.'_post_'.$today_date.'_count');
		$Cache->delete_value('forum_'.$old_forumid.'_last_replied_topic_content');
		\App\Models\Forum::query()->where('id', $forumid)->increment('topiccount');
		\App\Models\Forum::query()->where('id', $forumid)->increment('postcount', $nb_posts);
		$Cache->delete_value('forum_'.$forumid.'_post_'.$today_date.'_count');
		$Cache->delete_value('forum_'.$forumid.'_last_replied_topic_content');
	}

	// Redirect to forum page

	header("Location: " . get_protocol_prefix() . "$BASEURL/forums.php?action=viewforum&forumid=$forumid");

	die;
}

//-------- Action: Delete topic

if ($action == "deletetopic")
{
	$topicid = intval($_GET["topicid"] ?? 0);
	$topic = \App\Models\Topic::query()->where('id', $topicid)->first(['forumid', 'userid']);
	if (!$topic){
		die;
	}
	else {
		$forumid = $topic->forumid;
		$userid = $topic->userid;
	}
	$ismod = is_forum_moderator($topicid,'topic');
	if (!is_valid_id($topicid) || (!user_can('postmanage') && !$ismod))
		permissiondenied();

	$sure = intval($_GET["sure"] ?? 0);
	if (!$sure)
	{
		stderr($lang_forums['std_delete_topic'], $lang_forums['std_delete_topic_note'] .
		"<a class=altlink href=?action=deletetopic&topicid=$topicid&sure=1>".$lang_forums['std_here_if_sure'],false);
	}

	$postcount = \App\Models\Post::query()->where('topicid', $topicid)->count();

	\App\Models\Topic::query()->where('id', $topicid)->delete();
	\App\Models\Post::query()->where('topicid', $topicid)->delete();
	\Nexus\Database\NexusDB::table('readposts')->where('topicid', $topicid)->delete();
	\App\Models\Forum::query()->where('id', $forumid)->decrement('topiccount');
	\App\Models\Forum::query()->where('id', $forumid)->decrement('postcount', $postcount);
	$Cache->delete_value('forum_'.$forumid.'_post_'.$today_date.'_count');
	$forum_last_replied_topic_row = $Cache->get_value('forum_'.$forumid.'_last_replied_topic_content');
	if ($forum_last_replied_topic_row && $forum_last_replied_topic_row['id'] == $topicid)
		$Cache->delete_value('forum_'.$forumid.'_last_replied_topic_content');

	//===remove karma
	KPS("-",$starttopic_bonus,$userid);
	//===end

	header("Location: " . get_protocol_prefix() . "$BASEURL/forums.php?action=viewforum&forumid=$forumid");
	die;
}

//-------- Action: Delete post

if ($action == "deletepost")
{
	$postid = intval($_GET["postid"] ?? 0);
	$sure = intval($_GET["sure"] ?? 0);

	$ismod = is_forum_moderator($postid, 'post');
	if ((!user_can('postmanage') && !$ismod) || !is_valid_id($postid))
		permissiondenied();

	//------- Get topic id
	$post = \App\Models\Post::query()->where('id', $postid)->first(['topicid', 'userid']);
	if (!$post)
		stderr($lang_forums['std_error'], $lang_forums['std_post_not_found']);
	$topicid = $post->topicid;
	$userid = $post->userid;

	//------- Get the id of the last post before the one we're deleting
	$prevPostId = \App\Models\Post::query()->where('topicid', $topicid)->where('id', '<', $postid)->orderByDesc('id')->value('id');
	if (!$prevPostId) // This is the first post of a topic
		stderr($lang_forums['std_error'], $lang_forums['std_cannot_delete_post'] .
	"<a class=altlink href=?action=deletetopic&topicid=$topicid&sure=1>".$lang_forums['std_delete_topic_instead'],false);
	else
	{
		$redirtopost = "&page=p$prevPostId#pid$prevPostId";
	}

	//------- Make sure we know what we do :-)
	if (!$sure)
	{
		stderr($lang_forums['std_delete_post'], $lang_forums['std_delete_post_note'] .
		"<a class=altlink href=?action=deletepost&postid=$postid&sure=1>".$lang_forums['std_here_if_sure'],false);
	}

	//------- Delete post
	\App\Models\Post::query()->where('id', $postid)->delete();
	$Cache->delete_value('user_'.$userid.'_post_count');
	$Cache->delete_value('topic_'.$topicid.'_post_count');
	// update forum
	$forumid = \App\Models\Topic::query()->where('id', $topicid)->value('forumid');
	if (!$forumid)
		die();
	else{
		\App\Models\Forum::query()->where('id', $forumid)->decrement('postcount');
	}
	$forum_last_replied_topic_row = $Cache->get_value('forum_'.$forumid.'_last_replied_topic_content');
	if ($forum_last_replied_topic_row && $forum_last_replied_topic_row['lastpost'] == $postid)
		$Cache->delete_value('forum_'.$forumid.'_last_replied_topic_content');
	//------- Update topic
	update_topic_last_post($topicid);

	//===remove karma
	KPS("-",$makepost_bonus,$userid);

	header("Location: " . get_protocol_prefix() . "$BASEURL/forums.php?action=viewtopic&topicid=$topicid$redirtopost");
	die;
}

//-------- Action: Set locked on/off

if ($action == "setlocked")
{
	$topicid = intval($_POST["topicid"] ?? 0);
	$ismod = is_forum_moderator($topicid,'topic');
	if (!$topicid || (!user_can('postmanage') && !$ismod))
		permissiondenied();

	$locked = $_POST["locked"];
	\App\Models\Topic::query()->where('id', $topicid)->update(['locked' => $locked]);

	header("Location: $_POST[returnto]");
	die;
}

if ($action == 'hltopic')
{
	$topicid = intval($_GET["topicid"] ?? 0);
	$ismod = is_forum_moderator($topicid,'topic');
	if (!$topicid || (!user_can('postmanage') && !$ismod))
		permissiondenied();
	$color = intval($_POST["color"]);
	if ($color==0 || get_hl_color($color))
		\App\Models\Topic::query()->where('id', $topicid)->update(['hlcolor' => $color]);

	$forumid = \App\Models\Topic::query()->where('id', $topicid)->value('forumid');
	$forum_last_replied_topic_row = $Cache->get_value('forum_'.$forumid.'_last_replied_topic_content');
	if ($forum_last_replied_topic_row && $forum_last_replied_topic_row['id'] == $topicid)
		$Cache->delete_value('forum_'.$forumid.'_last_replied_topic_content');
	header("Location: $_POST[returnto]");
	die;
}

//-------- Action: Set sticky on/off

if ($action == "setsticky")
{
	$topicid = intval($_POST["topicid"] ?? 0);
	$ismod = is_forum_moderator($topicid,'topic');
	if (!$topicid || (!user_can('postmanage') && !$ismod))
		permissiondenied();

	$sticky = $_POST["sticky"];
	\App\Models\Topic::query()->where('id', $topicid)->update(['sticky' => $sticky]);

	header("Location: $_POST[returnto]");
	die;
}

//-------- Action: View forum

if ($action == "viewforum")
{
	$forumid = intval($_GET["forumid"] ?? 0);
	int_check($forumid,true);
	$userid = intval($CURUSER["id"] ?? 0);
	//------ Get forum name, moderators
	$row = get_forum_row($forumid);
	if (!$row){
		write_log("User " . $CURUSER["username"] . "," . $CURUSER["ip"] . " is trying to visit forum that doesn't exist", 'mod');
		stderr($lang_forums['std_forum_error'],$lang_forums['std_forum_not_found']);
	}
	if (get_user_class() < $row["minclassread"])
		permissiondenied();

	$forumname = $row['name'];
	$forummoderators = get_forum_moderators($forumid,false);
	$search = trim($_GET["search"] ?? '');
	$topicQuery = \App\Models\Topic::query()->where('forumid', $forumid);
	if ($search){
		$topicQuery->where('subject', 'like', '%'.$search.'%');
		$addparam .= "&search=".rawurlencode($search);
	}
	else{
		$addparam = "";
	}
	$num = $topicQuery->count();

	[$pagertop, $pagerbottom, , $offset, $perpage, ] = pager($topicsperpage, $num, "?"."action=viewforum&forumid=".$forumid.$addparam."&");
	if (isset($_GET["sort"])){
		switch ($_GET["sort"]){
			case 'firstpostasc':
			{
				$orderby = "firstpost ASC";
				break;
			}
			case 'firstpostdesc':
			{
				$orderby = "firstpost DESC";
				break;
			}
			case 'lastpostasc':
			{
				$orderby = "lastpost ASC";
				break;
			}
			case 'lastpostdesc':
			{
				$orderby = "lastpost DESC";
				break;
			}
			default:
			{
				$orderby = "lastpost DESC";
			}
		}
	}
	else
	{
		$orderby = "lastpost DESC";
	}
	//------ Get topics data
	$orderParts = explode(' ', $orderby);
	$topicRows = (clone $topicQuery)->orderBy('sticky', 'desc')->orderBy($orderParts[0], $orderParts[1] ?? 'desc')->offset($offset)->limit($perpage)->get();
	$numtopics = $topicRows->count();
	stdhead($lang_forums['head_forum']." ".$forumname);
	begin_main_frame("",true);
	print("<h1 align=\"center\"><a class=\"faqlink\" href=\"forums.php\">".$SITENAME."&nbsp;".$lang_forums['text_forums'] ."</a>--><a class=\"faqlink\" href=\"".htmlspecialchars("forums.php?action=viewforum&forumid=".$forumid)."\">".$forumname."</a></h1>\n");
	end_main_frame();
	print("<br />");
	$maypost = get_user_class() >= $row["minclasswrite"] && get_user_class() >= $row["minclasscreate"] && $CURUSER["forumpost"] == 'yes';

	if (!$maypost)
		print("<p><i>".$lang_forums['text_unpermitted_starting_new_topics']."</i></p>\n");

	print("<table border=\"0\" class=\"main\" cellspacing=\"0\" cellpadding=\"5\" width=\"97%\"><tr>\n");
	print("<td class=\"embedded\" width=\"90%\">");
	print($forummoderators ? "&nbsp;&nbsp;<img class=\"forum_mod\" src=\"pic/trans.gif\" alt=\"Moderator\" title=\"".$lang_forums['col_moderator']."\">&nbsp;".$forummoderators : "");
	print("</td><td class=\"embedded nowrap\" width=\"1%\">");
	if ($maypost)
		print("<a href=\"".htmlspecialchars("?action=newtopic&forumid=".$forumid)."\"><img class=\"f_new\" src=\"pic/trans.gif\" alt=\"New Topic\" title=\"".$lang_forums['title_new_topic']."\" /></a>&nbsp;&nbsp;");
	print("</td>");
	print("</tr></table>\n");
	if ($numtopics > 0)
	{
		print("<table border=\"1\" cellspacing=\"0\" cellpadding=\"5\" width=\"97%\">");

		print("<tr><td class=\"colhead\" align=\"center\" width=\"99%\">".$lang_forums['col_topic']."</td><td class=\"colhead\" align=\"center\"><a href=\"".htmlspecialchars("?action=viewforum&forumid=".$forumid.$addparam."&sort=".(isset($_GET["sort"]) && $_GET["sort"] == 'firstpostdesc' ? "firstpostasc" : "firstpostdesc"))."\" title=\"".(isset($_GET["sort"]) && $_GET["sort"] == 'firstpostdesc' ?  $lang_forums['title_order_topic_asc'] : $lang_forums['title_order_topic_desc'])."\">".$lang_forums['col_author']."</a></td><td class=\"colhead\" align=\"center\">".$lang_forums['col_replies']."/".$lang_forums['col_views']."</td><td class=\"colhead\" align=\"center\"><a href=\"".htmlspecialchars("?action=viewforum&forumid=".$forumid.$addparam."&sort=".(isset($_GET["sort"]) && $_GET["sort"] == 'lastpostasc' ? "lastpostdesc" : "lastpostasc"))."\" title=\"".(isset($_GET["sort"]) && $_GET["sort"] == 'lastpostasc' ? $lang_forums['title_order_post_desc'] : $lang_forums['title_order_post_asc'])."\">".$lang_forums['col_last_post']."</a></td>\n");

		print("</tr>\n");
		$counter = 0;

		foreach ($topicRows as $topic)
		{
			$topicarr = $topic->toArray();
			$topicid = $topicarr["id"];

			$topic_userid = $topicarr["userid"];

			$topic_views = $topicarr["views"];

			$views = number_format($topic_views);

			$locked = $topicarr["locked"] == "yes";

			$sticky = $topicarr["sticky"] == "yes";

			$hlcolor = $topicarr["hlcolor"];

			//---- Get reply count
			if (!$posts = $Cache->get_value('topic_'.$topicid.'_post_count')){
				$posts = \App\Models\Post::query()->where('topicid', $topicid)->count();
				$Cache->cache_value('topic_'.$topicid.'_post_count', $posts, 3600);
			}

			$replies = max(0, $posts - 1);

			$tpages = floor($posts / $postsperpage);

			if ($tpages * $postsperpage != $posts)
			++$tpages;

			if ($tpages > 1)
			{
				$topicpages = " [<img class=\"multipage\" src=\"pic/trans.gif\" alt=\"multi-page\" /> ";
				$dotted = 0;
				$dotspace = 4;
				$dotend = $tpages - $dotspace;
				for ($i = 1; $i <= $tpages; ++$i){
					if ($i > $dotspace && $i <= $dotend) {
						if (!$dotted)
						$topicpages .= " ... ";
						$dotted = 1;
						continue;
					}
				$topicpages .= " <a href=\"".htmlspecialchars("?action=viewtopic&topicid=".$topicid."&page=".($i-1))."\">$i</a>";
				}

				$topicpages .= " ]";
			}
			else
			$topicpages = "";

			//---- Get userID and date of last post

			$arr = get_post_row($topicarr['lastpost']);
			$lppostid = intval($arr["id"] ?? 0);
			$lpuserid = intval($arr["userid"] ?? 0);
			$lpusername = get_username($lpuserid);
			$lpadded = gettime($arr["added"],true,false);
			$onmouseover = "";
			if ($enabletooltip_tweak == 'yes' && $CURUSER['showlastpost'] != 'no'){
				if ($CURUSER['timetype'] != 'timealive')
					$lastposttime = $lang_forums['text_at_time'].$arr["added"];
				else
					$lastposttime = $lang_forums['text_blank'].gettime($arr["added"],true,false,true);
				$lptext = format_comment(mb_substr($arr['body'],0,100,"UTF-8") . (mb_strlen($arr['body'],"UTF-8") > 100 ? " ......" : "" ),true,false,false,true,600,false,false);
				$lastpost_tooltip[$counter]['id'] = "lastpost_" . $counter;
				$lastpost_tooltip[$counter]['content'] = $lang_forums['text_last_posted_by'].$lpusername.$lastposttime."<br />".$lptext;
				$onmouseover = "onmouseover=\"domTT_activate(this, event, 'content', document.getElementById('" . $lastpost_tooltip[$counter]['id'] . "'), 'trail', false,'lifetime', 5000,'styleClass','niceTitle','fadeMax', 87,'maxWidth', 400);\"";
			}

			$arr = get_post_row($topicarr['firstpost']);
			$fpuserid = intval($arr["userid"] ?? 0);
			$fpauthor = get_username($arr["userid"]);

			$subject = ($sticky ? "<img class=\"sticky\" src=\"pic/trans.gif\" alt=\"Sticky\" title=\"".$lang_forums['title_sticky']."\" />&nbsp;&nbsp;" : "") . "<a href=\"".htmlspecialchars("?action=viewtopic&forumid=".$forumid."&topicid=".$topicid)."\" ".$onmouseover.">" .highlight_topic(highlight($search,htmlspecialchars($topicarr["subject"])), $hlcolor) . "</a>".$topicpages;
			$lastpostread = get_last_read_post_id($topicid);

			if ($lastpostread >= $lppostid)
				$img = get_topic_image($locked ? "locked" : "read");
			else{
				$img = get_topic_image($locked ? "lockednew" : "unread");
				if ($lastpostread != $CURUSER['last_catchup'])
					$subject .= "&nbsp;&nbsp;<a href=\"".htmlspecialchars("?action=viewtopic&forumid=".$forumid."&topicid=".$topicid."&page=p".$lastpostread."#pid".$lastpostread)."\" title=\"".$lang_forums['title_jump_to_unread']."\"><font class=\"small new\"><b>".$lang_forums['text_new']."</b></font></a>";
			}


			$topictime = substr($arr['added'],0,10);
			if (strtotime($arr['added']) +  86400 > TIMENOW)
				$topictime = "<font class=\"new small\">".$topictime."</font>";
			else
				$topictime = "<font color=\"gray\" class=\"small\">".$topictime."</font>";

			print("<tr><td class=\"rowfollow\" align=\"left\"><table border=\"0\" cellspacing=\"0\" cellpadding=\"0\"><tr>" .
			"<td class=\"embedded\" style='padding-right: 10px'>".$img .
			"</td><td class=\"embedded\" align=\"left\">\n" .
			$subject."</td></tr></table></td><td class=\"rowfollow\" align=\"center\">".get_username($fpuserid)."<br />".$topictime."</td><td class=\"rowfollow\" align=\"center\">".$replies." / <font color=\"gray\">".$views."</font></td>\n" .
			"<td class=\"rowfollow nowrap\" align=\"center\">".$lpadded."<br />".$lpusername."</td>\n");

			print("</tr>\n");
			$counter++;

		} // while

		//print("</table>\n");
		//print("<table border=\"0\" cellspacing=\"0\" cellpadding=\"5\" width=\"97%\">");
		print("<tr><td align=\"left\">\n");
		print("<form method=\"get\" action=\"forums.php\"><b>".$lang_forums['text_fast_search']."</b><input type=\"hidden\" name=\"action\" value=\"viewforum\" /><input type=\"hidden\" name=\"forumid\" value=\"".$forumid."\" /><input type=\"text\" style=\"width: 180px\" name=\"search\" />&nbsp;<input type=\"submit\" value=\"".$lang_forums['text_go']."\" /></form>");
		print("</td>");
?>
<td align="left" colspan="3">
<span id="order" onclick="dropmenu(this);"><span style="cursor: pointer;"><b><?php echo $lang_forums['text_order']?></b></span>
<span id="orderlist" class="dropmenu" style="display: none"><ul>
<li><a href="?action=viewforum&amp;forumid=<?php echo $forumid.$addparam?>&amp;sort=firstpostdesc"><?php echo $lang_forums['text_topic_desc']?></a></li>
<li><a href="?action=viewforum&amp;forumid=<?php echo $forumid.$addparam?>&amp;sort=firstpostasc"><?php echo $lang_forums['text_topic_asc']?></a></li>
<li><a href="?action=viewforum&amp;forumid=<?php echo $forumid.$addparam?>&amp;sort=lastpostdesc"><?php echo $lang_forums['text_post_desc']?></a></li>
<li><a href="?action=viewforum&amp;forumid=<?php echo $forumid.$addparam?>&amp;sort=lastpostasc"><?php echo $lang_forums['text_post_asc']?></a></li>
</ul>
</span>
</span>
</td>
<?php
		print("</tr></table>");
		print($pagerbottom);
		if ($enabletooltip_tweak == 'yes' && $CURUSER['showlastpost'] != 'no')
			create_tooltip_container($lastpost_tooltip, 400);
	} // if
	else
		print("<p>".$lang_forums['text_no_topics_found']."</p>");
	stdfoot();
	die;
}

//-------- Action: View unread posts

if ($action == "viewunread")
{
	$userid = $CURUSER['id'];

	$beforepostid = intval($_GET['beforepostid'] ?? 0);
	$maxresults = 25;
	$unreadQuery = \App\Models\Topic::query()
		->where('lastpost', '>', $CURUSER['last_catchup'] ?? '1970-01-01 00:00:00');
	if ($beforepostid) {
		$unreadQuery->where('lastpost', '<', $beforepostid);
	}
	$unreadTopics = $unreadQuery->orderByDesc('lastpost')->limit(100)->get();

	stdhead($lang_forums['head_view_unread']);
	print("<h1 align=\"center\"><a class=\"faqlink\" href=\"forums.php\">".$SITENAME."&nbsp;".$lang_forums['text_forums']."</a>-->".$lang_forums['text_topics_with_unread_posts']."</h1>");

	$n = 0;
	$uc = get_user_class();

	foreach ($unreadTopics as $topic)
	{
		$arr = $topic->toArray();
		$topiclastpost = $arr['lastpost'];
		$topicid = $arr['id'];

		//---- Check if post is read
		$lastpostread = get_last_read_post_id($topicid);

		if ($lastpostread >= $topiclastpost)
			continue;

		$forumid = $arr['forumid'];
		//---- Check access & get forum name
		$a = get_forum_row($forumid);
		if ($uc < $a['minclassread'])
			continue;
		++$n;
		if ($n > $maxresults)
			break;

		$forumname = $a['name'];
		if ($n == 1)
		{
			print("<table border=\"1\" cellspacing=\"0\" cellpadding=\"5\">\n");
			print("<tr><td class=\"colhead\" align=\"left\">".$lang_forums['col_topic']."</td><td class=\"colhead\" align=\"left\">".$lang_forums['col_forum']."</td></tr>\n");
		}
		print("<tr><td class=\"rowfollow\" align=\"left\"><table border=\"0\" cellspacing=\"0\" cellpadding=\"0\"><tr><td class=\"embedded\" style='padding-right: 10px'>" .
		get_topic_image("unread")."</td><td class=\"embedded\">" .
		"<a href=\"".htmlspecialchars("?action=viewtopic&topicid=".$topicid.($lastpostread > 0 && $lastpostread != $CURUSER['last_catchup'] ? "&page=p".$lastpostread."#pid".$lastpostread : ""))."\">" . highlight_topic(htmlspecialchars($arr["subject"]), $arr["hlcolor"]).
		"</a></td></tr></table></td><td class=\"rowfollow\" align=\"left\"><a href=\"".htmlspecialchars("?action=viewforum&forumid=".$forumid)."\"><b>".$forumname."</b></a></td></tr>\n");
	}
	if ($n > 0)
	{
		print("</table>\n");
		print("<table border=\"0\" class=\"main\" cellspacing=\"0\" cellpadding=\"5\" width=\"1%\"><tr><td class=\"embedded\"><form method=\"get\" action=\"?\"><input type=\"hidden\" name=\"catchup\" value=\"1\" /><input type=\"submit\" value=\"".$lang_forums['text_catch_up']."\" class=\"btn\" /></form></td>");
		if ($n > $maxresults){
			print("<td class=\"embedded\"><form method=\"get\" action=\"?\"><input type=\"hidden\" name=\"action\" value=\"viewunread\" /><input type=\"hidden\" name=\"beforepostid\" value=\"".$topiclastpost."\" /><input type=\"submit\" value=\"".$lang_forums['submit_show_more']."\" class=\"btn\" /></form></td>");
		}
		print("</tr></table>");
	}
	else
		print("<p>".$lang_forums['text_nothing_found']."</p>");
	stdfoot();
	die;
}

if ($action == "search")
{
	stdhead($lang_forums['head_forum_search']);
	unset($error);
	$error = true;
	$found = "";
	$keywords = htmlspecialchars(trim($_GET["keywords"]));
	if ($keywords != "")
	{
		$term = '%'.$keywords.'%';
		$searchQuery = \Nexus\Database\NexusDB::table('posts')
			->leftJoin('topics', 'posts.topicid', '=', 'topics.id')
			->leftJoin('forums', 'topics.forumid', '=', 'forums.id')
			->where('forums.minclassread', '<=', get_user_class())
			->where(function ($q) use ($term) {
				$q->where(function ($sub) use ($term) {
					$sub->where('topics.subject', 'like', $term)->whereColumn('posts.id', 'topics.firstpost');
				})->orWhere('posts.body', 'like', $term);
			});
		$hits = $searchQuery->count('posts.id');
		if ($hits){
			$error = false;
			$found = "[<b><font class=\"striking\"> ".$lang_forums['text_found'].$hits.$lang_forums['text_num_posts']." </font></b>]";
		}
	}
?>
<style type="text/css">
.search{
	background-image:url(pic/search.gif);
	background-repeat:no-repeat;
	width:579px;
	height:95px;
	margin:5px 0 5px 0;
	text-align:left;
}
.search_title{
	color:#0062AE;
	background-color:#DAF3FB;
	font-size:12px;
	font-weight:bold;
	text-align:left;
	padding:7px 0 0 15px;
}

.search_table {
	border-collapse: collapse;
	border: none;
	background-color: #ffffff;
}

</style>
<div class="search">
	<div class="search_title"><?php echo $lang_forums['text_search_on_forum'] ?> <?php echo ($error && $keywords != "" ? "[<b><font color=striking> ".$lang_forums['text_nothing_found']."</font></b> ]" : $found)?></div>
	<div style="margin-left: 53px; margin-top: 13px;">
		<form method="get" action="forums.php" id="search_form" style="margin: 0pt; padding: 0pt; font-family: Tahoma,Arial,Helvetica,sans-serif; font-size: 11px;">
		<input type="hidden" name="action" value="search" />
		<table border="0" cellpadding="0" cellspacing="0" width="512" class="search_table">
		<tbody>
		<tr>
		<td style="padding-bottom: 3px; border: 0;" valign="top"><?php echo $lang_forums['text_by_keyword'] ?></td>
		</tr>
		<tr>
		<td style="padding-bottom: 3px; border: 0;" valign="top">
			<input name="keywords" type="text" value="<?php echo $keywords?>" style="width: 400px;" /></td>
			<td style="padding-bottom: 3px; border: 0;" valign="top"><input name="image" type="image" style="vertical-align: middle; padding-bottom: 0px; margin-left: 0px;" src="<?php echo get_forum_pic_folder()?>/search_button.gif" alt="Search" /></td>
		</tr>
		</tbody>
		</table>
		</form>
	</div>
</div>
<?php

	if (!$error)
	{
		$perpage = $topicsperpage;
		[$pagertop, $pagerbottom, , $offset, $perpage, ] = pager($perpage, $hits, "forums.php?action=search&keywords=".rawurlencode($keywords)."&");
		$posts = (clone $searchQuery)
			->select('posts.id', 'posts.topicid', 'posts.userid', 'posts.added', 'topics.subject', 'topics.hlcolor', 'forums.id AS forumid', 'forums.name AS forumname')
			->orderByDesc('posts.id')
			->offset($offset)
			->limit($perpage)
			->get();

		print($pagertop);
		print("<table border=\"1\" cellspacing=\"0\" cellpadding=\"5\" width=\"97%\">\n");
		print("<tr><td class=\"colhead\" align=\"center\">".$lang_forums['col_post']."</td><td class=\"colhead\" align=\"center\" width=\"70%\">".$lang_forums['col_topic']."</td><td class=\"colhead\" align=\"left\">".$lang_forums['col_forum']."</td><td class=\"colhead\" align=\"left\">".$lang_forums['col_posted_by']."</td></tr>\n");

		foreach ($posts as $post)
		{
			$post = (array) $post;
			print("<tr><td class=\"rowfollow\" align=\"center\" width=\"1%\">".$post['id']."</td><td class=\"rowfollow\" align=\"left\"><a href=\"".htmlspecialchars("?action=viewtopic&topicid=".$post['topicid']."&highlight=".rawurlencode($keywords)."&page=p".$post['id']."#pid".$post['id'])."\">" . highlight_topic(highlight($keywords,htmlspecialchars($post['subject'])), $post['hlcolor']) . "</a></td><td class=\"rowfollow nowrap\" align=\"left\"><a href=\"".htmlspecialchars("?action=viewforum&forumid=".$post['forumid'])."\"><b>" . htmlspecialchars($post["forumname"]) . "</b></a></td><td class=\"rowfollow nowrap\" align=\"left\">" . gettime($post['added'],true,false) . "&nbsp;|&nbsp;". get_username($post['userid']) ."</td></tr>\n");
		}

		print("</table>\n");
		print($pagerbottom);
	}
stdfoot();
die;
}

if (isset($_GET["catchup"]) && $_GET["catchup"] == 1){
	catch_up();
}

//-------- Handle unknown action
if ($action != "")
	stderr($lang_forums['std_forum_error'], $lang_forums['std_unknown_action']);

//-------- Default action: View forums

//-------- Get forums
if ($CURUSER)
	\App\Models\User::query()->where('id', $CURUSER['id'])->update(['forum_access' => date("Y-m-d H:i:s")]);

stdhead($lang_forums['head_forums']);
begin_main_frame();
print("<h1 align=\"center\">".$SITENAME."&nbsp;".$lang_forums['text_forums']."</h1>");
print("<p align=\"center\"><a href=\"?action=search\"><b>".$lang_forums['text_search']."</b></a> | <a href=\"?action=viewunread\"><b>".$lang_forums['text_view_unread']."</b></a> | <a href=\"?catchup=1\"><b>".$lang_forums['text_catch_up']."</b></a> ".(user_can('forummanage') ? "| <a href=\"forummanage.php\"><b>".$lang_forums['text_forum_manager']."</b></a>":"")."</p>");
print("<table border=\"1\" cellspacing=\"0\" cellpadding=\"5\" width=\"100%\">\n");

if (!$overforums = $Cache->get_value('overforums_list')){
	$overforums = \App\Models\OverForum::query()->orderBy('sort')->get()->toArray();
	$Cache->cache_value('overforums_list', $overforums, 86400);
}
foreach ($overforums as $a)
{
	if (get_user_class() < $a["minclassview"])
		continue;
	$forid = $a["id"];
	$overforumname = $a["name"];

	print("<tr><td align=\"left\" class=\"colhead\" width=\"99%\">".htmlspecialchars($overforumname)."</td><td align=\"center\" class=\"colhead\">".$lang_forums['col_topics']."</td>" .
	"<td align=\"center\" class=\"colhead\">".$lang_forums['col_posts']."</td>" .
	"<td align=\"left\" class=\"colhead\">".$lang_forums['col_last_post']."</td><td class=\"colhead\" align=\"left\">".$lang_forums['col_moderator']."</td></tr>\n");

	$forums = get_forum_row();
	foreach ($forums as $forums_arr)
	{
		if ($forums_arr['forid'] != $forid)
			continue;
		if (get_user_class() < $forums_arr["minclassread"])
			continue;

		$forumid = $forums_arr["id"];
		$forumname = htmlspecialchars($forums_arr["name"]);
		$forumdescription = htmlspecialchars($forums_arr["description"]);

		$forummoderators = get_forum_moderators($forums_arr['id'],false);
		if (!$forummoderators)
			$forummoderators = "<a href=\"contactstaff.php\"><i>".$lang_forums['text_apply_now']."</i></a>";

		$topiccount = number_format($forums_arr["topiccount"]);
		$postcount = number_format($forums_arr["postcount"]);

		// Find last post ID
		//Returns the ID of the last post of a forum
		if (!$arr = $Cache->get_value('forum_'.$forumid.'_last_replied_topic_content')){
			$lastTopic = \App\Models\Topic::query()->where('forumid', $forumid)->orderByDesc('lastpost')->first();
			$arr = $lastTopic ? $lastTopic->toArray() : false;
			$Cache->cache_value('forum_'.$forumid.'_last_replied_topic_content', $arr, 900);
		}

		if ($arr)
		{
			$lastpostid = $arr['lastpost'];
			// Get last post info
			$post_arr = get_post_row($lastpostid);
			$lastposterid = $post_arr["userid"];
			$lastpostdate = gettime($post_arr["added"],true,false);
			$lasttopicid = $arr['id'];
			$hlcolor = $arr['hlcolor'];
			$lasttopicdissubject = $lasttopicsubject = $arr['subject'];
			$max_length_of_topic_subject = 35;
			$count_dispname = mb_strlen($lasttopicdissubject,"UTF-8");
			if ($count_dispname > $max_length_of_topic_subject)
				$lasttopicdissubject = mb_substr($lasttopicdissubject, 0, $max_length_of_topic_subject-2,"UTF-8") . "..";
			$lasttopic = highlight_topic(htmlspecialchars($lasttopicdissubject), $hlcolor);

			$lastpost = "<a href=\"".htmlspecialchars("?action=viewtopic&topicid=".$lasttopicid."&page=last#last")."\" title=\"".htmlspecialchars($lasttopicsubject)."\">".$lasttopic."</a><br />". $lastpostdate."&nbsp;|&nbsp;".get_username($lastposterid);

			$lastreadpost = get_last_read_post_id($lasttopicid);

			if ($lastreadpost >= $lastpostid)
				$img = get_topic_image("read");
			else
				$img = get_topic_image("unread");
		}
		else
		{
			$lastpost = "N/A";
			$img = get_topic_image("read");
		}
		$posttodaycount = $Cache->get_value('forum_'.$forumid.'_post_'.$today_date.'_count');
		if ($posttodaycount == ""){
			$posttodaycount = \Nexus\Database\NexusDB::table('posts')
				->leftJoin('topics', 'posts.topicid', '=', 'topics.id')
				->where('posts.added', '>', date("Y-m-d"))
				->where('topics.forumid', $forumid)
				->count('posts.id');
			$Cache->cache_value('forum_'.$forumid.'_post_'.$today_date.'_count', $posttodaycount, 1800);
		}
		if ($posttodaycount > 0)
			$posttoday = "&nbsp;&nbsp;(".$lang_forums['text_today']."<b><font class=\"new\">".$posttodaycount."</font></b>)";
		else $posttoday = "";
		print("<tr><td class=\"rowfollow\" align=\"left\"><table border=\"0\" cellspacing=\"0\" cellpadding=\"0\"><tr><td class=\"embedded\" style='padding-right: 10px'>".$img."</td><td class=\"embedded\"><a href=\"".htmlspecialchars("?action=viewforum&forumid=".$forumid)."\"><font class=\"big\"><b>".$forumname."</b></font></a>" .$posttoday.
		"<br />".$forumdescription."</td></tr></table></td><td class=\"rowfollow\" align=\"center\" width=\"1%\">".$topiccount."</td><td class=\"rowfollow\" align=\"center\" width=\"1%\">".$postcount."</td>" .
		"<td class=\"rowfollow nowrap\" align=\"left\">".$lastpost."</td><td class=\"rowfollow\" align=\"left\">".$forummoderators."</td></tr>\n");
	}
}
// End Table Mod
print("</table>");
if ($showforumstats_main == "yes")
	forum_stats();
end_main_frame();
stdfoot();
?>

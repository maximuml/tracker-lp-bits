<?php
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING & ~E_DEPRECATED);

// Auto-generated legacy bridge shims
if (!isset($CURUSER)) $CURUSER = (array) (\App\Support\SupportContext::getUser() ?? []);
if (!isset($Cache)) $Cache = \App\Support\SupportContext::getCache();
if (!isset($BASEURL)) $BASEURL = \App\Support\SupportContext::getGlobal('BASEURL', '');
if (!isset($lang_folder)) $lang_folder = (array) (\App\Support\SupportContext::getGlobal('lang_folder') ?? []);
if (!isset($lang_functions)) $lang_functions = (array) (\App\Support\SupportContext::getGlobal('lang_functions') ?? []);
if (!isset($lang_usercp)) $lang_usercp = (array) (\App\Support\SupportContext::getGlobal('lang_usercp') ?? []);
$__server_PHP_SELF = \App\Support\SupportContext::getServerValue('PHP_SELF');
$__server_REMOTE_ADDR = \App\Support\SupportContext::getServerValue('REMOTE_ADDR');
$CURUSER['notifs'] = (string) ($CURUSER['notifs'] ?? '');
$userInfo = \App\Repositories\UsercpRepository::getUserById((int) $CURUSER["id"]);
$siteName = \App\Models\Setting::getSiteName();

if (!function_exists('usercpmenu')) { function usercpmenu ($selected = "home") {
$lang_usercp = (array) (\App\Support\SupportContext::getGlobal('lang_usercp') ?? []);
	print ("<div id=\"usercpnav\"><ul id=\"usercpmenu\" class=\"menu\">");
	print ("<li" . ($selected == "home" ? " class=selected" : "") . "><a href=\"usercp.php\">".$lang_usercp['text_user_cp_home']."</a></li>");
	print ("<li" . ($selected == "personal" ? " class=selected" : "") . "><a href=\"?action=personal\">".$lang_usercp['text_personal_settings']."</a></li>");
	print ("<li" . ($selected == "tracker" ? " class=selected" : "") . "><a href=\"?action=tracker\">".$lang_usercp['text_tracker_settings']."</a></li>");
	print ("<li" . ($selected == "forum" ? " class=selected" : "") . "><a href=\"?action=forum\">".$lang_usercp['text_forum_settings']."</a></li>");
	print ("<li" . ($selected == "security" ? " class=selected" : "") . "><a href=\"?action=security\">".$lang_usercp['text_security_settings']."</a></li>");
	print ("</ul></div>");
} }
if (!function_exists('getimagewidth')) { function getimagewidth ($imagewidth, $imageheight)
{
	while (($imagewidth > 150) or ($imageheight > 150))
	{
		$imagewidth=150;
		$imageheight=150;
	}
	return $imagewidth;
} }
if (!function_exists('getimageheight')) { function getimageheight ($imagewidth, $imageheight)
{
	while (($imagewidth > 150) or ($imageheight > 150))
	{
		$imagewidth=150;
		$imageheight=150;
	}
	return $imageheight;
} }
if (!function_exists('form')) { function form($name, $type = "save", $id = "") {
    if ($id == "") {
        $id = "form" . \App\Support\Strings::randomCode((int) 6);
    }
	return print("<form method=post action=usercp.php id=\"".$id."\"><input type=hidden name=action value=".htmlspecialchars($name)."><input type=hidden name=type value={$type}>");
} }
if (!function_exists('submit')) { function submit($type = "submit") {
$lang_usercp = (array) (\App\Support\SupportContext::getGlobal('lang_usercp') ?? []);
	print("<tr><td class=\"rowhead\" valign=\"top\" align=\"right\">".$lang_usercp['row_save_settings']."</td><td class=\"rowfollow\" valign=\"top\" align=left><input type=".$type." value=\"".$lang_usercp['submit_save_settings']."\"></td></tr>");
} }
if (!function_exists('format_tz')) { function format_tz($a)
{
	$h = floor($a);
	$m = ($a - floor($a)) * 60;
	return ($a >= 0?"+":"-") . (strlen(abs($h)) > 1?"":"0") . abs($h) .
	":" . ($m==0?"00":$m);
} }
if (!function_exists('priv')) { function priv($name, $descr) {
$CURUSER = \App\Support\SupportContext::getUser() ?? [];
	if ($CURUSER["privacy"] == $name)
	return "<input type=\"radio\" name=\"privacy\" value=\"".htmlspecialchars($name)."\" checked=\"checked\" /> ".htmlspecialchars($descr);
	else
	return "<input type=\"radio\" name=\"privacy\" value=\"".htmlspecialchars($name)."\" /> ".htmlspecialchars($descr);
} }
if (!function_exists('goback')) { function goback ($where = "-1") {
$lang_usercp = (array) (\App\Support\SupportContext::getGlobal('lang_usercp') ?? []);
	$text = $lang_usercp['text_go_back'];
	$goback = "<a class=faqlink HREF=\"javascript:history.go(".htmlspecialchars($where).")\">".htmlspecialchars($text)."</a>";
	return $goback;
} }
$action = ((\App\Support\SupportContext::getPost('action') !== null)) ? htmlspecialchars(\App\Support\SupportContext::getPost('action')) : (((\App\Support\SupportContext::getQuery('action') !== null)) ? htmlspecialchars(\App\Support\SupportContext::getQuery('action')) : '');
$type = ((\App\Support\SupportContext::getPost('type') !== null)) ? htmlspecialchars(\App\Support\SupportContext::getPost('type')) : (((\App\Support\SupportContext::getQuery('type') !== null)) ? htmlspecialchars(\App\Support\SupportContext::getQuery('type')) : '');

$allowed_actions = array("personal","tracker","forum","security");
if ($action){
	if (!in_array($action, $allowed_actions))
		\App\Support\LegacyResponse::abort($lang_usercp['std_error'], $lang_usercp['std_invalid_action']);
	else {
	switch ($action) {
		case "personal":
			if ($type == 'save') {
				$data = [];
				$parked = \App\Support\SupportContext::getPost("parked");
				if ($parked != 'yes')
					$parked = 'no';
				$acceptpms = \App\Support\SupportContext::getPost("acceptpms");
				$deletepms = (\App\Support\SupportContext::getPost("deletepms") != "" ? "yes" : "no");
				$savepms = (\App\Support\SupportContext::getPost("savepms") != "" ? "yes" : "no");
				$commentpm = \App\Support\SupportContext::getPost("commentpm");
				$gender = \App\Support\SupportContext::getPost("gender");
				$country = \App\Support\SupportContext::getPost("country");
				//	$tzoffset = \App\Support\SupportContext::getPost("tzoffset");
				$avatar = (\App\Support\SupportContext::getPost("avatar") ?? '') == '' ? (\App\Support\SupportContext::getPost("savatar") ?? '') : (\App\Support\SupportContext::getPost("avatar") ?? '');

				if(preg_match("/^https?:\/\/[^\s'\"<>]+\.(jpg|gif|png|jpeg)$/i", $avatar) && !preg_match("/\.php/i",$avatar) && !preg_match("/\.js/i",$avatar) && !preg_match("/\.cgi/i",$avatar)) {
					$data['avatar'] = htmlspecialchars( trim( $avatar ) );
				}
				$info = htmlspecialchars(trim(\App\Support\SupportContext::getPost("info") ?? ''));

				$data['parked'] = $parked;
				$data['acceptpms'] = $acceptpms;
				$data['deletepms'] = $deletepms;
				$data['savepms'] = $savepms;
				$data['commentpm'] = $commentpm;
				$data['gender'] = $gender;
				if (\App\Support\Validators::isId($country))
					$data['country'] = (int)$country;
				//	$data['tzoffset'] = $tzoffset;

				$data['info'] = $info;
				$data['tracker_url_id'] = \App\Support\SupportContext::getPost("tracker_url_id");

				//notifs
                if (!empty(\App\Support\SupportContext::getPost('notifs'))) {
                    preg_match_all('/\[(.*)\]/Ui', (string) $CURUSER['notifs'], $notifsArr);
                    $notifsArr = array_fill_keys($notifsArr[1], 1);
                    foreach (\App\Models\User::$notificationOptions as $option) {
                        if (((\App\Support\SupportContext::getPost('notifs') !== null))) {
                            $notifsArr[$option] = 1;
                        } else {
                            unset($notifsArr[$option]);
                        }
                    }
                    $data['notifs'] = '[' . implode('][', array_keys($notifsArr)) . ']';
                }
				\App\Repositories\UsercpRepository::updateUser((int) $CURUSER["id"], $data);
				\App\Support\Cache::clearUser($CURUSER["id"], $CURUSER['passkey']);
				header("Location: usercp.php?action=personal&type=saved");
			}

			$countries = "<option value=0>---- ".$lang_usercp['select_none_selected']." ----</option>\n";
			$countryRows = \App\Repositories\UsercpRepository::getCountryOptions();
			foreach ($countryRows as $ct_a)
			$countries .= "<option value=".htmlspecialchars($ct_a->id)."" . (htmlspecialchars($CURUSER["country"]) == htmlspecialchars($ct_a->id) ? " selected" : "") . ">".htmlspecialchars($ct_a->name)."</option>\n";

            $trackerUrls = "<option value=0>---- ".$lang_usercp['select_none_selected']." ----</option>\n";
            $trackerUrlList = \App\Models\TrackerUrl::listAll();
            foreach ($trackerUrlList as $item) {
                $trackerUrls .= "<option value=".htmlspecialchars($item->id)."" . (htmlspecialchars($CURUSER["tracker_url_id"]) == htmlspecialchars($item->id) ? " selected" : "") . ">".htmlspecialchars($item->url)."</option>\n";
            }
			$bitbucketRows = \App\Repositories\UsercpRepository::getBitbucketOptions();
			$options='';
			$text = '';
			foreach ($bitbucketRows as $sor)
			{
				$text.='<option value="'. \App\Support\Http::protocolPrefix(\App\Support\Url::isSecure()) . $BASEURL .'/bitbucket/'.$sor->name.'">'.$sor->name.'</option>';
			}

			usercpmenu ("personal");
            form ("personal");
			print ("<table border=0 cellspacing=0 cellpadding=5 width=".CONTENT_WIDTH.">");
			if ($type == 'saved')
				print("<tr><td colspan=2 class=\"heading\" valign=\"top\" align=\"center\"><font color=red>".$lang_usercp['text_saved']."</font></td></tr>\n");

			\App\Support\Html::trSmall($lang_usercp['row_account_parked'], "<input type=checkbox name=parked" . ($CURUSER["parked"] == "yes" ? " checked" : "") . " value=yes>".$lang_usercp['checkbox_pack_my_account']."<br /><font class=small size=1>".$lang_usercp['text_account_pack_note']."</font>", 1);
			$pmY = $lang_usercp['text_accept_pms']."<input type=radio name=acceptpms" . ($CURUSER["acceptpms"] == "yes" ? " checked" : "") . " value=yes>".$lang_usercp['radio_all_except_blocks']."<input type=radio name=acceptpms" .  ($CURUSER["acceptpms"] == "friends" ? " checked" : "") . " value=friends>".$lang_usercp['radio_friends_only']."<input type=radio name=acceptpms" .  ($CURUSER["acceptpms"] == "no" ? " checked" : "") . " value=no>".$lang_usercp['radio_staff_only']
                ."<br /><input type=checkbox name=deletepms" . ($CURUSER["deletepms"] == "yes" ? " checked" : "") . "> ".$lang_usercp['checkbox_delete_pms']
                ."<br /><input type=checkbox name=savepms" . ($CURUSER["savepms"] == "yes" ? " checked" : "") . "> ".$lang_usercp['checkbox_save_pms']
                ."<br /><input type=checkbox name=commentpm" . ($CURUSER["commentpm"] == "yes" ? " checked" : "") . " value=yes> ".$lang_usercp['checkbox_pm_on_comments']
            ;
			foreach (\App\Models\User::$notificationOptions as $option) {
			    $pmY .= sprintf('<br /><input type="checkbox" name="notifs[%s]"%s value="yes" /> %s', $option, is_null($CURUSER['notifs']) || str_contains($CURUSER['notifs'], "[{$option}]") == "yes" ? " checked" : "", $lang_usercp["checkbox_pm_on_{$option}"]);
            }
			\App\Support\Html::trSmall($lang_usercp['row_pms'], $pmY, 1);

			\App\Support\Html::trSmall($lang_usercp['row_gender'], "<input type=radio name=gender" . ($CURUSER["gender"] == "N/A" ? " checked" : "") . " value=N/A>".$lang_usercp['radio_not_available']."
<input type=radio name=gender" . ($CURUSER["gender"] == "Male" ? " checked" : "") . " value=Male>".$lang_usercp['radio_male']."<input type=radio name=gender" .  ($CURUSER["gender"] == "Female" ? " checked" : "") . " value=Female>".$lang_usercp['radio_female'], 1);
            \App\Support\Html::trSmall($lang_usercp['row_tracker_url'], "<select name=tracker_url_id>\n$trackerUrls\n</select>" . "<br /><font class=small size=1>".$lang_usercp['row_tracker_url_help']."</font>", 1);
            \App\Support\Html::trSmall($lang_usercp['row_country'], "<select name=country>\n$countries\n</select>", 1);
			\App\Support\Html::trSmall($lang_usercp['row_avatar_url'], "<img src=".($CURUSER["avatar"] ? "'$CURUSER[avatar]'" : "'" . \App\Support\Http::protocolPrefix(\App\Support\Url::isSecure()) . $BASEURL . "/pic/default_avatar.png'")." name='avatarimg'><br />
  <select name=savatar OnChange=\"document.forms[0].avatarimg.src=this.value;this.form.avatar.value=this.value;\">
  <option value='$CURUSER[avatar]'>".$lang_usercp['select_choose_avatar']."</option>
  <option value='" . \App\Support\Http::protocolPrefix(\App\Support\Url::isSecure()) . $BASEURL . "/pic/default_avatar.png'>".$lang_usercp['select_nothing']."</option>
  $text
  </select><input type=text name=avatar style=\"width: 400px\" value=\"" . htmlspecialchars($CURUSER["avatar"] ?? '') .
  "\"><br />\n".$lang_usercp['text_avatar_note'].($enablebitbucket_main == 'yes' ? $lang_usercp['text_bitbucket_note'] : ""), 1);
  \App\Support\Html::tr($lang_usercp['row_info'], "<textarea name=\"info\" style=\"width:700px\" rows=\"10\" >" . htmlspecialchars($CURUSER["info"] ?? '') . "</textarea><br />".$lang_usercp['text_info_note'], 1);
  submit();
  print("</table></form>");
  return;
  break;
		case "tracker":
			if ($enabletooltip_tweak == 'yes')
				$showtooltipsetting = true;
			else
				$showtooltipsetting = false;
			if ($type == 'save') {
				$data = [];
				$pmnotif = \App\Support\SupportContext::getPost("pmnotif") ?? '';
				$emailnotif = \App\Support\SupportContext::getPost("emailnotif") ?? '';

                preg_match_all('/\[(.*)\]/Ui', $CURUSER['notifs'], $notifs);
                $notifs = array_fill_keys($notifs[1], 1);
                foreach ($notifs as $key => $value) {
                    foreach (['incldead', 'spstate', 'inclbookmarked'] as $item) {
                        if (str_starts_with($key, $item)) {
                            unset($notifs[$key]);
                        }
                    }
                }

				if ($pmnotif == 'yes') {
                    $notifs['pm'] = 1;
                } else {
				    unset($notifs['pm']);
                }
				if ($emailnotif == 'yes') {
                    $notifs['email'] = 1;
                } else {
				    unset($notifs['email']);
                }

			if (!function_exists('browsecheck')) { function browsecheck($dbtable, $cbname, array &$result){
				$ids = \App\Repositories\UsercpRepository::getTableIds($dbtable);
				foreach ($ids as $id) {
					if (((\App\Support\SupportContext::getPost($cbname.$id) !== null)) && \App\Support\SupportContext::getPost($cbname.$id) == 'yes') {
					    $result[$cbname.$id] = 1;
                    } else {
					    unset($result[$cbname.$id]);
                    }
				}
			} }
                browsecheck("categories", "cat", $notifs);
				browsecheck("sources", "sou", $notifs);
				browsecheck("media", "med", $notifs);
				browsecheck("codecs", "cod", $notifs);
				browsecheck("standards", "sta", $notifs);
				browsecheck("processings", "pro", $notifs);
				browsecheck("audiocodecs", "aud", $notifs);
				$incldead = \App\Support\SupportContext::getPost("incldead");
				if ((isset($incldead)) && $incldead != 1) {
				    $notifs["incldead=$incldead"] = 1;
                }
				$spstate = \App\Support\SupportContext::getPost("spstate");
				if ($spstate) {
                    $notifs["spstate=$spstate"] = 1;
                }
				$inclbookmarked = \App\Support\SupportContext::getPost("inclbookmarked");
				if ($inclbookmarked) {
                    $notifs["inclbookmarked=$inclbookmarked"] = 1;
                }
				$stylesheet = \App\Support\SupportContext::getPost("stylesheet");
//				$caticon = \App\Support\SupportContext::getPost("caticon");
				$sitelanguage = \App\Support\SupportContext::getPost("sitelanguage");
				$fontsize = \App\Support\SupportContext::getPost("fontsize");
				if ($fontsize == 'large' || $fontsize == 'small')
					$data['fontsize'] = $fontsize;
				else
					$data['fontsize'] = 'medium';
				$data['notifs'] = '[' . implode('][', array_keys($notifs)) . ']';

				if (\App\Support\Validators::isId($stylesheet))
					$data['stylesheet'] = (int)$stylesheet;

				if (\App\Support\Validators::isId($sitelanguage))
				{
					$lang_folder = \App\Support\Locale::folderForIdWithContext($sitelanguage);
					if(\App\Support\Locale::folderFromCookie(\App\Support\SupportContext::getCookieValue('c_lang_folder', ''), (bool) false) != $lang_folder)
					{
						\App\Support\Locale::setFolderCookie((string) $lang_folder, (int) 0x7fffffff);
						header("Location: " . $__server_PHP_SELF);
					}
					$data['lang'] = (int)$sitelanguage;
				}

				$data['torrentsperpage'] = min(100, intval(\App\Support\SupportContext::getPost("torrentsperpage") ?? 0));
				if ($showtooltipsetting){
					$data['tooltip'] = \App\Support\SupportContext::getPost('tooltip');
				}
				$data['timetype'] = \App\Support\SupportContext::getPost('timetype');

				$data['appendsticky'] = (\App\Support\SupportContext::getPost("appendsticky") == 'yes' ? "yes" : "no");
				$data['appendnew'] = (\App\Support\SupportContext::getPost("appendnew") == 'yes' ? "yes" : "no");
				$data['appendpromotion'] = \App\Support\SupportContext::getPost("appendpromotion");
				$data['appendpicked'] = (\App\Support\SupportContext::getPost("appendpicked") == 'yes' ? "yes" : "no");
				$data['dlicon'] = (\App\Support\SupportContext::getPost('dlicon') == 'yes' ? "yes" : "no");
				$data['bmicon'] = (\App\Support\SupportContext::getPost('bmicon') == 'yes' ? "yes" : "no");

				$data['showcomnum'] = (\App\Support\SupportContext::getPost("showcomnum") == 'yes' ? "yes" : "no");
				if ($showtooltipsetting){
					$data['showlastcom'] = (\App\Support\SupportContext::getPost("showlastcom") == 'yes' ? "yes" : "no");
				}
				$data['pmnum'] = (\App\Support\SupportContext::getPost("pmnum") < 1 || \App\Support\SupportContext::getPost("pmnum") > 100 ? 20 : floor(\App\Support\SupportContext::getPost("pmnum")));
				$data['sbnum'] = (\App\Support\SupportContext::getPost("sbnum") ? max(10, min(500, intval(\App\Support\SupportContext::getPost("sbnum") ?? 0))) : 70);
				$data['sbrefresh'] = (\App\Support\SupportContext::getPost("sbrefresh") ? max(10, min(3600, intval(\App\Support\SupportContext::getPost("sbrefresh") ?? 0))) : 120);

				if (\App\Support\SupportContext::getPost("showdescription") == 'yes')
					$data['showdescription'] = 'yes';
				else
					$data['showdescription'] = 'no';

				if (\App\Support\SupportContext::getPost("smalldescr") == 'yes')
					$data['showsmalldescr'] = 'yes';
				else
					$data['showsmalldescr'] = 'no';
				if (\App\Support\SupportContext::getPost("showcomment") == 'yes')
					$data['showcomment'] = 'yes';
				else
					$data['showcomment'] = 'no';

				\App\Repositories\UsercpRepository::updateUser((int) $CURUSER["id"], $data);
				header("Location: usercp.php?action=tracker&type=saved");
			}
			usercpmenu ("tracker");
            form ("tracker");
if (strpos($CURUSER['notifs'], "[spstate=0]") !== false)
    $special_state = 0;
elseif (strpos($CURUSER['notifs'], "[spstate=1]") !== false)
    $special_state = 1;
elseif (strpos($CURUSER['notifs'], "[spstate=2]") !== false)
    $special_state = 2;
elseif (strpos($CURUSER['notifs'], "[spstate=3]") !== false)
    $special_state = 3;
elseif (strpos($CURUSER['notifs'], "[spstate=4]") !== false)
    $special_state = 4;
elseif (strpos($CURUSER['notifs'], "[spstate=5]") !== false)
    $special_state = 5;
elseif (strpos($CURUSER['notifs'], "[spstate=6]") !== false)
    $special_state = 6;
elseif (strpos($CURUSER['notifs'], "[spstate=7]") !== false)
    $special_state = 7;
else $special_state = 0;

			print ("<table border=0 cellspacing=0 cellpadding=5 width=".CONTENT_WIDTH.">");
			if ($type == 'saved')
				print("<tr><td colspan=2 class=\"heading\" valign=\"top\" align=\"center\"><font color=red>".$lang_usercp['text_saved']."</font></td></tr>\n");
			if ($emailnotify_smtp=='yes' && $smtptype != 'none')
				\App\Support\Html::trSmall($lang_usercp['row_email_notification'], "<input type=checkbox name=pmnotif" . (strpos($CURUSER['notifs'], "[pm]") !== false ? " checked" : "") . " value=yes> ".$lang_usercp['checkbox_notification_received_pm']."<br />\n<input type=checkbox name=emailnotif" . (strpos($CURUSER['notifs'], "[email]") !== false ? " checked" : "") . " value=\"yes\" /> ".$lang_usercp['checkbox_notification_default_categories'], 1);
            //no this option
			$brenablecatrow = false;


            $categories = \App\Support\SearchBox::buildCategoryTableWithContext($browsecatmode, 'yes', 'torrents.php?allsec=1', false, 3, $CURUSER['notifs'], ['section_name' => true]);
            $delimiter = '<div style="height: 1px;background-color: #eee;margin: 10px 0"></div>';
            $categories .= $delimiter . "<table><caption><font class='big'>{$lang_usercp['text_additional_selection']}</font></caption><tr><td class=bottom><b>".$lang_usercp['text_show_dead_active']."</b><br /><select name=\"incldead\"><option value=\"0\" ".(strpos($CURUSER['notifs'], "[incldead=0]") !== false ? " selected" : "").">".$lang_usercp['select_including_dead']."</option><option value=\"1\" ".(strpos($CURUSER['notifs'], "[incldead=1]") !== false ||  strpos($CURUSER['notifs'], "incldead") == false ? " selected" : "").">".$lang_usercp['select_active']."</option><option value=\"2\" ".(strpos($CURUSER['notifs'], "[incldead=2]") !== false  ? " selected" : "").">".$lang_usercp['select_dead']."</option></select></td><td class=bottom align=left><b>".$lang_usercp['text_show_special_torrents']."</b><br /><select name=\"spstate\"><option value=\"0\" ".($special_state == 0 ? " selected" : "").">".$lang_usercp['select_all']."</option>".\App\Support\Html::promotionSelection($special_state)."</select></td><td class=bottom><b>".$lang_usercp['text_show_bookmarked']."</b><br /><select name=\"inclbookmarked\"><option value=\"0\" ".(strpos($CURUSER['notifs'], "[inclbookmarked=0]") !== false ? " selected" : "").">".$lang_usercp['select_all']."</option><option value=\"1\" ".(strpos($CURUSER['notifs'], "[inclbookmarked=1]") !== false ? " selected" : "")." >".$lang_usercp['select_bookmarked']."</option><option value=\"2\" ".(strpos($CURUSER['notifs'], "[inclbookmarked=2]") !== false ? " selected" : "").">".$lang_usercp['select_bookmarked_exclude']."</option></select></td></tr></table>";
            \App\Support\Html::trSmall($lang_usercp['row_browse_default_categories'], $categories, 1);
			$ss_sa = \App\Repositories\UsercpRepository::getStylesheetOptions();
			ksort($ss_sa);
            $stylesheets = $categoryicons = '';
//			while (list($ss_name, $ss_id) = each($ss_sa))
            foreach ($ss_sa as $ss_name => $ss_id)
			{
				if ($ss_id == $CURUSER["stylesheet"]) $ss = " selected"; else $ss = "";
				$stylesheets .= "<option value=$ss_id$ss>$ss_name</option>\n";
			}
            \App\Support\Html::trSmall($lang_usercp['row_stylesheet'], "<select name=stylesheet>\n$stylesheets\n</select>&nbsp;&nbsp;<font class=small>".$lang_usercp['text_stylesheet_note']."<a href=\"aboutnexus.php#stylesheet\" ><b>".$lang_usercp['text_stylesheet_link']."</b></a></font>.", 1);
            /**
             * make no sense, should no exists!
             * @since v1.6.0
             */
//			$cires = sql_query("SELECT * FROM caticons ORDER BY name") or return;
//			while($caticon = mysql_fetch_array($cires)){
//				if ($caticon['id'] == $CURUSER['caticon']) $sl = " selected"; else $sl = "";
//				$categoryicons .= "<option value=".$caticon['id'].$sl.">".$caticon['name']."</option>\n";
//			}
//			tr_small($lang_usercp['row_category_icons'], "<select name=caticon>".$categoryicons."</select>",1);

            \App\Support\Html::trSmall($lang_usercp['row_font_size'], "<select name=fontsize><option value=small ".($CURUSER['fontsize'] == 'small' ? " selected" : "").">".$lang_usercp['select_small']."</option><option value=medium ".($CURUSER['fontsize'] == 'medium' ? " selected" : "").">".$lang_usercp['select_medium']."</option><option value=large ".($CURUSER['fontsize'] == 'large' ? " selected" : "").">".$lang_usercp['select_large']."</option></select>", 1);

			$s = "<select name=\"sitelanguage\">\n";

			$langs = \App\Support\Locale::languageList("site_lang", true);

			foreach ($langs as $row)
			{
				if ($row["site_lang_folder"] == \App\Support\Locale::folderFromCookie(\App\Support\SupportContext::getCookieValue('c_lang_folder', ''), (bool) false)) $se = " selected"; else $se = "";
				$s .= "<option value=". $row["id"] . $se. ">" . htmlspecialchars($row["lang_name"]) . "</option>\n";
			}
			$s .= "\n</select>&nbsp;&nbsp;<font class=small>".$lang_usercp['text_translation_note']."<a href=\"aboutnexus.php#translation\"><b>".$lang_usercp['text_translation_link']."</b></a></font>.</td></tr>";

			\App\Support\Html::trSmall($lang_usercp['row_site_language'], $s, 1);

			\App\Support\Html::trSmall($lang_usercp['row_pm_boxes'], $lang_usercp['text_show']."<input type=text name=pmnum size=5 value=".$CURUSER['pmnum']." >".$lang_usercp['text_pms_per_page'], 1);
if ($showshoutbox_main == "yes") //system side setting for shoutbox
			\App\Support\Html::trSmall($lang_usercp['row_shoutbox'], $lang_usercp['text_show_last']."<input type=text name=sbnum size=5 value=".$CURUSER['sbnum']." >".$lang_usercp['text_messages_at_shoutbox']."<br />".$lang_usercp['text_refresh_shoutbox_every']."<input type=text name=sbrefresh size=5 value=".$CURUSER['sbrefresh']." >".$lang_usercp['text_seconds'], 1);

			\App\Support\Html::trSmall($lang_usercp['row_torrent_detail'], "<input type=checkbox name=showdescription".($CURUSER["showdescription"] == "yes" ? " checked" : "") ." value=yes>".$lang_usercp['text_show_description'], 1);
			\App\Support\Html::trSmall($lang_usercp['row_discuss'], "<input type=checkbox name=showcomment".($CURUSER["showcomment"] == "yes" ? " checked" : "") ." value=yes>".$lang_usercp['text_show_comments'], 1);
			\App\Support\Html::trSmall($lang_usercp['row_time_type'], "<input type=radio name=timetype ".($CURUSER['timetype'] == 'timeadded' ? " checked" : "")." value=timeadded>".$lang_usercp['text_time_added']."&nbsp;&nbsp;<input type=radio name=timetype ".($CURUSER['timetype'] == 'timealive' ? " checked" : "")." value=timealive>".$lang_usercp['text_time_elapsed']."<br />", 1);
			//Setting for browse page
			\App\Support\Html::trSmall($lang_usercp['row_browse_page'], $lang_usercp['text_browse_setting_warning']."
		<br /><b>".$lang_usercp['row_torrent_page'].": </b><br />".$lang_usercp['text_show']."<input type=text size=5 name=torrentsperpage value=".$CURUSER['torrentsperpage']."> ".$lang_usercp['text_torrents_per_page'].$lang_usercp['text_zero_equals_default']."<br />".
		($showtooltipsetting ? "<b>".$lang_usercp['text_tooltip_type']."</b>: <br /><input type=radio name=tooltip ".($CURUSER['tooltip'] == 'off' ? " checked" : "")." value=off>".$lang_usercp['text_off']."<br />" : "").
		"<b>".$lang_usercp['text_append_words_to_torrents'].": </b><br /><input type=checkbox name=appendsticky ".($CURUSER['appendsticky'] == 'yes' ? " checked" : "")." value=yes>".$lang_usercp['text_append_sticky']."<br /><input type=checkbox name=appendnew ".($CURUSER['appendnew'] == 'yes' ? " checked" : "")." value=yes>".$lang_usercp['text_append_new']."<br />".$lang_usercp['text_torrents_on_promotion']."<input type=radio name=appendpromotion ".($CURUSER['appendpromotion'] == 'highlight' ? " checked" : "")." value='highlight'>".$lang_usercp['text_highlight']."<input type=radio name=appendpromotion ".($CURUSER['appendpromotion'] == 'word' ? " checked" : "")." value='word'>".$lang_usercp['text_append_words']."<input type=radio name=appendpromotion ".($CURUSER['appendpromotion'] == 'icon' ? " checked" : "")." value='icon'>".$lang_usercp['text_append_icon']."<input type=radio name=appendpromotion ".($CURUSER['appendpromotion'] == 'off' ? " checked" : "")." value='off'>".$lang_usercp['text_no_mark']."<br /><input type=checkbox name=appendpicked ".($CURUSER['appendpicked'] == 'yes' ? " checked" : "")." value=yes>".$lang_usercp['text_append_picked']."<br />
		<b>".$lang_usercp['text_show_action_icons'].": </b><br />"."<input type=checkbox name=dlicon ".($CURUSER['dlicon'] == 'yes' ? " checked" : "")." value=yes>".$lang_usercp['text_show_download_icon']." <img class=\"download\" src=\"pic/trans.gif\"  alt=\"Download\" /><br /><input type=checkbox name=bmicon ".($CURUSER['bmicon'] == 'yes' ? " checked" : "")." value=yes>".$lang_usercp['text_show_bookmark_icon']." <img class=\"bookmark\" src=\"pic/trans.gif\" alt=\"Bookmark\" /><br />
		<b>".$lang_usercp['text_comments_reviews'].": </b><br /><input type=checkbox name=showcomnum ".($CURUSER['showcomnum'] == 'yes' ? " checked" : "")." value=yes>".$lang_usercp['text_show_comment_number'].($showtooltipsetting ? "<select name=\"showlastcom\" style=\"width: 70px;\"><option value=\"yes\" ".($CURUSER['showlastcom'] != 'no' ? " selected" : "").">".$lang_usercp['select_with']."</option><option value=\"no\" ".($CURUSER['showlastcom'] == 'no' ? " selected" : "").">".$lang_usercp['select_without']."</option></select>".$lang_usercp['text_last_comment_on_tooltip'] : ""), 1);

			submit();
			print("</table></form>");
			return;
			break;
		case "forum":
			if ($enabletooltip_tweak == 'yes')
				$showtooltipsetting = true;
			else
				$showtooltipsetting = false;
			if ($type == 'save') {
				$data = [
				    'topicsperpage' => min(100, intval(\App\Support\SupportContext::getPost("topicsperpage") ?? 0)),
				    'postsperpage' => min(100, intval(\App\Support\SupportContext::getPost("postsperpage") ?? 0)),
				    'avatars' => (\App\Support\SupportContext::getPost("avatars") != "" ? "yes" : "no"),
				    'signatures' => (\App\Support\SupportContext::getPost("signatures") != "" ? "yes" : "no"),
				    'clicktopic' => \App\Support\SupportContext::getPost("clicktopic"),
				    'signature' => htmlspecialchars(trim(\App\Support\SupportContext::getPost("signature"))),
                ];
				if ($showtooltipsetting)
					$data['showlastpost'] = (\App\Support\SupportContext::getPost("ttlastpost") != "" ? "yes" : "no");

				\App\Repositories\UsercpRepository::updateUser((int) $CURUSER["id"], $data);
				header("Location: usercp.php?action=forum&type=saved");
			}
			usercpmenu ("forum");
            form ("forum");
			print ("<table border=0 cellspacing=0 cellpadding=5 width=".CONTENT_WIDTH.">");
			if ($type == 'saved')
			print("<tr><td colspan=2 class=\"heading\" valign=\"top\" align=\"center\"><font color=red>".$lang_usercp['text_saved']."</font></td></tr>\n");

			\App\Support\Html::trSmall($lang_usercp['row_topics_per_page'], "<input type=text size=10 name=topicsperpage value=$CURUSER[topicsperpage]>".$lang_usercp['text_zero_equals_default'], 1);
			\App\Support\Html::trSmall($lang_usercp['row_posts_per_page'], "<input type=text size=10 name=postsperpage value=$CURUSER[postsperpage]> ".$lang_usercp['text_zero_equals_default'], 1);
			\App\Support\Html::trSmall($lang_usercp['row_view_avatars'], "<input type=checkbox name=avatars" . ($CURUSER["avatars"] == "yes" ? " checked" : "") . ">".$lang_usercp['checkbox_low_bandwidth_note'], 1);
			\App\Support\Html::trSmall($lang_usercp['row_view_signatures'], "<input type=checkbox name=signatures" . ($CURUSER["signatures"] == "yes" ? " checked" : "") . ">".$lang_usercp['checkbox_low_bandwidth_note'], 1);
			if ($showtooltipsetting)
				\App\Support\Html::tr($lang_usercp['row_tooltip_last_post'], "<input type=checkbox name=ttlastpost" . ($CURUSER["showlastpost"] == "yes" ? " checked" : "") . ">".$lang_usercp['checkbox_last_post_note'], 1);
			\App\Support\Html::trSmall($lang_usercp['row_click_on_topic'], "<input type=radio name=clicktopic" . ($CURUSER["clicktopic"] == "firstpage" ? " checked" : "") . " value=\"firstpage\">".$lang_usercp['text_go_to_first_page']."<input type=radio name=clicktopic" . ($CURUSER["clicktopic"] == "lastpage" ? " checked" : "") . " value=\"lastpage\">".$lang_usercp['text_go_to_last_page'], 1);
			\App\Support\Html::trSmall($lang_usercp['row_forum_signature'], "<textarea name=signature style=\"width:700px\" rows=10>" . $CURUSER['signature'] . "</textarea><br />".$lang_usercp['text_signature_note'], 1);
			submit();
			print("</table></form>");
			return;
			break;
		case "security":
			if ($type == 'confirm') {
				$response = \App\Support\SupportContext::getPost('response');
				if (!$response){
					\App\Support\LegacyResponse::abort($lang_usercp['std_error'], $lang_usercp['std_enter_old_password'].goback(), 0);
				}
                //验证旧密码
                $challenge = \App\Repositories\UsercpRepository::getChallenge($userInfo->username);
                if (empty($challenge)) {
                    \App\Support\LegacyResponse::abort($lang_usercp['std_error'], "expired!".goback(), 0);
                }
                $expectedResponse = hash_hmac('sha256', $userInfo->passhash, $challenge);
                if (!hash_equals($expectedResponse, $response)) {
                    \App\Support\LegacyResponse::abort($lang_usercp['std_error'], $lang_usercp['std_wrong_password_note'].goback(), 0);
                }

				$data = [];
				$changedemail = 0;
				$passupdated = 0;
				$privacyupdated = 0;
				$resetpasskey = \App\Support\SupportContext::getPost("resetpasskey");
				$email = htmlspecialchars(trim(\App\Support\SupportContext::getPost("email")));
				$chpassword = \App\Support\SupportContext::getPost("chpassword");
//				$passagain = \App\Support\SupportContext::getPost("passagain");
				$privacy = \App\Support\SupportContext::getPost("privacy");
				$twoStepSecret = \App\Support\SupportContext::getPost('two_step_secret') ?? '';
				$twoStepSecretHash = \App\Support\SupportContext::getPost('two_step_code');

				if (!empty($twoStepSecretHash)) {
				    if (empty($CURUSER['two_step_secret'])) {
				        //do bind
                        $secretToVerify = $twoStepSecret;
                        $data['two_step_secret'] = $twoStepSecret;
                    } else {
				        //unbind
                        $secretToVerify = $CURUSER['two_step_secret'];
                        $data['two_step_secret'] = '';
                    }
                    if (!\App\Support\TwoFactorAuthHelper::verifyCode($secretToVerify, $twoStepSecretHash)) {
                        \App\Support\LegacyResponse::abort($lang_usercp['std_error'], 'Invalid two step code'.goback("-2"), 0);
                        return;
                    }
                }

				if ($chpassword != "") {
					$sec = \App\Support\Token::randomHex((int) 20);
					$passhash = hash('sha256', $sec . $chpassword);
					$data['secret'] = $sec;
					$data['passhash'] = $passhash;
                    $authKey = \App\Support\Token::randomHex((int) 20);
					$data['auth_key'] = $authKey;

					\App\Support\AuthCookie::setLoginCookie((int) $CURUSER["id"], (string) $authKey, (int) 0);
					$passupdated = 1;
				}

				if ($disableemailchange != 'no' && $smtptype != 'none' && $email != $CURUSER["email"])
				{
					if (!\App\Support\Validators::isEmail($email)){
						\App\Support\LegacyResponse::abort($lang_usercp['std_error'], $lang_usercp['std_wrong_email_address_format'].goback("-2"), 0);
						return;
					}
					if (\App\Repositories\UsercpRepository::emailExistsForOther((string) $email, (int) $CURUSER['id'])){
						\App\Support\LegacyResponse::abort($lang_usercp['std_error'], $lang_usercp['std_email_in_use'].goback("-2"), 0);
						return;
					}
					$changedemail = 1;
				}
				if ($resetpasskey == 1) {
					$passkey = md5($CURUSER['username'].date("Y-m-d H:i:s").$CURUSER['passhash']);
					$data['passkey'] = $passkey;
				}
				if ($changedemail == 1) {
					$sec = \App\Support\Token::randomHex((int) 20);
					$hash = md5($sec . $email . $sec);
					$obemail = rawurlencode($email);
					$data['editsecret'] = $sec;
					$subject = "$SITENAME".$lang_usercp['mail_profile_change_confirmation'];
                    $changeEmailOne = sprintf($lang_usercp['mail_change_email_one'], $siteName);
                    $changeEmailNine = sprintf($lang_usercp['mail_change_email_nine'], $siteName);
					$body = <<<EOD
{$changeEmailOne}{$CURUSER["username"]}{$lang_usercp['mail_change_email_two']}($email){$lang_usercp['mail_change_email_three']}

{$lang_usercp['mail_change_email_four']}{$__server_REMOTE_ADDR}{$lang_usercp['mail_change_email_five']}

{$lang_usercp['mail_change_email_six']}<b><a href="javascript:void(null)" onclick="window.open('http://$BASEURL/confirmemail.php/{$CURUSER["id"]}/$hash/$obemail')">{$lang_usercp['mail_here']}</a></b>{$lang_usercp['mail_change_email_six_1']}<br />
http://$BASEURL/confirmemail.php/{$CURUSER["id"]}/$hash/$obemail

{$lang_usercp['mail_change_email_seven']}

------{$lang_usercp['mail_change_email_eight']}
{$changeEmailNine}
EOD;

					\App\Support\Mail::sentLegacy((string) $email, (string) $SITENAME, (string) $SITEEMAIL, (string) $subject, (string) str_replace("<br />", "<br />", nl2br($body)), (string) "profile change", (bool) false, (bool) false, '', (string) 'UTF-8');

				}
				if ($privacy != "normal" && $privacy != "low" && $privacy != "strong")
				return;

				$data['privacy'] = $privacy;
				if ($CURUSER['privacy'] != $privacy) $privacyupdated = 1;

				$userId = $CURUSER["id"];
				$resetAuthKey = !empty(\App\Support\SupportContext::getRequestInput('resetauthkey')) && \App\Support\SupportContext::getRequestInput('resetauthkey') == 1;
                \App\Repositories\UsercpRepository::updateSecurity((int) $userId, $data, $resetAuthKey, (array) \App\Support\SupportContext::allPost());
				$to = "usercp.php?action=security&type=saved";
				if ($changedemail == 1)
				$to .= "&mail=1";
				if ($resetpasskey == 1)
				$to .= "&passkey=1";
				if ($passupdated == 1)
				$to .= "&password=1";
				if ($privacyupdated == 1)
				$to .= "&privacy=1";
				\App\Support\Cache::clearUser($CURUSER["id"], '');
                \App\Repositories\UsercpRepository::deleteChallenge($userInfo->username);
				header("Location: $to");
			}
			usercpmenu ("security");
            form ("security", $type == "save" ? "confirm" : "save","security");
			print ("<table border=0 cellspacing=0 cellpadding=5 width=".CONTENT_WIDTH.">");
			if ($type == 'save') {
//				print("<form method=post action=usercp.php><input type=hidden name=action value=security><input type=hidden name=type value=confirm>");
				$resetpasskey = \App\Support\SupportContext::getPost("resetpasskey");
				$resetauthkey = \App\Support\SupportContext::getPost("resetauthkey");
				$email = htmlspecialchars(trim(\App\Support\SupportContext::getPost("email")));
				$chpassword = \App\Support\SupportContext::getPost("chpassword");
				$passagain = \App\Support\SupportContext::getPost("passagain");
				$privacy = \App\Support\SupportContext::getPost("privacy");
				$two_step_secret = \App\Support\SupportContext::getPost("two_step_secret") ?? '';
				$two_step_code = \App\Support\SupportContext::getPost("two_step_code");
				if ($resetpasskey == 1)
				print("<input type=\"hidden\" name=\"resetpasskey\" value=\"1\">");
                if ($resetauthkey == 1)
				print("<input type=\"hidden\" name=\"resetauthkey\" value=\"1\">");
				print("<input type=\"hidden\" name=\"email\" value=\"$email\">");
				print("<input type=\"hidden\" name=\"chpassword\" value=\"$chpassword\">");
//				print("<input type=\"hidden\" name=\"passagain\" value=\"$passagain\">");
				print("<input type=\"hidden\" name=\"privacy\" value=\"$privacy\">");
				print("<input type=\"hidden\" name=\"two_step_secret\" value=\"$two_step_secret\">");
				print("<input type=\"hidden\" name=\"two_step_code\" value=\"$two_step_code\">");
				Print("<tr><td class=\"rowhead nowrap\" valign=\"top\" align=\"right\" width=1%>".$lang_usercp['row_security_check']."</td><td valign=\"top\" align=\"left\" width=\"99%\"><input type=password class=oldpassword style=\"width: 200px\"><br /><font class=small>".$lang_usercp['text_security_check_note']."</font></td></tr>\n");
				print('<input type=hidden name=username value="'.$CURUSER["username"].'">');
                print('<input type=hidden name=response>');
                \App\Support\Hooks::doAction("usercp_security_update_confirm", \App\Support\SupportContext::allPost());
                submit("button");
				print("</table></form>");
                \App\Support\Form::passwordChallengeJs("security", "username", "oldpassword");
				return;
			}
			if ($type == 'saved')
				print("<tr><td colspan=2 class=\"heading\" valign=\"top\" align=\"center\"><font color=red>".$lang_usercp['text_saved'].(\App\Support\SupportContext::getQuery("mail") == "1" ? $lang_usercp['std_confirmation_email_sent'] : "")." ".(\App\Support\SupportContext::getQuery("passkey") == "1" ? $lang_usercp['std_passkey_reset'] : "")." ".(\App\Support\SupportContext::getQuery("password") == "1" ? $lang_usercp['std_password_changed'] : "")." ".(\App\Support\SupportContext::getQuery("privacy") == "1" ? $lang_usercp['std_privacy_level_updated'] : "")."</font></td></tr>\n");

			\App\Support\Html::trSmall($lang_usercp['row_reset_passkey'], "<input type=checkbox name=resetpasskey value=1 />".$lang_usercp['checkbox_reset_my_passkey']."<br /><font class=small>".$lang_usercp['text_reset_passkey_note']."</font>", 1);
//			tr_small($lang_usercp['row_reset_authkey'],"<input type=checkbox name=resetauthkey value=1 />".$lang_usercp['checkbox_reset_my_authkey']."<br /><font class=small>".$lang_usercp['text_reset_authkey_note']."</font>", 1);

			//two step authentication
            if (!empty($CURUSER['two_step_secret'])) {
                \App\Support\Html::trSmall($lang_usercp['row_two_step_secret'], "<input type=text name=two_step_code />".$lang_usercp['text_two_step_secret_unbind_note'], 1);
            } else {
                $twoStepSecret = \App\Support\TwoFactorAuthHelper::createSecret();
                $twoStepQrCodeUrl = \App\Support\TwoFactorAuthHelper::qrCodeUrl(sprintf('%s(%s)', \App\Support\Config\SiteConfig::current()->basic->siteName(), $CURUSER['username']), $twoStepSecret);
                $twoStepY = '<div style="display: flex;align-items:center">';
                $twoStepY .= sprintf('<div><img src="%s" /></div>', $twoStepQrCodeUrl);
                $twoStepY .= sprintf(
                    '<div style="padding-left: 20px">%s<a href="%s" target="_blank">Link</a><br /><br />%s%s<br/><br/>%s<input type=hidden name=two_step_secret value="%s" /><input type=text name=two_step_code readonly onfocus="this.removeAttribute(\'readonly\')"/></div>',
                    $lang_usercp['text_two_step_secret_bind_by_qrdoe_note'],
                    $twoStepQrCodeUrl,
                    $lang_usercp['text_two_step_secret_bind_manually_note'],
                    $twoStepSecret,
                    $lang_usercp['text_two_step_secret_bind_complete_note'],
                    $twoStepSecret
                );
                $twoStepY .= '</div>';
                \App\Support\Html::trSmall($lang_usercp['row_two_step_secret'], $twoStepY, 1);
            }
            printf('<tr><td class="rowhead" valign="top" align="right">%s</td><td class="rowfollow" valign="top" align="left">', \App\Support\Locale::trans('passkey.passkey', [], null));
            \App\Repositories\UserPasskeyRepository::renderList($CURUSER['id']);
            printf('</td></tr>');

			if ($disableemailchange != 'no' && $smtptype != 'none') //system-wide setting
				\App\Support\Html::trSmall($lang_usercp['row_email_address'], "<input type=\"text\" name=\"email\" style=\"width: 200px\" value=\"" . htmlspecialchars($CURUSER["email"]) . "\" /> <br /><font class=small>".$lang_usercp['text_email_address_note']."</font>", 1);
            \App\Support\Hooks::doAction("usercp_security_setting_form");
            \App\Support\Html::trSmall($lang_usercp['row_change_password'], "<input type=\"password\" class=\"password\" style=\"width: 200px\" />", 1);
            print('<input type="hidden" name="chpassword" />');
			\App\Support\Html::trSmall($lang_usercp['row_type_password_again'], "<input type=\"password\" class=\"passagain\" style=\"width: 200px\" />", 1);
			\App\Support\Html::trSmall($lang_usercp['row_privacy_level'], priv("normal", $lang_usercp['radio_normal']) . " " . priv("low", $lang_usercp['radio_low']) . " " . priv("strong", $lang_usercp['radio_strong']), 1);
            submit("button");
			print("</table></form>");

            \App\Support\Form::passwordHashJs("security", "password", "chpassword", false, "passagain", "username");
			return;
			break;
	}
}
}

\Nexus\Nexus::js('vendor/jquery-loading/jquery.loading.min.js', 'footer', true);
usercpmenu ();
//Comment Results
$commentcount = \App\Repositories\UsercpRepository::getCommentCount((int) $CURUSER["id"]);

//Join Date
if ($CURUSER['added'] == "0000-00-00 00:00:00" || $CURUSER['added'] == null)
	$joindate = 'N/A';
else
	$joindate = $CURUSER['added']." (" . \App\Support\Time::format($CURUSER['added'],true,false,true).")";

//Forum Posts
if (!$forumposts = $Cache->get_value('user_'.$CURUSER['id'].'_post_count')){
	$forumposts = \App\Repositories\UsercpRepository::getForumPostCount((int) $CURUSER['id']);
	$Cache->cache_value('user_'.$CURUSER['id'].'_post_count', $forumposts, 3600);
}
$dayposts = 0;
if ($forumposts)
{
	$seconds3 = (TIMENOW - strtotime($CURUSER["added"]));
	$days = round($seconds3/86400, 0);
	if($days > 1) {
		$dayposts  = round(($forumposts / $days), 1);
	}
	if (!$postcount = $Cache->get_value('total_posts_count')){
		$postcount = \App\Repositories\UsercpRepository::getTotalPostCount();
		$Cache->cache_value('total_posts_count', $postcount, 96400);
	}
	$percentages = round($forumposts*100/$postcount, 3)."%";
}
?>
<table border="0" cellspacing="0" cellpadding="5" width=<?php echo CONTENT_WIDTH ?>>
<?php
\App\Support\Html::trSmall($lang_usercp['row_join_date'], $joindate, 1);
\App\Support\Html::trSmall($lang_usercp['row_email_address'], $CURUSER['email'], 1);
$seedBoxIcon = (new \App\Repositories\SeedBoxRepository())->renderIcon($CURUSER['ip'], $CURUSER['id']);
if ($enablelocation_tweak == 'yes'){
	list($loc_pub, $loc_mod) = \App\Support\Network::ipLocationWithContext($CURUSER["ip"]);
	\App\Support\Html::trSmall($lang_usercp['row_ip_location'], \App\Support\Strings::hidden($CURUSER["ip"]." <span title='" . $loc_mod . "'>[" . $loc_pub . "]</span>$seedBoxIcon"), 1);
}
else{
	\App\Support\Html::trSmall($lang_usercp['row_ip_location'], \App\Support\Strings::hidden($CURUSER["ip"] . $seedBoxIcon), 1);
}
if ($CURUSER["avatar"])
	\App\Support\Html::trSmall($lang_usercp['row_avatar'], "<img src=\"" . $CURUSER["avatar"] . "\" border=0>", 1);
\App\Support\Html::trSmall($lang_usercp['row_passkey'], \App\Support\Strings::hidden($CURUSER["passkey"]), 1);
$loginSecretDeadline = \App\Support\Config\SiteConfig::current()->security->loginSecretDeadline();
if (\App\Support\Config\SiteConfig::current()->security->loginType() === 'passkey' && $loginSecretDeadline !== null && $loginSecretDeadline > date('Y-m-d H:i:s')) {
    \App\Support\Html::trSmall($lang_usercp['row_passkey_login_url'], sprintf('%s/%s/%s', \App\Support\Url::schemeAndHost(false), \App\Support\Config\SiteConfig::current()->security->loginSecret(), $CURUSER['passkey']), 1);
}
\App\Support\Html::trSmall($lang_usercp['row_invitations'], $CURUSER['invites']." [<a href=\"invite.php?id=".$CURUSER['id']."\" title=\"".$lang_usercp['link_send_invitation']."\">".$lang_usercp['text_send']."</a>]", 1);
\App\Support\Html::trSmall($lang_usercp['row_karma_points'], $CURUSER['seedbonus']." [<a href=\"mybonus.php\" title=\"".$lang_usercp['link_use_karma_points']."\">".$lang_usercp['text_use']."</a>]", 1);
\App\Support\Html::trSmall($lang_usercp['row_written_comments'], $commentcount." [<a href=\"userhistory.php?action=viewcomments&id=".$CURUSER['id']."\" title=\"".$lang_usercp['link_view_comments']."\">".$lang_usercp['text_view']."</a>]", 1);

//start seed box
if (\App\Support\Config\SiteConfig::current()->seedBox->enabled()) {
    $seedBox = '';
    $columnOperator = \App\Support\Locale::trans('label.seed_box_record.operator', [], null);
    $columnBandwidth = \App\Support\Locale::trans('label.seed_box_record.bandwidth', [], null);
//    $columnIPBegin = nexus_trans('label.seed_box_record.ip_begin');
//    $columnIPEnd = nexus_trans('label.seed_box_record.ip_end');
    $columnIP = \App\Support\Locale::trans('label.seed_box_record.ip', [], null);
    $columnIPHelp = \App\Support\Locale::trans('label.seed_box_record.ip_help', [], null);
    $columnComment = \App\Support\Locale::trans('label.comment', [], null);
    $columnStatus = \App\Support\Locale::trans('label.seed_box_record.status', [], null);
    $res = \App\Repositories\UsercpRepository::getSeedBoxRecords((int) $CURUSER['id']);
    if ($res->count() > 0)
    {
        $seedBox .= "<table border='1' cellspacing='0' cellpadding='5' id='seed-box-table'><tr><td class='colhead'>ID</td><td class='colhead'>{$columnOperator}</td><td class='colhead'>{$columnBandwidth}</td><td class='colhead'>{$columnIP}</td><td class='colhead'>{$columnComment}</td><td class='colhead'>{$columnStatus}</td><td class='colhead'></td></tr>";
        foreach ($res as $seedBoxRecord)
        {
            $seedBox .= "<tr>";
            $seedBox .= sprintf('<td>%s</td>', $seedBoxRecord->id);
            $seedBox .= sprintf('<td>%s</td>', $seedBoxRecord->operator);
            $seedBox .= sprintf('<td>%s</td>', $seedBoxRecord->bandwidth ?: '');
            $seedBox .= sprintf('<td>%s</td>', $seedBoxRecord->ip ?: sprintf('%s ~ %s', $seedBoxRecord->ip_begin, $seedBoxRecord->ip_end));
            $seedBox .= sprintf('<td>%s</td>', $seedBoxRecord->comment);
            $seedBox .= sprintf('<td>%s</td>', $seedBoxRecord->statusText);
            $seedBox .= sprintf('<td><img style="cursor: pointer" class="staff_delete remove-seed-box-btn" src="pic/trans.gif" alt="D" title="%s" data-id="%s"></td>', $lang_functions['text_delete'], $seedBoxRecord->id);
            $seedBox .= "</tr>";
        }
        $seedBox .= '</table>';
    }
    $seedBox .= sprintf('<div><input type="button" id="add-seed-box-btn" value="%s"/></div>', $lang_usercp['add_seed_box_btn']);
    \App\Support\Html::trSmall($lang_usercp['row_seed_box'], $seedBox, 1);
    $seedBoxForm = <<<FORM
<div class="form-box">
<form id="seed-box-form">
    <div class="form-control-row">
        <div class="label">{$columnOperator}</div>
        <div class="field"><input type="text" name="params[operator]"></div>
    </div>
    <div class="form-control-row">
        <div class="label">{$columnBandwidth}</div>
        <div class="field"><input type="number" name="params[bandwidth]"></div>
    </div>
    <div class="form-control-row">
        <div class="label">{$columnIP}</div>
        <div class="field"><input type="text" name="params[ip]"></div>
    </div>
    <div class="form-control-row">
        <div class="label">{$columnComment}</div>
        <div class="field"><textarea name="params[comment]" rows="4"></textarea></div>
    </div>
</form>
</div>
FORM;
    $seedBoxJs = <<<JS
jQuery('#add-seed-box-btn').on('click', function () {
    layer.open({
        type: 1,
        title: "{$lang_usercp['row_seed_box']} {$lang_usercp['add_seed_box_btn']}",
        content: `$seedBoxForm`,
        btn: ['OK'],
        btnAlign: 'c',
        yes: function () {
            let params = jQuery('#seed-box-form').serialize()
            jQuery('body').loading({stoppable: false});
            jQuery.post('ajax.php', params + "&action=addSeedBoxRecord", function (response) {
                jQuery('body').loading('stop');
                if (response.ret != 0) {
                    layer.alert(response.msg)
                    return
                }
                window.location.reload()
            }, 'json')
        }
    })
});
jQuery('#seed-box-table').on('click', '.remove-seed-box-btn', function () {
    let params = {action: "removeSeedBoxRecord", params: {id: jQuery(this).attr("data-id")}}
    layer.confirm("{$lang_functions['std_confirm_remove']}", window.nexusLayerOptions.confirm, function (index) {
        jQuery('body').loading({stoppable: false});
        jQuery.post('ajax.php', params, function (response) {
            jQuery('body').loading('stop');
            if (response.ret != 0) {
                layer.alert(response.msg, window.nexusLayerOptions.alert)
                return
            }
            window.location.reload()
        }, 'json')
    })
});
JS;
    \Nexus\Nexus::js($seedBoxJs, 'footer', false);
}
//end seed box

//token start
$permissions = \App\Repositories\TokenRepository::listUserTokenPermissionAllowed();
$permissionOptions = [];
foreach ($permissions as $name => $label) {
    $permissionOptions[] = sprintf('<label><input type="checkbox" name="permissions[]" value="%s">%s</label>', $name, $label);
}
$permissionCheckbox = implode("", $permissionOptions);
$token = '';
$tokenLabel = \App\Support\Locale::trans("token.label", [], null);
$columnName = \App\Support\Locale::trans('label.name', [], null);
$columnPermission = \App\Support\Locale::trans('token.permission', [], null);
$columnCreatedAt = \App\Support\Locale::trans('label.created_at', [], null);
$actionCreate = \App\Support\Locale::trans('label.create', [], null);
$actionLabel = \App\Support\Locale::trans('label.action', [], null);
$res = $userInfo->tokens()->orderBy("id", "desc")->get();
if ($res->count() > 0)
{
    $token .= "<table border='1' cellspacing='0' cellpadding='5' id='token-table'><tr><td class='colhead'>ID</td><td class='colhead'>{$columnName}</td><td class='colhead'>{$columnPermission}</td><td class='colhead'>{$columnCreatedAt}</td><td class='colhead'>{$actionLabel}</td></tr>";
    foreach ($res as $tokenRecord)
    {
        $token .= "<tr>";
        $token .= sprintf('<td>%s</td>', $tokenRecord->id);
        $token .= sprintf('<td>%s</td>', $tokenRecord->name);
        $token .= sprintf('<td>%s</td>', $tokenRecord->abilitiesText);
        $token .= sprintf('<td>%s</td>', $tokenRecord->created_at);
        $token .= sprintf('<td><img style="cursor: pointer" class="staff_delete token-del" src="pic/trans.gif" alt="D" title="%s" data-id="%s"></td>', $lang_functions['text_delete'], $tokenRecord->id);
        $token .= "</tr>";
    }
    $token .= '</table>';
}
$token .= sprintf('<div><input type="button" id="add-token-box-btn" value="%s"/></div>', $actionCreate);
\App\Support\Html::trSmall($tokenLabel, $token, 1);
$tokenFoxForm = <<<FORM
<div class="form-box">
<form id="token-box-form">
    <div class="form-control-row">
        <div class="label">{$columnName}</div>
        <div class="field"><input type="text" name="name"></div>
    </div>
    <div class="form-control-row">
        <div class="label">{$columnPermission}</div>
        <div class="field">$permissionCheckbox</div>
    </div>
</form>
</div>
FORM;
    $tokenBoxJs = <<<JS
jQuery('#add-token-box-btn').on('click', function () {
    layer.open({
        type: 1,
        title: "{$tokenLabel} {$actionCreate}",
        content: `$tokenFoxForm`,
        btn: ['OK'],
        btnAlign: 'c',
        yes: function (index) {
            layer.close(index);
            jQuery('body').loading({stoppable: false});
            let params = jQuery('#token-box-form').serialize()
            jQuery.post('/web/token/add', params, function (response) {
                 jQuery('body').loading('stop');
                console.log(response)
                if (response.ret != 0) {
                    layer.alert(response.msg, window.nexusLayerOptions.alert)
                } else {
                    layer.alert(response.msg, window.nexusLayerOptions.alert, function(index) {
                        layer.close(index);
                        window.location.reload()
                    })
                }
            }, 'json')
        }
    })
});
jQuery('#token-table').on('click', '.token-del', function () {
    let params = {id: jQuery(this).attr("data-id")}
    layer.confirm("{$lang_functions['std_confirm_remove']}", window.nexusLayerOptions.confirm, function (index) {
        layer.close(index)
        jQuery('body').loading({stoppable: false});
        jQuery.post('/web/token/del', params, function (response) {
            console.log(response)
            if (response.ret != 0) {
                jQuery('body').loading('stop');
                layer.alert(response.msg, window.nexusLayerOptions.alert)
                return
            }
            window.location.reload()
        }, 'json')
    })
});
JS;
    \Nexus\Nexus::js($tokenBoxJs, 'footer', false);

//token end

if ($forumposts)
	\App\Support\Html::tr($lang_usercp['row_forum_posts'], $forumposts." [<a href=\"userhistory.php?action=viewposts&id=".$CURUSER['id']."\" title=\"".$lang_usercp['link_view_posts']."\">".$lang_usercp['text_view']."</a>] (".$dayposts.$lang_usercp['text_posts_per_day']."; ".$percentages.$lang_usercp['text_of_total_posts'].")", 1);
?>
</table>
<table border="0" cellspacing="0" cellpadding="5" width=<?php echo CONTENT_WIDTH ?>>
<?php
print("<td align=center class=tabletitle><b>".$lang_usercp['text_recently_read_topics']."</b></td>");
?>
</table>
<?php
print("<table border=0 cellspacing=0 cellpadding=3 width=".CONTENT_WIDTH."><tr>".
"<td class=colhead align=left width=80%>".$lang_usercp['col_topic_title']."</td>".
"<td class=colhead align=center><nobr>".$lang_usercp['col_replies']."/".$lang_usercp['col_views']."</nobr></td>".
"<td class=colhead align=center>".$lang_usercp['col_topic_starter']."</td>".
"<td class=colhead align=center width=20%>".$lang_usercp['col_last_post']."</td>".
"</tr>");
$topicRows = \App\Repositories\UsercpRepository::getReadTopics((int) $CURUSER['id']);
foreach ($topicRows as $topicarr)
{
	$topicid = $topicarr['id'];
	$topic_title = $topicarr['subject'];
	$topic_userid = $topicarr['userid'];
	$topic_views = $topicarr['views'];
	$views = number_format($topic_views);

	/// GETTING TOTAL NUMBER OF POSTS ///
	if (!$posts = $Cache->get_value('topic_'.$topicid.'_post_count')){
		$posts = \App\Repositories\UsercpRepository::getTopicPostCount($topicid);
		$Cache->cache_value('topic_'.$topicid.'_post_count', $posts, 3600);
	}
	$replies = max(0, $posts - 1);

	/// GETTING USERID AND DATE OF LAST POST ///
	$arr = \App\Support\Forum::postRowWithContext($topicarr['lastpost']);
	$postid = intval($arr["id"] ?? 0);
	$userid = intval($arr["userid"] ?? 0);
	$added = \App\Support\Time::format($arr['added'],true,false);

	/// GET NAME OF LAST POSTER ///
	$username = \App\Support\UserDisplay::username($userid);

	/// GET NAME OF THE AUTHOR ///
	$author = \App\Support\UserDisplay::username($topic_userid);
	$subject = "<a href=forums.php?action=viewtopic&topicid=$topicid><b>" . htmlspecialchars($topicarr['subject']) . "</b></a>";

	print("<tr class=tableb><td style='padding-left: 10px' align=left class=rowfollow>$subject</td>".
	"<td align=center class=rowfollow>".$replies."/".$views."</td>" .
	"<td align=center class=rowfollow>".$author."</td>" .
	"<td align=center class=rowfollow><nobr>".$added." | ".$username."</nobr></td></tr>");
}
?>
  </table>
</td>
</tr>
<?php

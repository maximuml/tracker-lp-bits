<?php
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING & ~E_DEPRECATED);
if (empty($nexus_legacy_layout)) { stdhead($lang_contactstaff['head_contact_staff'], false); }
if (empty($nexus_legacy_layout)) { begin_main_frame(); }
print("<form id=compose method=post name=\"compose\" action=takecontact.php>");
begin_compose($lang_contactstaff['text_message_to_staff'], "new");
end_compose();
print("</form>");
if (empty($nexus_legacy_layout)) { end_main_frame(); }
if (empty($nexus_legacy_layout)) { stdfoot(); }

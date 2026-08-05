<?php
extract($GLOBALS, EXTR_SKIP);
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING & ~E_DEPRECATED);
stdhead($lang_contactstaff['head_contact_staff'], false);
begin_main_frame();
print("<form id=compose method=post name=\"compose\" action=takecontact.php>");
begin_compose($lang_contactstaff['text_message_to_staff'], "new");
end_compose();
print("</form>");
end_main_frame();
stdfoot();

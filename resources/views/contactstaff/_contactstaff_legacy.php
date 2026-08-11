<?php
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING & ~E_DEPRECATED);
\App\Support\Html::stdhead($lang_contactstaff['head_contact_staff'], false);
\App\Support\Frame::mainFrameOpen();
print("<form id=compose method=post name=\"compose\" action=takecontact.php>");
\App\Support\Frame::composeBeginVoid($lang_contactstaff['text_message_to_staff'], "new");
\App\Support\Frame::composeEndVoid();
print("</form>");
\App\Support\Frame::mainFrameClose();
\App\Support\Html::stdfoot();

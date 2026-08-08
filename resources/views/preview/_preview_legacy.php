<?php
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING & ~E_DEPRECATED);

$body = \App\Support\SupportContext::getPost('body');
print ("<table width=100% border=1 cellspacing=0 cellpadding=10 align=left>\n");
print ("<tr><td align=left>".format_comment($body)."<br /><br /></td></tr></table>");

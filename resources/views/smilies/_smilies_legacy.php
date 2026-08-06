<?php
extract($context, EXTR_SKIP);
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING & ~E_DEPRECATED);

stdhead();
begin_main_frame();
insert_smilies_frame();
end_main_frame();
stdfoot();

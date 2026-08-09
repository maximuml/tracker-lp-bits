<?php
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING & ~E_DEPRECATED);

if (empty($nexus_legacy_layout)) { stdhead(); }
if (empty($nexus_legacy_layout)) { begin_main_frame(); }
insert_smilies_frame();
if (empty($nexus_legacy_layout)) { end_main_frame(); }
if (empty($nexus_legacy_layout)) { stdfoot(); }

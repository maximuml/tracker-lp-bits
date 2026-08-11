<?php
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING & ~E_DEPRECATED);

\App\Support\Html::stdhead();
\App\Support\Frame::mainFrameOpen();
\App\Support\Html::smiliesFrame();
\App\Support\Frame::mainFrameClose();
\App\Support\Html::stdfoot();

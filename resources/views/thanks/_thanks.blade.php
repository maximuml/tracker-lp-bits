<?php

$torrentid = (int) ($torrentid ?? 0);
$message = (string) ($message ?? '');

\App\Support\Html::stdhead('Thanks');
\App\Support\Html::stdMessage('Thanks', $message);
print("<p align='center'><a href='details.php?id=$torrentid'>Back to torrent</a></p>");
\App\Support\Html::stdfoot();

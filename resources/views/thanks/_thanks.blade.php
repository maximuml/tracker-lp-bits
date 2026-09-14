<?php

$torrentid = (int) ($torrentid ?? 0);
$message = (string) ($message ?? '');

echo \App\Support\Frame::stdMessage('Thanks', $message, false);
print("<p align='center'><a href='details.php?id=$torrentid'>Back to torrent</a></p>");

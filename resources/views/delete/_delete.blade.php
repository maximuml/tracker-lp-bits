<?php

$lang_delete = (array) \App\Support\SupportContext::getGlobal('lang_delete', []);
$message = (string) ($message ?? $lang_delete['text_torrent_deleted'] ?? 'Torrent deleted.');
$ret = (string) ($ret ?? '<a href="index.php">' . ($lang_delete['text_back_to_index'] ?? 'Back to index') . '</a>');

?>
<h1><?php echo $message ?></h1>
<p><?php echo $ret ?></p>
<?php

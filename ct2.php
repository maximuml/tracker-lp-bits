<?php

use Illuminate\Contracts\Console\Kernel;

require '/var/www/html/vendor/autoload.php';
$app = require '/var/www/html/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
$pdo = DB::connection()->getPdo();
foreach ($pdo->query("SHOW VARIABLES WHERE Variable_name IN ('character_set_client','character_set_connection','character_set_results')") as $r) {
    echo $r['Variable_name'].'='.$r['Value']."\n";
}
$s = DB::table('torrents')->where('id', 2)->value('small_descr');
echo 'as-is: '.substr($s, 0, 40)."\n";
echo 'hex: '.bin2hex(substr($s, 0, 6))."\n";
$raw = $pdo->query('SELECT HEX(small_descr) FROM torrents WHERE id=2')->fetchColumn();
echo 'db-hex: '.substr($raw, 0, 24)."\n";

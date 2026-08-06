<?php
$nexusId = (int) ($_GET['id'] ?? 0);
unset($_GET['id']);
$nexusRoute = '/details/' . $nexusId;
require __DIR__ . '/nexus.php';

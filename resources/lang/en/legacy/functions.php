<?php

// Root-copy alias while app/Services call sites still reference legacy/*.
return require dirname(__DIR__).'/functions.php';

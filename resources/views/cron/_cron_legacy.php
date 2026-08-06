<?php
extract($GLOBALS, EXTR_SKIP);
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING & ~E_DEPRECATED);

if ($useCronTriggerCleanUp) {
	$return = autoclean();
	if ($return) {
		echo $return."\n";
	} else {
		echo "Clean-up not triggered.\n";
	}
} else {
	echo "Forbidden. Clean-up is set to be browser-triggered.\n";
}

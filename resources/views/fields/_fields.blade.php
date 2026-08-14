<?php
$mode = $mode ?? 'view';
$field = new \Nexus\Field\Field();
if ($mode === 'add') {
    echo $field->buildFieldForm();
} elseif ($mode === 'edit') {
    echo $field->buildFieldForm($row ?? []);
} else {
    echo $fieldTable ?? '';
}
?>

<?php
$mode = $mode ?? 'view';
$field = new \App\Support\CustomField();
if ($mode === 'add') {
    echo $field->buildFieldForm();
} elseif ($mode === 'edit') {
    echo $field->buildFieldForm($row ?? []);
} else {
    echo $fieldTable ?? '';
}
?>

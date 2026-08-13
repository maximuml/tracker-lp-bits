<?php
$field = new \Nexus\Field\Field();
if ($mode === 'add') {
    \App\Support\Html::stdhead(($lang_fields['field_management'] ?? 'Field management') . " - " . ($lang_fields['text_add'] ?? 'Add'));
    \App\Support\Frame::mainFrameOpen();
    echo $field->buildFieldForm();
} elseif ($mode === 'edit') {
    \App\Support\Html::stdhead(($lang_fields['field_management'] ?? 'Field management') . " - " . ($lang_fields['text_edit'] ?? 'Edit'));
    \App\Support\Frame::mainFrameOpen();
    echo $field->buildFieldForm($row ?? []);
} else {
    echo $fieldTable ?? '';
}
\App\Support\Frame::mainFrameClose();
\App\Support\Html::stdfoot();
?>

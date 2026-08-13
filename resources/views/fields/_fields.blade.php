<?php
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING & ~E_DEPRECATED);
require_once(\App\Support\Locale::scriptFilePath((string) 'catmanage.php', (bool) false, (string) ""));
if (\App\Support\UserDisplay::currentClass() < UC_ADMINISTRATOR) {
    \App\Support\LegacyResponse::permissionDenied();
}
$field = new \Nexus\Field\Field();


$action = \App\Support\SupportContext::getQuery('action') ?? 'view';
if ($action == 'view') {
    \App\Support\Html::stdhead($lang_fields['field_management']." - ".$lang_fields['text_field']);
    \App\Support\Frame::mainFrameOpen();
    $r =  $field->buildFieldTable();
    echo $r;
    \App\Support\Html::stdfoot();
} elseif ($action == 'add') {
    \App\Support\Html::stdhead($lang_fields['field_management']." - ".$lang_fields['text_add']);
    \App\Support\Frame::mainFrameOpen();
    echo $field->buildFieldForm();
} elseif ($action == 'submit') {
    echo "This method is deprecated! This method is no longer available in 1.10, it does not save data correctly, please go to the management system!"; return;
    try {
        $result = $field->save(\App\Support\SupportContext::allRequest());
        \App\Support\LegacyResponse::redirect('fields.php?action=view');
    } catch (\Exception $e) {
        \App\Support\LegacyResponse::abort($lang_fields['field_management'], $e->getMessage());
    }
} elseif ($action == 'edit') {
    $id = intval(\App\Support\SupportContext::getQuery('id') ?? 0);
    if ($id == 0) {
        \App\Support\LegacyResponse::abort($lang_fields['field_management'], "Invalid id");
    }
    $row = (array) \Nexus\Database\NexusDB::table('torrents_custom_fields')->where('id', $id)->first();
    if (empty($row)) {
        \App\Support\LegacyResponse::abort('', 'Invalid id');
    }
    \App\Support\Html::stdhead($lang_fields['field_management']." - ".$lang_fields['text_edit']);
    \App\Support\Frame::mainFrameOpen();
    echo $field->buildFieldForm($row);
} elseif ($action == 'del') {
    $id = intval(\App\Support\SupportContext::getQuery('id') ?? 0);
    if ($id == 0) {
        \App\Support\LegacyResponse::abort($lang_fields['field_management'], "Invalid id");
    }
    \Nexus\Database\NexusDB::table('torrents_custom_fields')->where('id', $id)->delete();
    \App\Support\LegacyResponse::redirect('fields.php?action=view');
}

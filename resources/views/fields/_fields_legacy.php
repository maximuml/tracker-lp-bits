<?php
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING & ~E_DEPRECATED);
require_once(get_langfile_path('catmanage.php'));
if (get_user_class() < UC_ADMINISTRATOR) {
    permissiondenied();
}
$field = new \Nexus\Field\Field();


$action = \App\Support\SupportContext::getQuery('action') ?? 'view';
if ($action == 'view') {
    stdhead($lang_fields['field_management']." - ".$lang_fields['text_field']);
    begin_main_frame();
    $r =  $field->buildFieldTable();
    echo $r;
    stdfoot();
} elseif ($action == 'add') {
    stdhead($lang_fields['field_management']." - ".$lang_fields['text_add']);
    begin_main_frame();
    echo $field->buildFieldForm();
} elseif ($action == 'submit') {
    echo "This method is deprecated! This method is no longer available in 1.10, it does not save data correctly, please go to the management system!"; return;
    try {
        $result = $field->save(\App\Support\SupportContext::allRequest());
        nexus_redirect('fields.php?action=view');
    } catch (\Exception $e) {
        stderr($lang_fields['field_management'], $e->getMessage());
    }
} elseif ($action == 'edit') {
    $id = intval(\App\Support\SupportContext::getQuery('id') ?? 0);
    if ($id == 0) {
        stderr($lang_fields['field_management'], "Invalid id");
    }
    $row = (array) \Nexus\Database\NexusDB::table('torrents_custom_fields')->where('id', $id)->first();
    if (empty($row)) {
        stderr('', 'Invalid id');
    }
    stdhead($lang_fields['field_management']." - ".$lang_fields['text_edit']);
    begin_main_frame();
    echo $field->buildFieldForm($row);
} elseif ($action == 'del') {
    $id = intval(\App\Support\SupportContext::getQuery('id') ?? 0);
    if ($id == 0) {
        stderr($lang_fields['field_management'], "Invalid id");
    }
    \Nexus\Database\NexusDB::table('torrents_custom_fields')->where('id', $id)->delete();
    nexus_redirect('fields.php?action=view');
}

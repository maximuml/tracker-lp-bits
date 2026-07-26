<?php

return [
    'actions' => [
        'install' => 'install',
        'delete' => 'delete',
        'update' => 'upgrade',
    ],
    'labels' => [
        'package_name' => 'package_name',
        'remote_url' => 'repository_address',
        'installed_version' => 'installed_version',
        'updated_at' => 'last_executed_action',
    ],
    'status' => [
        \App\Models\Plugin::STATUS_NORMAL => 'Normal',
        \App\Models\Plugin::STATUS_NOT_INSTALLED => 'Not installed',

        \App\Models\Plugin::STATUS_PRE_INSTALL => 'Ready to install',
        \App\Models\Plugin::STATUS_INSTALLING => 'Installing',
        \App\Models\Plugin::STATUS_INSTALL_FAILED => 'Install fail',

        \App\Models\Plugin::STATUS_PRE_UPDATE => 'Ready to upgrade',
        \App\Models\Plugin::STATUS_UPDATING => 'Upgrading',
        \App\Models\Plugin::STATUS_UPDATE_FAILED => 'Upgrade fail',

        \App\Models\Plugin::STATUS_PRE_DELETE => 'Ready to remove',
        \App\Models\Plugin::STATUS_DELETING => 'Removing',
        \App\Models\Plugin::STATUS_DELETE_FAILED => 'Remove fail',
    ],
];

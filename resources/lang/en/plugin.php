<?php

use App\Models\Plugin;

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
        Plugin::STATUS_NORMAL => 'Normal',
        Plugin::STATUS_NOT_INSTALLED => 'Not installed',

        Plugin::STATUS_PRE_INSTALL => 'Ready to install',
        Plugin::STATUS_INSTALLING => 'Installing',
        Plugin::STATUS_INSTALL_FAILED => 'Install fail',

        Plugin::STATUS_PRE_UPDATE => 'Ready to upgrade',
        Plugin::STATUS_UPDATING => 'Upgrading',
        Plugin::STATUS_UPDATE_FAILED => 'Upgrade fail',

        Plugin::STATUS_PRE_DELETE => 'Ready to remove',
        Plugin::STATUS_DELETING => 'Removing',
        Plugin::STATUS_DELETE_FAILED => 'Remove fail',
    ],
];

<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Backup GPG Encryption
    |--------------------------------------------------------------------------
    |
    | GPG recipient (email or key ID) for encrypting backup archives
    | before transfer to remote storage. When empty, backups are
    | stored unencrypted (not recommended for production).
    |
    */

    'gpg_recipient' => env('BACKUP_GPG_RECIPIENT', ''),

];

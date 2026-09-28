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

    /*
    |--------------------------------------------------------------------------
    | Google Drive Upload
    |--------------------------------------------------------------------------
    |
    | Service-account JSON key path and optional Drive folder ID used when
    | the "backup.via_gdrive" setting is enabled. The service account needs
    | the drive.file scope; share the target folder with its client_email.
    |
    */

    'gdrive_credentials' => env('GDRIVE_CREDENTIALS', ''),

    'gdrive_folder_id' => env('GDRIVE_FOLDER_ID', ''),

];

<?php

declare(strict_types=1);

namespace App\Exceptions;

/**
 * Upload-domain validation failure that maps to a specific form field.
 *
 * The web upload flow converts these into a ValidationException so the user
 * lands back on /upload with the entered data and a per-field error instead
 * of the generic /error redirect. The field name matches the form input
 * name (file, name, descr, type, price, hr, pos_state, uplver, tags, ...);
 * null means the failure is not tied to a single field (permissions,
 * upload bans) and is shown in the form-level summary only.
 */
class UploadValidationException extends NexusException
{
    public function __construct(string $message, private readonly ?string $field = null)
    {
        parent::__construct($message);
    }

    public function field(): ?string
    {
        return $this->field;
    }
}

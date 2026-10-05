<?php

declare(strict_types=1);

namespace App\Services\Ajax;

use App\Support\NotificationFeed;

/**
 * Parameter object bundling the feature-service dependencies of AjaxService
 * — keeps its constructor within the RepositorySizeTest dependency cap.
 */
final readonly class AjaxFeatureServices
{
    public function __construct(
        public PasskeyActions $passkeyActions,
        public NotificationFeed $notificationFeed,
    ) {}
}

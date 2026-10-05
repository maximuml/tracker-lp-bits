<?php

declare(strict_types=1);

namespace App\Services;

use App\Services\Ajax\AjaxFeatureServices;
use App\Services\Ajax\PasskeyActions;
use App\Services\Ajax\ShoutboxActions;

final class AjaxService
{
    /**
     * Explicit whitelist of actions that may be dispatched via the /ajax endpoint.
     *
     * The controller checks `in_array($action, self::ALLOWED_ACTIONS)` before
     * calling dispatch(). Handler groups expose their action names through
     * their own ACTIONS constants; every remaining entry is a private method
     * on this class, so no public method besides dispatch() can ever become
     * an AJAX endpoint.
     *
     * Actions migrated to REST endpoints 308-redirect before reaching this
     * list (see LegacyAjaxRedirects), so only the still-dispatched groups
     * remain here.
     *
     * @var array<int, string>
     */
    public const ALLOWED_ACTIONS = [
        ...ShoutboxActions::ACTIONS,
        ...PasskeyActions::ACTIONS,
    ];

    public function __construct(
        private readonly AjaxFeatureServices $features,
    ) {}

    /** @param array<string, mixed> $params */
    public function dispatch(string $action, array $params): mixed
    {
        return match (true) {
            in_array($action, ShoutboxActions::ACTIONS, true) => $this->features->shoutboxActions->{$action}($params),
            in_array($action, PasskeyActions::ACTIONS, true) => $this->features->passkeyActions->{$action}($params),
            default => throw new \InvalidArgumentException("Unknown ajax action: {$action}"),
        };
    }
}

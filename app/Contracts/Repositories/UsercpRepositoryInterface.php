<?php

declare(strict_types=1);

namespace App\Contracts\Repositories;

use App\DTOs\Usercp\ForumSettingsDto;
use App\DTOs\Usercp\PersonalSettingsDto;
use App\DTOs\Usercp\SecuritySettingsDto;
use App\DTOs\Usercp\TrackerSettingsDto;
use App\Models\User;
use Illuminate\Http\Request;

interface UsercpRepositoryInterface
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public function getUserTokens(User $user): array;

    /**
     * @param  array<string, mixed>  $data
     */
    public function updateUser(int $userId, array $data): bool;

    public function updateLastOffer(int $userId): bool;

    public function emailExistsForOther(string $email, int $userId): bool;

    public function getChallenge(string $username): ?string;

    public function deleteChallenge(string $username): bool;

    /**
     * @param  array<string, mixed>  $data
     */
    public function updateSecurity(int $userId, array $data, bool $resetAuthKey): bool;

    /**
     * @return array<int, int>
     */
    public function getTableIds(string $table): array;

    /**
     * @return array<string, mixed>
     */
    public function settings(): array;

    /**
     * Update personal settings for the authenticated user.
     *
     * @return array<string, mixed>
     */
    public function updatePersonal(PersonalSettingsDto $dto): array;

    /**
     * Update forum settings for the authenticated user.
     *
     * @return array<string, mixed>
     */
    public function updateForum(ForumSettingsDto $dto): array;

    /**
     * Update tracker/browse settings for the authenticated user.
     *
     * @return array<string, mixed>
     */
    public function updateTracker(TrackerSettingsDto $dto): array;

    /**
     * Process the legacy usercp security "confirm" form and return the redirect URL.
     */
    public function updateSecurityFromLegacyRequest(Request $request): string;

    /**
     * Update security settings for the authenticated user via API.
     *
     * @return array<string, mixed>
     */
    public function updateSecurityApi(SecuritySettingsDto $dto): array;
}

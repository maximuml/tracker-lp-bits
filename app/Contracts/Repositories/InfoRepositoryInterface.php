<?php

declare(strict_types=1);

namespace App\Contracts\Repositories;

interface InfoRepositoryInterface
{
    /**
     * @return array<string, mixed>
     */
    public function aboutNexus(): array;

    public function resolveRuleLangId(int $langId): int;

    /**
     * @return array<int|string, array<string, mixed>>
     */
    public function faqCategories(int $langId): array;

    /**
     * @return array<int, array<string, mixed>>
     */
    public function rules(int $langId): array;

    /**
     * @return array<string, mixed>
     */
    public function donationPageData(): array;

    /**
     * @return array<string, mixed>
     */
    public function getUserHistoryPosts(int $userId, int $userClass, int $perpage, string $phpSelf): array;

    /**
     * @return array<string, mixed>
     */
    public function getUserHistoryComments(int $userId, int $perpage, string $phpSelf): array;

    /**
     * @return array{faqCateg: array<string, mixed>, faqOrphaned: array<string, mixed>}
     */
    public function faqManageData(): array;

    /**
     * @param  array<int|string, int>  $order
     */
    public function reorderFaq(array $order): void;

    /** @param  array<string, mixed>  $data */
    public function updateFaq(int $id, array $data): void;

    public function deleteFaq(int $id): void;

    /**
     * @return array<string, mixed>|null
     */
    public function getFaqById(int $id): ?array;

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getFaqCategoriesByLang(int $langId): array;

    public function getLanguageName(int $langId): string;

    /**
     * @return array{maxorder: int, maxlinkid: int}
     */
    public function getFaqMaxOrderAndLinkId(string $type, int $langId): array;

    /** @param  array<string, mixed>  $data */
    public function insertFaq(array $data): void;
}

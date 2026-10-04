<?php

declare(strict_types=1);

namespace App\ViewModels\Chrome;

use App\Contracts\Repositories\SearchBoxRepositoryInterface;
use App\Repositories\AttendanceRepository;
use App\Repositories\HitAndRunRepository;
use App\Repositories\StaffMessageRepository;
use App\Services\PermissionChecker;

/**
 * Repositories the chrome view models need beyond the layout repository the
 * callers already carry (ADR 0018). Passed as one parameter object so the
 * `*::load()` signatures stay narrow while every dependency still arrives
 * via container injection at the composition root (SiteChromeComposer /
 * PageRenderer).
 */
final readonly class ChromeRepositories
{
    public function __construct(
        public AttendanceRepository $attendance,
        public HitAndRunRepository $hitAndRun,
        public StaffMessageRepository $staffMessages,
        public SearchBoxRepositoryInterface $searchBox,
        public PermissionChecker $permissionChecker,
    ) {}
}

<?php

declare(strict_types=1);

namespace App\Services\Cleanup;

use App\Contracts\Repositories\SiteLogRepositoryInterface;
use App\Repositories\AgentAllowRepository;
use App\Repositories\LogRepository;
use App\Repositories\MessageRepository;
use App\Repositories\PostRepository;
use App\Repositories\TopicMaintenanceRepository;
use App\Repositories\UserCleanupRepository;

/**
 * Parameter object bundling the data-access dependencies of the periodic
 * (class-5) housekeeping task — keeps the task's constructor within the
 * RepositorySizeTest dependency cap.
 */
final readonly class HousekeepingRepositories
{
    public function __construct(
        public AgentAllowRepository $agentAllow,
        public UserCleanupRepository $userCleanup,
        public MessageRepository $messages,
        public PostRepository $posts,
        public TopicMaintenanceRepository $topics,
        public LogRepository $logs,
        public SiteLogRepositoryInterface $siteLogs,
    ) {}
}

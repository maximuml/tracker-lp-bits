<?php

declare(strict_types=1);

namespace App\DTOs\Announce;

use App\DTOs\AnnounceRequestDto;
use App\Services\Announce\ResponseBuilder;
use App\Services\Announce\TrafficResult;

/**
 * Immutable context object passed through the announce pipeline.
 *
 * Replaces the 20+ mutable private fields on AnnounceService with a single
 * typed, read-only value object. Each pipeline step that needs to enrich
 * the context returns a new instance via the `with*()` methods, following
 * the same pattern as ResponseBuilder::withTorrent().
 *
 * This eliminates cross-request state leakage under Octane/RoadRunner
 * because no mutable fields persist on the service between requests.
 */
final readonly class AnnounceContext
{
    /**
     * @param  array<string, mixed>  $params  Raw announce query params.
     * @param  array<string, mixed>  $user  Authenticated user row.
     * @param  array<string, mixed>|null  $torrent  Torrent row or null if not found.
     * @param  array<string, mixed>|null  $self  Existing peer row for this user+torrent.
     * @param  array<string, mixed>|false  $snatchInfo  Snatch row or false if none.
     * @param  array<string, mixed>  $userUpdate  Columns to update on the user row.
     * @param  array<string, mixed>  $torrentUpdate  Columns to update on the torrent row.
     */
    public function __construct(
        public AnnounceRequestDto $dto,
        public array $params,
        public string $ip,
        public string $agent,
        public string $dt,
        public int $seeder,
        public bool $isDonor,
        public bool $isReAnnounce,
        public int $clientFamilyId,
        public int $announceWait,
        public int $autocleanIntervalOne,
        public ResponseBuilder $responseBuilder,
        public array $user = [],
        public ?array $torrent = null,
        public ?array $self = null,
        public array|false $snatchInfo = false,
        public array $userUpdate = [],
        public array $torrentUpdate = [],
        public int $uploadedIncrementForUser = 0,
        public int $downloadedIncrementForUser = 0,
        public ?TrafficResult $traffic = null,
    ) {}

    public function userId(): int
    {
        return (int) ($this->user['id'] ?? 0);
    }

    public function torrentId(): int
    {
        return (int) ($this->torrent['id'] ?? 0);
    }

    public function peerIdBinary(): string
    {
        return $this->dto->peerId->toBinary();
    }

    public function infoHashBinary(): string
    {
        return $this->dto->infoHash->toBinary();
    }

    public function isSeeder(): bool
    {
        return $this->seeder === 1;
    }

    /** @param  array<string, mixed>  $user */
    public function withUser(array $user): self
    {
        return new self(
            dto: $this->dto,
            params: $this->params,
            ip: $this->ip,
            agent: $this->agent,
            dt: $this->dt,
            seeder: $this->seeder,
            isDonor: $this->isDonor,
            isReAnnounce: $this->isReAnnounce,
            clientFamilyId: $this->clientFamilyId,
            announceWait: $this->announceWait,
            autocleanIntervalOne: $this->autocleanIntervalOne,
            user: $user,
            torrent: $this->torrent,
            self: $this->self,
            snatchInfo: $this->snatchInfo,
            userUpdate: $this->userUpdate,
            torrentUpdate: $this->torrentUpdate,
            uploadedIncrementForUser: $this->uploadedIncrementForUser,
            downloadedIncrementForUser: $this->downloadedIncrementForUser,
            responseBuilder: $this->responseBuilder,
            traffic: $this->traffic,
        );
    }

    /** @param  array<string, mixed>  $torrent */
    public function withTorrent(array $torrent): self
    {
        return new self(
            dto: $this->dto,
            params: $this->params,
            ip: $this->ip,
            agent: $this->agent,
            dt: $this->dt,
            seeder: $this->seeder,
            isDonor: $this->isDonor,
            isReAnnounce: $this->isReAnnounce,
            clientFamilyId: $this->clientFamilyId,
            announceWait: $this->announceWait,
            autocleanIntervalOne: $this->autocleanIntervalOne,
            user: $this->user,
            torrent: $torrent,
            self: $this->self,
            snatchInfo: $this->snatchInfo,
            userUpdate: $this->userUpdate,
            torrentUpdate: $this->torrentUpdate,
            uploadedIncrementForUser: $this->uploadedIncrementForUser,
            downloadedIncrementForUser: $this->downloadedIncrementForUser,
            responseBuilder: $this->responseBuilder,
            traffic: $this->traffic,
        );
    }

    public function withResponseBuilder(ResponseBuilder $responseBuilder): self
    {
        return new self(
            dto: $this->dto,
            params: $this->params,
            ip: $this->ip,
            agent: $this->agent,
            dt: $this->dt,
            seeder: $this->seeder,
            isDonor: $this->isDonor,
            isReAnnounce: $this->isReAnnounce,
            clientFamilyId: $this->clientFamilyId,
            announceWait: $this->announceWait,
            autocleanIntervalOne: $this->autocleanIntervalOne,
            user: $this->user,
            torrent: $this->torrent,
            self: $this->self,
            snatchInfo: $this->snatchInfo,
            userUpdate: $this->userUpdate,
            torrentUpdate: $this->torrentUpdate,
            uploadedIncrementForUser: $this->uploadedIncrementForUser,
            downloadedIncrementForUser: $this->downloadedIncrementForUser,
            responseBuilder: $responseBuilder,
            traffic: $this->traffic,
        );
    }

    public function withIsDonor(bool $isDonor): self
    {
        return new self(
            dto: $this->dto,
            params: $this->params,
            ip: $this->ip,
            agent: $this->agent,
            dt: $this->dt,
            seeder: $this->seeder,
            isDonor: $isDonor,
            isReAnnounce: $this->isReAnnounce,
            clientFamilyId: $this->clientFamilyId,
            announceWait: $this->announceWait,
            autocleanIntervalOne: $this->autocleanIntervalOne,
            user: $this->user,
            torrent: $this->torrent,
            self: $this->self,
            snatchInfo: $this->snatchInfo,
            userUpdate: $this->userUpdate,
            torrentUpdate: $this->torrentUpdate,
            uploadedIncrementForUser: $this->uploadedIncrementForUser,
            downloadedIncrementForUser: $this->downloadedIncrementForUser,
            responseBuilder: $this->responseBuilder,
            traffic: $this->traffic,
        );
    }

    public function withIsReAnnounce(bool $isReAnnounce): self
    {
        return new self(
            dto: $this->dto,
            params: $this->params,
            ip: $this->ip,
            agent: $this->agent,
            dt: $this->dt,
            seeder: $this->seeder,
            isDonor: $this->isDonor,
            isReAnnounce: $isReAnnounce,
            clientFamilyId: $this->clientFamilyId,
            announceWait: $this->announceWait,
            autocleanIntervalOne: $this->autocleanIntervalOne,
            user: $this->user,
            torrent: $this->torrent,
            self: $this->self,
            snatchInfo: $this->snatchInfo,
            userUpdate: $this->userUpdate,
            torrentUpdate: $this->torrentUpdate,
            uploadedIncrementForUser: $this->uploadedIncrementForUser,
            downloadedIncrementForUser: $this->downloadedIncrementForUser,
            responseBuilder: $this->responseBuilder,
            traffic: $this->traffic,
        );
    }

    public function withClientFamilyId(int $clientFamilyId): self
    {
        return new self(
            dto: $this->dto,
            params: $this->params,
            ip: $this->ip,
            agent: $this->agent,
            dt: $this->dt,
            seeder: $this->seeder,
            isDonor: $this->isDonor,
            isReAnnounce: $this->isReAnnounce,
            clientFamilyId: $clientFamilyId,
            announceWait: $this->announceWait,
            autocleanIntervalOne: $this->autocleanIntervalOne,
            user: $this->user,
            torrent: $this->torrent,
            self: $this->self,
            snatchInfo: $this->snatchInfo,
            userUpdate: $this->userUpdate,
            torrentUpdate: $this->torrentUpdate,
            uploadedIncrementForUser: $this->uploadedIncrementForUser,
            downloadedIncrementForUser: $this->downloadedIncrementForUser,
            responseBuilder: $this->responseBuilder,
            traffic: $this->traffic,
        );
    }

    /** @param  array<string, mixed>|null  $self */
    public function withSelf(?array $self): self
    {
        return new self(
            dto: $this->dto,
            params: $this->params,
            ip: $this->ip,
            agent: $this->agent,
            dt: $this->dt,
            seeder: $this->seeder,
            isDonor: $this->isDonor,
            isReAnnounce: $this->isReAnnounce,
            clientFamilyId: $this->clientFamilyId,
            announceWait: $this->announceWait,
            autocleanIntervalOne: $this->autocleanIntervalOne,
            user: $this->user,
            torrent: $this->torrent,
            self: $self,
            snatchInfo: $this->snatchInfo,
            userUpdate: $this->userUpdate,
            torrentUpdate: $this->torrentUpdate,
            uploadedIncrementForUser: $this->uploadedIncrementForUser,
            downloadedIncrementForUser: $this->downloadedIncrementForUser,
            responseBuilder: $this->responseBuilder,
            traffic: $this->traffic,
        );
    }

    /** @param  array<string, mixed>|false  $snatchInfo */
    public function withSnatchInfo(array|false $snatchInfo): self
    {
        return new self(
            dto: $this->dto,
            params: $this->params,
            ip: $this->ip,
            agent: $this->agent,
            dt: $this->dt,
            seeder: $this->seeder,
            isDonor: $this->isDonor,
            isReAnnounce: $this->isReAnnounce,
            clientFamilyId: $this->clientFamilyId,
            announceWait: $this->announceWait,
            autocleanIntervalOne: $this->autocleanIntervalOne,
            user: $this->user,
            torrent: $this->torrent,
            self: $this->self,
            snatchInfo: $snatchInfo,
            userUpdate: $this->userUpdate,
            torrentUpdate: $this->torrentUpdate,
            uploadedIncrementForUser: $this->uploadedIncrementForUser,
            downloadedIncrementForUser: $this->downloadedIncrementForUser,
            responseBuilder: $this->responseBuilder,
            traffic: $this->traffic,
        );
    }

    /** @param  array<string, mixed>  $userUpdate */
    public function withUserUpdate(array $userUpdate): self
    {
        return new self(
            dto: $this->dto,
            params: $this->params,
            ip: $this->ip,
            agent: $this->agent,
            dt: $this->dt,
            seeder: $this->seeder,
            isDonor: $this->isDonor,
            isReAnnounce: $this->isReAnnounce,
            clientFamilyId: $this->clientFamilyId,
            announceWait: $this->announceWait,
            autocleanIntervalOne: $this->autocleanIntervalOne,
            user: $this->user,
            torrent: $this->torrent,
            self: $this->self,
            snatchInfo: $this->snatchInfo,
            userUpdate: $userUpdate,
            torrentUpdate: $this->torrentUpdate,
            uploadedIncrementForUser: $this->uploadedIncrementForUser,
            downloadedIncrementForUser: $this->downloadedIncrementForUser,
            responseBuilder: $this->responseBuilder,
            traffic: $this->traffic,
        );
    }

    /** @param  array<string, mixed>  $torrentUpdate */
    public function withTorrentUpdate(array $torrentUpdate): self
    {
        return new self(
            dto: $this->dto,
            params: $this->params,
            ip: $this->ip,
            agent: $this->agent,
            dt: $this->dt,
            seeder: $this->seeder,
            isDonor: $this->isDonor,
            isReAnnounce: $this->isReAnnounce,
            clientFamilyId: $this->clientFamilyId,
            announceWait: $this->announceWait,
            autocleanIntervalOne: $this->autocleanIntervalOne,
            user: $this->user,
            torrent: $this->torrent,
            self: $this->self,
            snatchInfo: $this->snatchInfo,
            userUpdate: $this->userUpdate,
            torrentUpdate: $torrentUpdate,
            uploadedIncrementForUser: $this->uploadedIncrementForUser,
            downloadedIncrementForUser: $this->downloadedIncrementForUser,
            responseBuilder: $this->responseBuilder,
            traffic: $this->traffic,
        );
    }

    public function withTraffic(TrafficResult $traffic): self
    {
        return new self(
            dto: $this->dto,
            params: $this->params,
            ip: $this->ip,
            agent: $this->agent,
            dt: $this->dt,
            seeder: $this->seeder,
            isDonor: $this->isDonor,
            isReAnnounce: $this->isReAnnounce,
            clientFamilyId: $this->clientFamilyId,
            announceWait: $this->announceWait,
            autocleanIntervalOne: $this->autocleanIntervalOne,
            user: $this->user,
            torrent: $this->torrent,
            self: $this->self,
            snatchInfo: $this->snatchInfo,
            userUpdate: $this->userUpdate,
            torrentUpdate: $this->torrentUpdate,
            uploadedIncrementForUser: $traffic->uploadedIncrementForUser,
            downloadedIncrementForUser: $traffic->downloadedIncrementForUser,
            responseBuilder: $this->responseBuilder,
            traffic: $traffic,
        );
    }

    public function withAutocleanIntervalOne(int $autocleanIntervalOne): self
    {
        return new self(
            dto: $this->dto,
            params: $this->params,
            ip: $this->ip,
            agent: $this->agent,
            dt: $this->dt,
            seeder: $this->seeder,
            isDonor: $this->isDonor,
            isReAnnounce: $this->isReAnnounce,
            clientFamilyId: $this->clientFamilyId,
            announceWait: $this->announceWait,
            autocleanIntervalOne: $autocleanIntervalOne,
            user: $this->user,
            torrent: $this->torrent,
            self: $this->self,
            snatchInfo: $this->snatchInfo,
            userUpdate: $this->userUpdate,
            torrentUpdate: $this->torrentUpdate,
            uploadedIncrementForUser: $this->uploadedIncrementForUser,
            downloadedIncrementForUser: $this->downloadedIncrementForUser,
            responseBuilder: $this->responseBuilder,
            traffic: $this->traffic,
        );
    }
}

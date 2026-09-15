<?php

declare(strict_types=1);

namespace App\Services\Ajax;

use App\Repositories\BonusRepository;
use App\Repositories\MedalRepository;
use App\Support\CurrentUser;

final class MedalActions
{
    /** @var array<int, string> */
    public const ACTIONS = [
        'toggleUserMedalStatus',
        'buyMedal',
        'giftMedal',
        'saveUserMedal',
    ];

    public function __construct(
        private readonly MedalRepository $medalRepository,
        private readonly BonusRepository $bonusRepository,
        private readonly CurrentUser $currentUser,
    ) {}

    /** @param array<string, mixed> $params */
    public function toggleUserMedalStatus(array $params): mixed
    {
        $CURUSER = $this->currentUser->get() ?? [];
        $rep = $this->medalRepository;

        return $rep->toggleUserMedalStatus($params['id'], $CURUSER['id']);
    }

    /** @param array<string, mixed> $params */
    public function buyMedal(array $params): mixed
    {
        $CURUSER = $this->currentUser->get() ?? [];
        $rep = $this->bonusRepository;

        return $rep->consumeToBuyMedal($CURUSER['id'], $params['medal_id']);
    }

    /** @param array<string, mixed> $params */
    public function giftMedal(array $params): mixed
    {
        $CURUSER = $this->currentUser->get() ?? [];
        $rep = $this->bonusRepository;

        return $rep->consumeToGiftMedal($CURUSER['id'], $params['medal_id'], $params['uid']);
    }

    /** @param array<string, mixed> $params */
    public function saveUserMedal(array $params): mixed
    {
        $CURUSER = $this->currentUser->get() ?? [];
        $data = [];
        foreach ($params as $param) {
            if (! is_array($param) || ! isset($param['name'], $param['value'])) {
                continue;
            }
            $fieldAndId = explode('_', $param['name']);
            if (count($fieldAndId) < 2) {
                continue;
            }
            $field = $fieldAndId[0];
            $id = $fieldAndId[1];
            $value = $param['value'];
            $data[$id][$field] = $value;
        }
        $rep = $this->medalRepository;

        return $rep->saveUserMedal($CURUSER['id'], $data);
    }
}

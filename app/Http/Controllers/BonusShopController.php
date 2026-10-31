<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\FreeleechRequest;
use App\Support\Cache\NexusCache;
use App\Support\Promotion;
use App\Support\UserDisplay;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

class BonusShopController extends BasePageController
{
    public function __construct(
        private readonly ?NexusCache $cache,
    ) {}

    public function freeleech(Request $request): View|RedirectResponse|Response
    {
        $administratorClass = defined('UC_ADMINISTRATOR') ? \constant('UC_ADMINISTRATOR') : 0;
        if (UserDisplay::currentClass() < $administratorClass) {
            return $this->abortResponse('Error', 'Access denied.');
        }

        $action = trim((string) ($request->post('action') ?? $request->query('action') ?? 'main'));
        $action = htmlspecialchars($action);

        $stateMap = [
            'setallfree' => 2,
            'setall2up' => 3,
            'setall2up_free' => 4,
            'setallhalf_down' => 5,
            'setall2up_half_down' => 6,
            'setallnormal' => 1,
        ];

        if (isset($stateMap[$action])) {
            return $this->abortResponse('Error', 'Permission denied.');
        }

        $links = [
            'setallfree' => 'set all torrents free',
            'setall2up' => 'set all torrents 2x up',
            'setall2up_free' => 'set all torrents 2x up and free',
            'setallhalf_down' => 'set all torrents half down',
            'setall2up_half_down' => 'set all torrents 2x up and half down',
            'setallnormal' => 'set all torrents normal',
        ];

        $message = '';
        foreach ($links as $key => $label) {
            $message .= 'Click <a class=altlink href=/web/freeleech?action='.$key.'>here</a> to '.$label.'..<br />';
        }

        return $this->abortResponse('Select action', $message, false);

    }

    public function freeleechSubmit(FreeleechRequest $request): View|RedirectResponse|Response
    {
        $administratorClass = defined('UC_ADMINISTRATOR') ? \constant('UC_ADMINISTRATOR') : 0;
        if (UserDisplay::currentClass() < $administratorClass) {
            return $this->abortResponse('Error', 'Access denied.');
        }

        $action = trim((string) ($request->post('action') ?? $request->query('action') ?? 'main'));
        $action = htmlspecialchars($action);

        $stateMap = [
            'setallfree' => 2,
            'setall2up' => 3,
            'setall2up_free' => 4,
            'setallhalf_down' => 5,
            'setall2up_half_down' => 6,
            'setallnormal' => 1,
        ];

        $messages = [
            'setallfree' => 'All torrents have been set free..',
            'setall2up' => 'All torrents have been set 2x up..',
            'setall2up_free' => 'All torrents have been set 2x up and free..',
            'setallhalf_down' => 'All torrents have been set half down..',
            'setall2up_half_down' => 'All torrents have been set half down..',
            'setallnormal' => 'All torrents have been set normal..',
        ];

        if (isset($stateMap[$action])) {
            Promotion::setGlobalSpecialState($stateMap[$action]);
            $this->cache?->forget('global_promotion_state');

            return $this->abortResponse('Success', $messages[$action]);
        }

        return $this->freeleech($request);
    }
}

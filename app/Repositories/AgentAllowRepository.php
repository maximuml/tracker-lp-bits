<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Exceptions\ClientNotAllowedException;
use App\Models\AgentAllow;
use App\Models\AgentDeny;
use App\Models\NexusModel;
use App\Support\Env;
use App\Support\Json;
use App\Support\Logger;
use App\Support\Url;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class AgentAllowRepository extends BaseRepository
{
    /** @return list<string> */
    protected function allowedSortColumns(): array
    {
        return ['id', 'family', 'allowed'];
    }

    /**
     * @param  array<int|string, mixed>  $params
     * @return mixed
     */
    public function getList(array $params)
    {
        $query = AgentAllow::query();
        if (! empty($params['family'])) {
            $query->where('family', 'like', "%{$params['family']}%");
        }
        [$sortField, $sortType] = $this->getSortFieldAndType($params);
        $query->orderBy($sortField, $sortType);

        return $query->paginate();
    }

    /**
     * @param  array<int|string, mixed>  $params
     * @return mixed
     */
    public function store(array $params)
    {
        $this->getPatternMatches($params['peer_id_pattern'], $params['peer_id_start'], $params['peer_id_match_num']);
        $this->getPatternMatches($params['agent_pattern'], $params['agent_start'], $params['agent_match_num']);
        /** @var array<string, mixed> $data */
        $data = $params;
        $model = AgentAllow::query()->create($data);

        return $model;
    }

    /**
     * @param  array<int|string, mixed>  $params
     * @return mixed
     */
    public function update(array $params, int $id)
    {
        $this->getPatternMatches($params['peer_id_pattern'], $params['peer_id_start'], $params['peer_id_match_num']);
        $this->getPatternMatches($params['agent_pattern'], $params['agent_start'], $params['agent_match_num']);
        $model = AgentAllow::query()->findOrFail($id);
        /** @var array<string, mixed> $data */
        $data = $params;
        $model->update($data);

        return $model;
    }

    /**
     * @return mixed
     */
    public function getDetail(int $id)
    {
        $model = AgentAllow::query()->findOrFail($id);

        return $model;
    }

    /**
     * @return mixed
     */
    public function delete(int $id)
    {
        $model = AgentAllow::query()->findOrFail($id);
        $model->denies()->delete();
        $result = $model->delete();

        return $result;
    }

    /**
     * @param  mixed  $pattern
     * @param  mixed  $start
     * @param  mixed  $matchNum
     * @return mixed
     */
    public function getPatternMatches($pattern, $start, $matchNum)
    {
        if (! preg_match($pattern, $start, $matches)) {
            throw new ClientNotAllowedException(sprintf('pattern: %s can not match start: %s', $pattern, $start));
        }
        $matchCount = count($matches) - 1;

        // due to old data may be matchNum > matchCount
        return array_slice($matches, 1, $matchNum);
    }

    /**
     * @param  mixed  $peerId
     * @param  mixed  $agent
     * @param  mixed  $debug
     * @return NexusModel|mixed
     *
     * @throws ClientNotAllowedException
     */
    public function checkClient($peerId, $agent, $debug = false)
    {
        // check from high version to low version, if high version allow, stop!
        $cacheKey = Env::get('CACHE_KEY_AGENT_ALLOW', 'all_agent_allows').':php';
        $allows = Cache::remember($cacheKey, 3600, function () {
            return AgentAllow::query()
                ->orderBy('peer_id_start', 'desc')
                ->orderBy('agent_start', 'desc')
                ->get();
        });
        $agentAllowPassed = null;
        $versionTooLowStr = '';
        foreach ($allows as $agentAllow) {
            $agentAllowId = $agentAllow->id;
            $logPrefix = "[ID: $agentAllowId]";
            $isPeerIdAllowed = $isAgentAllowed = $isPeerIdTooLow = $isAgentTooLow = false;
            // check peer_id, when handle scrape request, no peer_id, so let it pass
            if ($agentAllow->peer_id_pattern == '' || $peerId === null) {
                $isPeerIdAllowed = true;
            } else {
                $pattern = $agentAllow->peer_id_pattern;
                $start = $agentAllow->peer_id_start;
                $matchType = $agentAllow->peer_id_matchtype;
                $matchNum = $agentAllow->peer_id_match_num;
                try {
                    $peerIdResult = $this->isAllowed($pattern, $start, $matchNum, $matchType, $peerId, $debug, $logPrefix);
                    if ($debug) {
                        Logger::writeWithContext((string) ("{$logPrefix}, peerIdResult: {$peerIdResult}, with parameters: ".Json::encode(compact('pattern', 'start', 'matchNum', 'matchType', 'peerId'))), (string) 'info', (bool) false);
                    }
                } catch (\Exception $exception) {
                    Logger::writeWithContext((string) ("{$logPrefix}, check peer_id error: ".$exception->getMessage()), (string) 'error', (bool) false);
                    throw new ClientNotAllowedException('regular expression err for peer_id: '.$start.', please ask sysop to fix this');
                }
                if ($peerIdResult == 1) {
                    $isPeerIdAllowed = true;
                }
                if ($peerIdResult == 2) {
                    $isPeerIdTooLow = true;
                }
            }

            // check agent
            if ($agentAllow->agent_pattern == '') {
                $isAgentAllowed = true;
            } else {
                $pattern = $agentAllow->agent_pattern;
                $start = $agentAllow->agent_start;
                $matchType = $agentAllow->agent_matchtype;
                $matchNum = $agentAllow->agent_match_num;
                try {
                    $agentResult = $this->isAllowed($pattern, $start, $matchNum, $matchType, $agent, $debug, $logPrefix);
                    if ($debug) {
                        Logger::writeWithContext((string) ("{$logPrefix}, agentResult: {$agentResult}, with parameters: ".Json::encode(compact('pattern', 'start', 'matchNum', 'matchType', 'agent'))), (string) 'info', (bool) false);
                    }
                } catch (\Exception $exception) {
                    Logger::writeWithContext((string) ("{$logPrefix}, check agent error: ".$exception->getMessage()), (string) 'error', (bool) false);
                    throw new ClientNotAllowedException('regular expression err for agent: '.$start.', please ask sysop to fix this');
                }
                if ($agentResult == 1) {
                    $isAgentAllowed = true;
                }
                if ($agentResult == 2) {
                    $isAgentTooLow = true;
                }
            }

            // both OK, passed, client is allowed
            if ($isPeerIdAllowed && $isAgentAllowed) {
                $agentAllowPassed = $agentAllow;
                break;
            }
            if ($isPeerIdTooLow && $isAgentTooLow) {
                $versionTooLowStr = 'Your '.$agentAllow->family." 's version is too low, please update it after ".$agentAllow->start_name;
            }
        }

        if ($versionTooLowStr) {
            throw new ClientNotAllowedException($versionTooLowStr);
        }

        if (! $agentAllowPassed) {
            throw new ClientNotAllowedException('Banned Client, Please goto '.Url::schemeAndHost(false).'/faq.php#id29 for a list of acceptable clients');
        }

        if ($debug) {
            Logger::writeWithContext((string) ('agentAllowPassed: '.$agentAllowPassed->toJson()), (string) 'info', (bool) false);
        }

        // check if exclude
        if ($agentAllowPassed->exception) {
            $agentDeny = $this->checkIsDenied($peerId, $agent, $agentAllowPassed->id);
            if ($agentDeny) {
                if ($debug) {
                    Logger::writeWithContext((string) ('agentDeny: '.$agentDeny->toJson()), (string) 'info', (bool) false);
                }
                throw new ClientNotAllowedException(sprintf(
                    '[%s-%s]Client: %s is banned due to: %s',
                    $agentAllowPassed->id, $agentDeny->id, $agentDeny->name, $agentDeny->comment
                ));
            }
        }
        if (Url::isSecure() && ! $agentAllowPassed->allowhttps) {
            throw new ClientNotAllowedException(sprintf(
                '[%s]This client does not support https well, Please goto %s/faq.php#id29 for a list of proper clients',
                $agentAllowPassed->id, Url::schemeAndHost(false)
            ));
        }

        return $agentAllowPassed;

    }

    /**
     * @param  mixed  $peerId
     * @param  mixed  $agent
     * @param  mixed  $familyId
     * @return mixed
     */
    private function checkIsDenied($peerId, $agent, $familyId)
    {
        $cacheKey = Env::get('CACHE_KEY_AGENT_DENY', 'all_agent_denies').':php';
        /** @var Collection<int, mixed> $allDenies */
        $allDenies = Cache::remember($cacheKey, 3600, function () {
            return AgentDeny::query()->get()->groupBy('family_id');
        });
        $agentDenies = $allDenies->get($familyId, []);
        foreach ($agentDenies as $agentDeny) {
            if ($agentDeny->agent == $agent && preg_match('/^'.$agentDeny->peer_id.'/', $peerId)) {
                return $agentDeny;
            }
        }
    }

    /**
     * check peer_id or agent is allowed
     * 0: not allowed
     * 1: allowed
     * 2: version too low
     *
     * @param  mixed  $pattern
     * @param  mixed  $start
     * @param  mixed  $matchNum
     * @param  mixed  $matchType
     * @param  mixed  $value
     * @param  mixed  $debug
     * @param  mixed  $logPrefix
     *
     * @throws ClientNotAllowedException
     */
    private function isAllowed($pattern, $start, $matchNum, $matchType, $value, $debug = false, $logPrefix = ''): int
    {
        $matchBench = $this->getPatternMatches($pattern, $start, $matchNum);
        if ($debug) {
            Logger::writeWithContext((string) ("{$logPrefix}, matchBench: ".Json::encode($matchBench)), (string) 'info', (bool) false);
        }
        if (! preg_match($pattern, $value, $matchTarget)) {
            if ($debug) {
                Logger::writeWithContext((string) sprintf("{$logPrefix}, pattern: (%s) not match: (%s)", $pattern, $value), (string) 'info', (bool) false);
            }

            return 0;
        }
        if ($matchNum <= 0) {
            return 1;
        }
        $matchTarget = array_slice($matchTarget, 1);
        if ($debug) {
            Logger::writeWithContext((string) ("{$logPrefix}, matchTarget: ".Json::encode($matchTarget)), (string) 'info', (bool) false);
        }
        for ($i = 0; $i < $matchNum; $i++) {
            if (! isset($matchBench[$i]) || ! isset($matchTarget[$i])) {
                break;
            }
            if ($matchType == 'dec') {
                $matchBench[$i] = intval($matchBench[$i]);
                $matchTarget[$i] = intval($matchTarget[$i]);
            } elseif ($matchType == 'hex') {
                $matchBench[$i] = hexdec((string) $matchBench[$i]);
                $matchTarget[$i] = hexdec((string) $matchTarget[$i]);
            } else {
                throw new ClientNotAllowedException(sprintf('Invalid match type: %s', $matchType));
            }
            if ($matchTarget[$i] > $matchBench[$i]) {
                // higher, pass directly
                return 1;
            } elseif ($matchTarget[$i] < $matchBench[$i]) {
                return 2;
            }
        }

        // NOTE: at last, after all position checked, not [NOT_MATCH] or lower, it is passed!
        return 1;

    }

    /**
     * @param  mixed  $peerId
     * @param  mixed  $agent
     * @param  mixed  $debug
     * @return mixed
     */
    public function checkClientSimple($peerId, $agent, $debug = false)
    {
        // check from high version to low version, if high version allow, stop!
        $cacheKey = Env::get('CACHE_KEY_AGENT_ALLOW', 'all_agent_allows').':php';
        $allows = Cache::remember($cacheKey, 3600, function () {
            return AgentAllow::query()
                ->orderBy('peer_id_start', 'desc')
                ->orderBy('agent_start', 'desc')
                ->get();
        });
        $agentAllowPassed = null;
        foreach ($allows as $agentAllow) {
            $agentAllowId = $agentAllow->id;
            $agentAllowLogPrefix = "[ID: $agentAllowId], peerId: $peerId";
            $pattern = $agentAllow->peer_id_pattern;
            // check peer_id, when handle scrape request, no peer_id, so let it pass
            $isPeerIdAllowed = empty($pattern) || preg_match($pattern, $peerId);
            $agentAllowLogPrefix .= ", peer_id pattern: $pattern, isPeerIdAllowed: $isPeerIdAllowed";

            // check agent, agent must have both announce + scrape
            $pattern = $agentAllow->agent_pattern;
            $isAgentAllowed = ! empty($pattern) && preg_match($pattern, $agent);
            $agentAllowLogPrefix .= ", agent pattern: $pattern, isAgentAllowed: $isAgentAllowed";

            // both OK, passed, client is allowed
            if ($isPeerIdAllowed && $isAgentAllowed) {
                $agentAllowPassed = $agentAllow;
                Logger::writeWithContext((string) "{$agentAllowLogPrefix}, PASSED", (string) 'debug', (bool) false);
                break;
            }
            if ($debug) {
                Logger::writeWithContext((string) "{$agentAllowLogPrefix}, NOT PASSED", (string) 'debug', (bool) false);
            }
        }

        if (! $agentAllowPassed) {
            throw new ClientNotAllowedException('Banned Client, Please goto '.Url::schemeAndHost(false).'/faq.php#id29 for a list of acceptable clients');
        }

        if ($debug) {
            Logger::writeWithContext((string) ('agentAllowPassed: '.$agentAllowPassed->toJson()), (string) 'debug', (bool) false);
        }

        // check if exclude
        if ($agentAllowPassed->exception) {
            $agentDeny = $this->checkIsDenied($peerId, $agent, $agentAllowPassed->id);
            if ($agentDeny) {
                if ($debug) {
                    Logger::writeWithContext((string) ('agentDeny: '.$agentDeny->toJson()), (string) 'info', (bool) false);
                }
                throw new ClientNotAllowedException(sprintf(
                    '[%s-%s]Client: %s is banned due to: %s',
                    $agentAllowPassed->id, $agentDeny->id, $agentDeny->name, $agentDeny->comment
                ));
            }
        }
        if (Url::isSecure() && ! $agentAllowPassed->allowhttps) {
            throw new ClientNotAllowedException(sprintf(
                '[%s]This client does not support https well, Please goto %s/faq.php#id29 for a list of proper clients',
                $agentAllowPassed->id, Url::schemeAndHost(false)
            ));
        }

        return $agentAllowPassed;

    }
}

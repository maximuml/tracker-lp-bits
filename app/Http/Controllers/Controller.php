<?php

namespace App\Http\Controllers;

use App\Support\Api;
use App\Support\Logger;
use App\Support\Settings;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Support\Str;

class Controller extends BaseController
{
    use AuthorizesRequests, DispatchesJobs, ValidatesRequests;

    /** @var ?list<string> */
    protected ?array $extraFields = null;

    /** @var ?list<string> */
    protected ?array $extraSettingNames = null;

    /**
     * 返回成功信息
     *
     * @param  mixed  $data
     * @param  mixed  $msg
     * @return array<string, mixed>
     */
    public function success($data, $msg = null): array
    {
        if (is_null($msg)) {
            $backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2);
            $caller = $backtrace[1];
            $msg = $this->getReturnMsg($caller);
        }

        return Api::successWithContext($msg, $data);
    }

    /**
     * 返回成功信息，对于不是 JsonResource 的数据，进行包装。返回的数据在 data.data 中
     *
     * @param  mixed  $data
     * @param  mixed  $msg
     * @return array<string, mixed>
     *
     * @deprecated 没有必要，已经在 api() 中添加 data 包裹，使用 success() 即可
     */
    public function successJsonResource($data, $msg = null): array
    {
        if (is_null($msg)) {
            $backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2);
            $caller = $backtrace[1];
            $msg = $this->getReturnMsg($caller);
        }
        if ($data instanceof JsonResource) {
            return $this->success($data, $msg);
        }
        $resource = new JsonResource($data);

        return $this->success($resource, $msg);
    }

    /**
     * 返回失败信息，目前对于失败信息不需要包装
     *
     * @param  mixed  $data
     * @param  mixed  $msg
     * @return array<string, mixed>
     */
    public function fail($data, $msg = null)
    {
        if (is_null($msg)) {
            $backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2);
            $caller = $backtrace[1];
            $msg = $this->getReturnMsg($caller);
        }

        return Api::failWithContext($msg, $data);
    }

    /**
     * @param  array<int|string, mixed>  $backtrace
     * @return mixed
     */
    protected function getReturnMsg(array $backtrace)
    {
        $title = $this->title ?? '';
        if (empty($title)) {
            $title = $backtrace['class'];
            $pos = strripos($title, '\\');
            $title = substr($title, $pos + 1);
            $title = str_replace('Controller', '', $title);
        }
        $action = $backtrace['function'];
        $map = [
            'index' => 'list',
            'show' => 'detail',
            'update' => 'update',
            'destroy' => 'delete',
        ];
        if (isset($map[$action])) {
            $action = $map[$action];
        }

        return Str::slug("$title.$action", '.');
    }

    /** @return  array<int|string, mixed> */
    protected function getPaginationParameters(): array
    {
        $request = request();
        $format = $request->__format;
        if ($format == 'data-table') {
            $perPage = $request->length;
            $page = intval($request->start / $perPage) + 1;
        } else {
            $perPage = $request->limit;
            $page = $request->page;
        }

        return [$perPage, ['*'], 'page', $page];
    }

    /** @param  mixed  $field */
    protected function hasExtraField($field): bool
    {
        if ($this->extraFields === null) {
            $extraFieldsStr = request()->input('extra_fields', '');
            $this->extraFields = explode(',', $extraFieldsStr);
        }
        Logger::writeWithContext((string) sprintf('field: %s, extraFields: %s', $field, json_encode($this->extraFields)), (string) 'info', (bool) false);

        return in_array($field, $this->extraFields);
    }

    /**
     * @param  array<int|string, mixed>  $additional
     * @param  array<int|string, mixed>  $names
     */
    protected function appendExtraSettings(array &$additional, array $names): void
    {
        if ($this->extraSettingNames === null) {
            $extraSettingStr = request()->input('extra_settings', '');
            $this->extraSettingNames = explode(',', $extraSettingStr);
        }
        $results = [];
        foreach ($names as $name) {
            if (in_array($name, $this->extraSettingNames)) {
                $results[$name] = Settings::get($name);
            }
        }
        if (! empty($results)) {
            $additional['extra_settings'] = $results;
        }
    }
}

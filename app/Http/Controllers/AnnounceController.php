<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Exceptions\TrackerException;
use App\Exceptions\TrackerWarningException;
use App\Http\Requests\AnnounceRequest;
use App\Services\AnnounceService;
use App\Support\Json;
use App\Support\Logger;
use App\Support\Metrics\AnnounceMetricsRecorder;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Rhilip\Bencode\Bencode;
use Symfony\Component\HttpFoundation\Response;

class AnnounceController extends Controller
{
    public function __construct(private readonly AnnounceService $service) {}

    public function announce(Request $request): Response
    {
        try {
            $validated = $request->validate(AnnounceRequest::announceRules());

            $validEvents = ['started', 'completed', 'stopped', 'paused'];
            if (empty($validated['event']) || ! in_array($validated['event'], $validEvents, true)) {
                $validated['event'] = null;
            }

            $response = $this->service->handle($request, $validated);
        } catch (ValidationException $e) {
            $msg = collect($e->errors())->flatten()->first();
            AnnounceMetricsRecorder::recordRejection('validation error');

            return $this->bencodeResponse(['failure reason' => (string) $msg]);
        } catch (TrackerException $e) {
            AnnounceMetricsRecorder::recordRejection($e->getMessage());

            return $this->bencodeResponse(['failure reason' => $e->getMessage()]);
        } catch (TrackerWarningException $e) {
            return $this->bencodeResponse($e->getResponse());
        }

        return $this->bencodeResponse($response);
    }

    /** @param array<string, mixed> $dict */
    private function bencodeResponse(array $dict): Response
    {
        $logDict = $dict;
        unset($logDict['peers'], $logDict['peers6']);
        Logger::writeWithContext((string) Json::encode($logDict), (string) 'info', (bool) false);

        return response(
            Bencode::encode($dict),
            200,
            [
                'Content-Type' => 'text/plain; charset=utf-8',
                'Pragma' => 'no-cache',
            ]
        );
    }
}

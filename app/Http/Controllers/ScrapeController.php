<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\DTOs\ScrapeRequestDto;
use App\Exceptions\TrackerException;
use App\Exceptions\TrackerWarningException;
use App\Services\ScrapeService;
use App\Support\Json;
use App\Support\Logger;
use Illuminate\Http\Request;
use Rhilip\Bencode\Bencode;
use Symfony\Component\HttpFoundation\Response;

class ScrapeController extends Controller
{
    public function __construct(private readonly ScrapeService $service) {}

    public function scrape(Request $request): Response
    {
        try {
            $dto = ScrapeRequestDto::fromRequest($request);
            $response = $this->service->scrape($dto);
        } catch (TrackerException $e) {
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
        unset($logDict['files']);
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

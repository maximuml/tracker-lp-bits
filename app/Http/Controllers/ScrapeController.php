<?php

namespace App\Http\Controllers;

use App\Exceptions\TrackerException;
use App\Exceptions\TrackerWarningException;
use App\Services\ScrapeService;
use Illuminate\Http\Request;
use Rhilip\Bencode\Bencode;
use Symfony\Component\HttpFoundation\Response;

class ScrapeController extends Controller
{
    public function __construct(private ScrapeService $service)
    {
    }

    public function scrape(Request $request): Response
    {
        try {
            $response = $this->service->scrape($request);
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
        do_log(nexus_json_encode($logDict));

        return response(
            Bencode::encode($dict),
            200,
            [
                'Content-Type' => 'text/plain; charset=utf-8',
                'Pragma'       => 'no-cache',
            ]
        );
    }
}

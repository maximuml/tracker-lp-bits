<?php

declare(strict_types=1);

namespace App\Repositories\TorrentSearch;

use App\Support\LegacyResponse;

/**
 * Resolve taxonomy selections (category + sub-taxonomies) from explicit
 * query params, a single taxonomy click, or user notif preferences.
 *
 * Extracted from FilterParser to keep both classes under the 400-line
 * ratchet.
 */
final class TaxonomySelectionParser
{
    /**
     * Resolve taxonomy selections (category + optional sub-taxonomies) from
     * explicit params, a single taxonomy click, or user notif preferences.
     *
     * @param  array<string, mixed>  $searchParams
     * @param  array<string, mixed>  $CURUSER
     * @param  array<int, array<string, mixed>>  $cats
     * @param  array<int, array<string, mixed>>  $sources
     * @param  array<int, array<string, mixed>>  $media
     * @param  array<int, array<string, mixed>>  $codecs
     * @param  array<int, array<string, mixed>>  $standards
     * @param  array<int, array<string, mixed>>  $processings
     * @param  array<int, array<string, mixed>>  $audiocodecs
     * @return array{
     *     wherecatina: list<int>,
     *     wheresourceina: list<int>,
     *     wheremediumina: list<int>,
     *     wherecodecina: list<int>,
     *     wherestandardina: list<int>,
     *     whereprocessingina: list<int>,
     *     whereaudiocodecina: list<int>,
     *     addparam: string,
     *     all: bool|int,
     * }
     */
    public function resolve(
        array $searchParams,
        array $CURUSER,
        bool $hasSearchParams,
        int|bool $showsubcat,
        int|bool $showsource,
        int|bool $showmedium,
        int|bool $showcodec,
        int|bool $showstandard,
        int|bool $showprocessing,
        int|bool $showaudiocodec,
        array $cats,
        array $sources,
        array $media,
        array $codecs,
        array $standards,
        array $processings,
        array $audiocodecs,
    ): array {
        $addparam = '';
        $wherecatina = [];
        $wheresourceina = [];
        $wheremediumina = [];
        $wherecodecina = [];
        $wherestandardina = [];
        $whereprocessingina = [];
        $whereaudiocodecina = [];

        $category_get = intval($searchParams['cat'] ?? 0);
        $source_get = $medium_get = $codec_get = $standard_get = $processing_get = $audiocodec_get = 0;
        if ($showsubcat) {
            if ($showsource) {
                $source_get = intval($searchParams['source'] ?? 0);
            }
            if ($showmedium) {
                $medium_get = intval($searchParams['medium'] ?? 0);
            }
            if ($showcodec) {
                $codec_get = intval($searchParams['codec'] ?? 0);
            }
            if ($showstandard) {
                $standard_get = intval($searchParams['standard'] ?? 0);
            }
            if ($showprocessing) {
                $processing_get = intval($searchParams['processing'] ?? 0);
            }
            if ($showaudiocodec) {
                $audiocodec_get = intval($searchParams['audiocodec'] ?? 0);
            }
        }

        $all = intval($searchParams['all'] ?? 0);

        if (! $all) {
            if (! $hasSearchParams && $CURUSER['notifs']) {
                $all = true;
                $notifs = (string) $CURUSER['notifs'];
                $catResult = $this->collectFromNotifs($cats, 'cat', 'cat', $notifs, $all, $addparam);
                $all = $catResult['all'];
                $wherecatina = $catResult['ina'];
                if ($showsubcat) {
                    if ($showsource) {
                        $res = $this->collectFromNotifs($sources, 'sou', 'source', $notifs, $all, $addparam);
                        $all = $res['all'];
                        $wheresourceina = $res['ina'];
                    }
                    if ($showmedium) {
                        $res = $this->collectFromNotifs($media, 'med', 'medium', $notifs, $all, $addparam);
                        $all = $res['all'];
                        $wheremediumina = $res['ina'];
                    }
                    if ($showcodec) {
                        $res = $this->collectFromNotifs($codecs, 'cod', 'codec', $notifs, $all, $addparam);
                        $all = $res['all'];
                        $wherecodecina = $res['ina'];
                    }
                    if ($showstandard) {
                        $res = $this->collectFromNotifs($standards, 'sta', 'standard', $notifs, $all, $addparam);
                        $all = $res['all'];
                        $wherestandardina = $res['ina'];
                    }
                    if ($showprocessing) {
                        $res = $this->collectFromNotifs($processings, 'pro', 'processing', $notifs, $all, $addparam);
                        $all = $res['all'];
                        $whereprocessingina = $res['ina'];
                    }
                    if ($showaudiocodec) {
                        $res = $this->collectFromNotifs($audiocodecs, 'aud', 'audiocodec', $notifs, $all, $addparam);
                        $all = $res['all'];
                        $whereaudiocodecina = $res['ina'];
                    }
                }
            }
            // when one clicked the cat, source, etc. name/image
            elseif ($category_get) {
                LegacyResponse::assertId($category_get, true, true, true);
                $wherecatina[] = $category_get;
                $addparam .= "cat=$category_get&";
            } elseif ($medium_get) {
                LegacyResponse::assertId($medium_get, true, true, true);
                $wheremediumina[] = $medium_get;
                $addparam .= "medium=$medium_get&";
            } elseif ($source_get) {
                LegacyResponse::assertId($source_get, true, true, true);
                $wheresourceina[] = $source_get;
                $addparam .= "source=$source_get&";
            } elseif ($codec_get) {
                LegacyResponse::assertId($codec_get, true, true, true);
                $wherecodecina[] = $codec_get;
                $addparam .= "codec=$codec_get&";
            } elseif ($standard_get) {
                LegacyResponse::assertId($standard_get, true, true, true);
                $wherestandardina[] = $standard_get;
                $addparam .= "standard=$standard_get&";
            } elseif ($processing_get) {
                LegacyResponse::assertId($processing_get, true, true, true);
                $whereprocessingina[] = $processing_get;
                $addparam .= "processing=$processing_get&";
            } elseif ($audiocodec_get) {
                LegacyResponse::assertId($audiocodec_get, true, true, true);
                $whereaudiocodecina[] = $audiocodec_get;
                $addparam .= "audiocodec=$audiocodec_get&";
            } else { // select and go
                $all = true;
                $catResult = $this->collectFromParams($cats, 'cat', $searchParams, $all, $addparam);
                $all = $catResult['all'];
                $wherecatina = $catResult['ina'];
                if ($showsubcat) {
                    if ($showsource) {
                        $res = $this->collectFromParams($sources, 'source', $searchParams, $all, $addparam);
                        $all = $res['all'];
                        $wheresourceina = $res['ina'];
                    }
                    if ($showmedium) {
                        $res = $this->collectFromParams($media, 'medium', $searchParams, $all, $addparam);
                        $all = $res['all'];
                        $wheremediumina = $res['ina'];
                    }
                    if ($showcodec) {
                        $res = $this->collectFromParams($codecs, 'codec', $searchParams, $all, $addparam);
                        $all = $res['all'];
                        $wherecodecina = $res['ina'];
                    }
                    if ($showstandard) {
                        $res = $this->collectFromParams($standards, 'standard', $searchParams, $all, $addparam);
                        $all = $res['all'];
                        $wherestandardina = $res['ina'];
                    }
                    if ($showprocessing) {
                        $res = $this->collectFromParams($processings, 'processing', $searchParams, $all, $addparam);
                        $all = $res['all'];
                        $whereprocessingina = $res['ina'];
                    }
                    if ($showaudiocodec) {
                        $res = $this->collectFromParams($audiocodecs, 'audiocodec', $searchParams, $all, $addparam);
                        $all = $res['all'];
                        $whereaudiocodecina = $res['ina'];
                    }
                }
            }
        }

        return [
            'wherecatina' => $wherecatina,
            'wheresourceina' => $wheresourceina,
            'wheremediumina' => $wheremediumina,
            'wherecodecina' => $wherecodecina,
            'wherestandardina' => $wherestandardina,
            'whereprocessingina' => $whereprocessingina,
            'whereaudiocodecina' => $whereaudiocodecina,
            'addparam' => $addparam,
            'all' => $all,
        ];
    }

    /**
     * Collect taxonomy selections from user notification preferences.
     *
     * @param  array<int, array<string, mixed>>  $items
     * @return array{ina: list<int>, all: bool}
     */
    private function collectFromNotifs(array $items, string $prefix, string $paramKey, string $notifs, bool|int $all, string &$addparam): array
    {
        $ina = [];
        foreach ($items as $item) {
            $findme = '['.$prefix.$item['id'].']';
            $check = strpos($notifs, $findme) !== false;
            $all = $all && $check;
            if ($check) {
                $ina[] = (int) $item['id'];
                $addparam .= "{$paramKey}{$item['id']}=1&";
            }
        }

        return ['ina' => $ina, 'all' => (bool) $all];
    }

    /**
     * Collect taxonomy selections from explicit query parameters.
     *
     * @param  array<int, array<string, mixed>>  $items
     * @param  array<string, mixed>  $searchParams
     * @return array{ina: list<int>, all: int|bool}
     */
    private function collectFromParams(array $items, string $paramKey, array $searchParams, bool|int $all, string &$addparam): array
    {
        $ina = [];
        foreach ($items as $item) {
            $__is = (isset($searchParams[$paramKey.$item['id']]) && $searchParams[$paramKey.$item['id']]);
            $all &= $__is;
            if ($__is) {
                $ina[] = (int) $item['id'];
                $addparam .= "{$paramKey}{$item['id']}=1&";
            }
        }

        return ['ina' => $ina, 'all' => $all];
    }
}

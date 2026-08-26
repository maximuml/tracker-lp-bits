<?php

namespace App\Repositories;

use App\Auth\Permission;
use App\Models\Torrent;
use App\Support\Category;
use App\Support\Config\SiteConfig;
use App\Support\CurrentUser;
use App\Support\Globals;
use App\Support\Hooks;
use App\Support\LegacyResponse;
use App\Support\Log;
use App\Support\Logger;
use App\Support\Pagination;
use App\Support\Promotion;
use App\Support\SearchBox;
use App\Support\SearchSuggest;
use App\Support\SupportContext;
use Carbon\Carbon;
use Illuminate\Database\Connection;
use Illuminate\Support\Facades\DB;
use Nexus\Nexus;

class TorrentSearchRepository
{
    /**
     * @param  array<string, mixed>  $query  Query parameters to use instead of $_GET
     * @return array<string, mixed>
     */
    public static function getListingData(array $query = []): array
    {
        $CURUSER = app(CurrentUser::class)->get() ?? [];
        $lang_torrents = app(Globals::class)->get('lang_torrents', []);
        $browsecatmode = (int) app(Globals::class)->get('browsecatmode', 1);
        $torrentsperpage_main = (int) app(Globals::class)->get('torrentsperpage_main', 0);
        $catimgurl = '';
        $catpadding = 0;
        $catsperrow = 0;

        if (empty($CURUSER)) {
            $CURUSER = [];
        }

        $sources = $media = $codecs = $standards = $processings = $audiocodecs = [];

        // check searchbox
        switch (Nexus::instance()->getScript()) {
            case 'torrents':
                $sectiontype = $browsecatmode;
                break;
            default:
                $sectiontype = 0;
        }
        /**
         * tags
         */
        $tagRep = new TagRepository;
        $allTags = $tagRep->listAll($sectiontype);
        $filterInputWidth = 62;
        $searchParams = $query ?: request()->query();
        $hasSearchParams = ! empty($searchParams);
        $searchParams['mode'] = $sectiontype;

        $showsubcat = SearchBox::valueWithContext($sectiontype, 'showsubcat'); // whether show subcategory (i.e. sources, codecs) or not
        $showsource = SearchBox::valueWithContext($sectiontype, 'showsource'); // whether show sources or not
        $showmedium = SearchBox::valueWithContext($sectiontype, 'showmedium'); // whether show media or not
        $showcodec = SearchBox::valueWithContext($sectiontype, 'showcodec'); // whether show codecs or not
        $showstandard = SearchBox::valueWithContext($sectiontype, 'showstandard'); // whether show standards or not
        $showprocessing = SearchBox::valueWithContext($sectiontype, 'showprocessing'); // whether show processings or not
        $showaudiocodec = SearchBox::valueWithContext($sectiontype, 'showaudiocodec'); // whether show audio codec or not
        $catsperrow = SearchBox::valueWithContext($sectiontype, 'catsperrow'); // show how many cats per line in search box
        $catpadding = SearchBox::valueWithContext($sectiontype, 'catpadding'); // padding space between categories in pixel

        $cats = Category::listByModeWithContext($sectiontype);
        if ($showsubcat) {
            if ($showsource) {
                $sources = SearchBox::itemListWithContext('sources', $sectiontype);
            }
            if ($showmedium) {
                $media = SearchBox::itemListWithContext('media', $sectiontype);
            }
            if ($showcodec) {
                $codecs = SearchBox::itemListWithContext('codecs', $sectiontype);
            }
            if ($showstandard) {
                $standards = SearchBox::itemListWithContext('standards', $sectiontype);
            }
            if ($showprocessing) {
                $processings = SearchBox::itemListWithContext('processings', $sectiontype);
            }
            if ($showaudiocodec) {
                $audiocodecs = SearchBox::itemListWithContext('audiocodecs', $sectiontype);
            }
        }

        $searchstr_raw = is_scalar($searchParams['search'] ?? '') ? (string) ($searchParams['search'] ?? '') : '';
        $searchstr_ori = htmlspecialchars(trim($searchstr_raw));
        $searchstr = substr(DB::getPdo()->quote(trim($searchstr_raw)), 1, -1);
        $searchParams['search'] = $searchstr_raw;
        if (empty($searchstr)) {
            unset($searchstr);
        }

        $meilisearchEnabled = SiteConfig::current()->meiliSearch->enabled();
        $shouldUseMeili = $meilisearchEnabled && ! empty($searchstr);
        Logger::writeWithContext((string) "[SHOULD_USE_MEILI]: {$shouldUseMeili}", (string) 'info', (bool) false);
        // sorting by MarkoStamcar
        $column = '';
        $ascdesc = '';
        if (isset($searchParams['sort']) && $searchParams['sort'] && isset($searchParams['type']) && $searchParams['type']) {

            switch ($searchParams['sort']) {
                case '1': $column = 'name';
                    break;
                case '2': $column = 'numfiles';
                    break;
                case '3': $column = 'comments';
                    break;
                case '4': $column = 'added';
                    break;
                case '5': $column = 'size';
                    break;
                case '6': $column = 'times_completed';
                    break;
                case '7': $column = 'seeders';
                    break;
                case '8': $column = 'leechers';
                    break;
                case '9': $column = 'owner';
                    break;
                default: $column = 'id';
                    break;
            }

            switch ($searchParams['type']) {
                case 'asc': $ascdesc = 'ASC';
                    $linkascdesc = 'asc';
                    break;
                case 'desc': $ascdesc = 'DESC';
                    $linkascdesc = 'desc';
                    break;
                default: $ascdesc = 'DESC';
                    $linkascdesc = 'desc';
                    break;
            }

            if ($column == 'owner') {
                $orderby = 'ORDER BY pos_state DESC, torrents.anonymous, users.username '.$ascdesc;
            } else {
                $orderby = 'ORDER BY pos_state DESC, torrents.'.$column.' '.$ascdesc;
            }

            $pagerlink = 'sort='.intval($searchParams['sort']).'&type='.$linkascdesc.'&';

        } else {

            $orderby = 'ORDER BY pos_state DESC, torrents.id DESC';
            $pagerlink = '';

        }

        $allCategoryId = \App\Models\SearchBox::listCategoryId($sectiontype);
        $addparam = '';
        $wherea = [];
        $wherecatina = [];
        $wheresourceina = [];
        $wheremediumina = [];
        $wherecodecina = [];
        $wherestandardina = [];
        $whereprocessingina = [];
        $whereaudiocodecina = [];
        $whereothera = [];
        // ----------------- start whether show torrents from all sections---------------------//
        if ($hasSearchParams) {
            $allsec = intval($searchParams['allsec'] ?? 0);
        } else {
            $allsec = 0;
        }
        if ($allsec == 1) {		// show torrents from all sections
            $addparam .= 'allsec=1&';
        }
        // ----------------- end whether ignoring section ---------------------//
        // ----------------- start bookmarked ---------------------//
        $inclbookmarked = 0;
        if ($hasSearchParams) {
            $inclbookmarked = intval($searchParams['inclbookmarked'] ?? 0);
        } elseif ($CURUSER['notifs']) {
            if (strpos($CURUSER['notifs'], '[inclbookmarked=0]') !== false) {
                $inclbookmarked = 0;
            } elseif (strpos($CURUSER['notifs'], '[inclbookmarked=1]') !== false) {
                $inclbookmarked = 1;
            } elseif (strpos($CURUSER['notifs'], '[inclbookmarked=2]') !== false) {
                $inclbookmarked = 2;
            }
        }

        if (! in_array($inclbookmarked, [0, 1, 2])) {
            $inclbookmarked = 0;
            Log::writeWithContext('User '.$CURUSER['username'].','.$CURUSER['ip'].' is hacking inclbookmarked field in'.SupportContext::getServerValue('SCRIPT_NAME', ''), 'mod');
        }
        if ($inclbookmarked == 0) {  // all(bookmarked,not)
            $addparam .= 'inclbookmarked=0&';
        } elseif ($inclbookmarked == 1) {		// bookmarked
            $addparam .= 'inclbookmarked=1&';
            if (! empty($CURUSER['id'])) {
                $wherea[] = 'torrents.id IN (SELECT torrentid FROM bookmarks WHERE userid='.$CURUSER['id'].')';
            }
        } elseif ($inclbookmarked == 2) {		// not bookmarked
            $addparam .= 'inclbookmarked=2&';
            if (! empty($CURUSER['id'])) {
                $wherea[] = 'torrents.id NOT IN (SELECT torrentid FROM bookmarks WHERE userid='.$CURUSER['id'].')';
            }
        }
        // ----------------- end bookmarked ---------------------//

        // ----------------- start include dead ---------------------//
        if (isset($searchParams['incldead'])) {
            $include_dead = intval($searchParams['incldead'] ?? 0);
        } elseif ($CURUSER['notifs']) {
            if (strpos($CURUSER['notifs'], '[incldead=0]') !== false) {
                $include_dead = 0;
            } elseif (strpos($CURUSER['notifs'], '[incldead=1]') !== false) {
                $include_dead = 1;
            } elseif (strpos($CURUSER['notifs'], '[incldead=2]') !== false) {
                $include_dead = 2;
            } else {
                $include_dead = 1;
            }
        } else {
            $include_dead = 1;
        }

        if (! in_array($include_dead, [0, 1, 2])) {
            $include_dead = 0;
            Log::writeWithContext('User '.$CURUSER['username'].','.$CURUSER['ip'].' is hacking incldead field in'.SupportContext::getServerValue('SCRIPT_NAME', ''), 'mod');
        }
        if ($include_dead == 0) {  // all(active,dead)
            $addparam .= 'incldead=0&';
        } elseif ($include_dead == 1) {		// active
            $addparam .= 'incldead=1&';
            //	$wherea[] = "visible = 'yes'";
            $whereothera[] = "visible = 'yes'";
        } elseif ($include_dead == 2) {		// dead
            $addparam .= 'incldead=2&';
            //	$wherea[] = "visible = 'no'";
            $whereothera[] = "visible = 'no'";
        }
        // ----------------- end include dead ---------------------//

        if (empty($CURUSER['id']) || ! Permission::canViewBannedTorrent()) {
            //    $wherea[] = "banned = 'no'";
            $whereothera[] = "banned = 'no'";
            $searchParams['banned'] = 'no';
        }

        $special_state = 0;
        if ($hasSearchParams) {
            $special_state = intval($searchParams['spstate'] ?? 0);
        } elseif ($CURUSER['notifs']) {
            if (strpos($CURUSER['notifs'], '[spstate=0]') !== false) {
                $special_state = 0;
            } elseif (strpos($CURUSER['notifs'], '[spstate=1]') !== false) {
                $special_state = 1;
            } elseif (strpos($CURUSER['notifs'], '[spstate=2]') !== false) {
                $special_state = 2;
            } elseif (strpos($CURUSER['notifs'], '[spstate=3]') !== false) {
                $special_state = 3;
            } elseif (strpos($CURUSER['notifs'], '[spstate=4]') !== false) {
                $special_state = 4;
            } elseif (strpos($CURUSER['notifs'], '[spstate=5]') !== false) {
                $special_state = 5;
            } elseif (strpos($CURUSER['notifs'], '[spstate=6]') !== false) {
                $special_state = 6;
            } elseif (strpos($CURUSER['notifs'], '[spstate=7]') !== false) {
                $special_state = 7;
            }
        }

        if (! in_array($special_state, [0, 1, 2, 3, 4, 5, 6, 7])) {
            $special_state = 0;
            Log::writeWithContext('User '.$CURUSER['username'].','.$CURUSER['ip'].' is hacking spstate field in '.SupportContext::getServerValue('SCRIPT_NAME', ''), 'mod');
        }
        if ($special_state == 0) {	// all
            $addparam .= 'spstate=0&';
        } elseif ($special_state == 1) {	// normal
            $addparam .= 'spstate=1&';

            $wherea[] = 'sp_state = 1';

            if (Promotion::globalSpecialState() == 1) {
                $wherea[] = 'sp_state = 1';
            }
        } elseif ($special_state == 2) {	// free
            $addparam .= 'spstate=2&';

            if (Promotion::globalSpecialState() == 1) {
                $wherea[] = 'sp_state = 2';
            } elseif (Promotion::globalSpecialState() == 2) {

            }
        } elseif ($special_state == 3) {	// 2x up
            $addparam .= 'spstate=3&';
            if (Promotion::globalSpecialState() == 1) {	// only sp state
                $wherea[] = 'sp_state = 3';
            } elseif (Promotion::globalSpecialState() == 3) {	// all

            }
        } elseif ($special_state == 4) {	// 2x up and free
            $addparam .= 'spstate=4&';

            if (Promotion::globalSpecialState() == 1) {	// only sp state
                $wherea[] = 'sp_state = 4';
            } elseif (Promotion::globalSpecialState() == 4) {	// all

            }
        } elseif ($special_state == 5) {	// half down
            $addparam .= 'spstate=5&';

            if (Promotion::globalSpecialState() == 1) {	// only sp state
                $wherea[] = 'sp_state = 5';
            } elseif (Promotion::globalSpecialState() == 5) {	// all

            }
        } elseif ($special_state == 6) {	// half down
            $addparam .= 'spstate=6&';

            if (Promotion::globalSpecialState() == 1) {	// only sp state
                $wherea[] = 'sp_state = 6';
            } elseif (Promotion::globalSpecialState() == 6) {	// all

            }
        } elseif ($special_state == 7) {	// 30% down
            $addparam .= 'spstate=7&';

            if (Promotion::globalSpecialState() == 1) {	// only sp state
                $wherea[] = 'sp_state = 7';
            } elseif (Promotion::globalSpecialState() == 7) {	// all

            }
        }

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
                foreach ($cats as $cat) {
                    $all &= $cat['id'];
                    $mystring = $CURUSER['notifs'];
                    $findme = '[cat'.$cat['id'].']';
                    $search = strpos($mystring, $findme);
                    if ($search === false) {
                        $catcheck = false;
                    } else {
                        $catcheck = true;
                    }

                    if ($catcheck) {
                        $wherecatina[] = $cat['id'];
                        $addparam .= "cat$cat[id]=1&";
                    }
                }
                if ($showsubcat) {
                    if ($showsource) {
                        foreach ($sources as $source) {
                            $all &= $source['id'];
                            $mystring = $CURUSER['notifs'];
                            $findme = '[sou'.$source['id'].']';
                            $search = strpos($mystring, $findme);
                            if ($search === false) {
                                $sourcecheck = false;
                            } else {
                                $sourcecheck = true;
                            }

                            if ($sourcecheck) {
                                $wheresourceina[] = $source['id'];
                                $addparam .= "source{$source['id']}=1&";
                            }
                        }
                    }
                    if ($showmedium) {
                        foreach ($media as $medium) {
                            $all &= $medium['id'];
                            $mystring = $CURUSER['notifs'];
                            $findme = '[med'.$medium['id'].']';
                            $search = strpos($mystring, $findme);
                            if ($search === false) {
                                $mediumcheck = false;
                            } else {
                                $mediumcheck = true;
                            }

                            if ($mediumcheck) {
                                $wheremediumina[] = $medium['id'];
                                $addparam .= "medium{$medium['id']}=1&";
                            }
                        }
                    }
                    if ($showcodec) {
                        foreach ($codecs as $codec) {
                            $all &= $codec['id'];
                            $mystring = $CURUSER['notifs'];
                            $findme = '[cod'.$codec['id'].']';
                            $search = strpos($mystring, $findme);
                            if ($search === false) {
                                $codeccheck = false;
                            } else {
                                $codeccheck = true;
                            }

                            if ($codeccheck) {
                                $wherecodecina[] = $codec['id'];
                                $addparam .= "codec{$codec['id']}=1&";
                            }
                        }
                    }
                    if ($showstandard) {
                        foreach ($standards as $standard) {
                            $all &= $standard['id'];
                            $mystring = $CURUSER['notifs'];
                            $findme = '[sta'.$standard['id'].']';
                            $search = strpos($mystring, $findme);
                            if ($search === false) {
                                $standardcheck = false;
                            } else {
                                $standardcheck = true;
                            }

                            if ($standardcheck) {
                                $wherestandardina[] = $standard['id'];
                                $addparam .= "standard{$standard['id']}=1&";
                            }
                        }
                    }
                    if ($showprocessing) {
                        foreach ($processings as $processing) {
                            $all &= $processing['id'];
                            $mystring = $CURUSER['notifs'];
                            $findme = '[pro'.$processing['id'].']';
                            $search = strpos($mystring, $findme);
                            if ($search === false) {
                                $processingcheck = false;
                            } else {
                                $processingcheck = true;
                            }

                            if ($processingcheck) {
                                $whereprocessingina[] = $processing['id'];
                                $addparam .= "processing{$processing['id']}=1&";
                            }
                        }
                    }
                    if ($showaudiocodec) {
                        foreach ($audiocodecs as $audiocodec) {
                            $all &= $audiocodec['id'];
                            $mystring = $CURUSER['notifs'];
                            $findme = '[aud'.$audiocodec['id'].']';
                            $search = strpos($mystring, $findme);
                            if ($search === false) {
                                $audiocodeccheck = false;
                            } else {
                                $audiocodeccheck = true;
                            }

                            if ($audiocodeccheck) {
                                $whereaudiocodecina[] = $audiocodec['id'];
                                $addparam .= "audiocodec{$audiocodec['id']}=1&";
                            }
                        }
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
                foreach ($cats as $cat) {
                    $__is = (isset($searchParams["cat{$cat['id']}"]) && $searchParams["cat{$cat['id']}"]);
                    $all &= $__is;
                    if ($__is) {
                        $wherecatina[] = $cat['id'];
                        $addparam .= "cat{$cat['id']}=1&";
                    }
                }
                if ($showsubcat) {
                    if ($showsource) {
                        foreach ($sources as $source) {
                            $__is = (isset($searchParams["source{$source['id']}"]) && $searchParams["source{$source['id']}"]);
                            $all &= $__is;
                            if ($__is) {
                                $wheresourceina[] = $source['id'];
                                $addparam .= "source{$source['id']}=1&";
                            }
                        }
                    }
                    if ($showmedium) {
                        foreach ($media as $medium) {
                            $__is = (isset($searchParams["medium{$medium['id']}"]) && $searchParams["medium{$medium['id']}"]);
                            $all &= $__is;
                            if ($__is) {
                                $wheremediumina[] = $medium['id'];
                                $addparam .= "medium{$medium['id']}=1&";
                            }
                        }
                    }
                    if ($showcodec) {
                        foreach ($codecs as $codec) {
                            $__is = (isset($searchParams["codec{$codec['id']}"]) && $searchParams["codec{$codec['id']}"]);
                            $all &= $__is;
                            if ($__is) {
                                $wherecodecina[] = $codec['id'];
                                $addparam .= "codec{$codec['id']}=1&";
                            }
                        }
                    }
                    if ($showstandard) {
                        foreach ($standards as $standard) {
                            $__is = (isset($searchParams["standard{$standard['id']}"]) && $searchParams["standard{$standard['id']}"]);
                            $all &= $__is;
                            if ($__is) {
                                $wherestandardina[] = $standard['id'];
                                $addparam .= "standard{$standard['id']}=1&";
                            }
                        }
                    }
                    if ($showprocessing) {
                        foreach ($processings as $processing) {
                            $__is = (isset($searchParams["processing{$processing['id']}"]) && $searchParams["processing{$processing['id']}"]);
                            $all &= $__is;
                            if ($__is) {
                                $whereprocessingina[] = $processing['id'];
                                $addparam .= "processing{$processing['id']}=1&";
                            }
                        }
                    }
                    if ($showaudiocodec) {
                        foreach ($audiocodecs as $audiocodec) {
                            $__is = (isset($searchParams["audiocodec{$audiocodec['id']}"]) && $searchParams["audiocodec{$audiocodec['id']}"]);
                            $all &= $__is;
                            if ($__is) {
                                $whereaudiocodecina[] = $audiocodec['id'];
                                $addparam .= "audiocodec{$audiocodec['id']}=1&";
                            }
                        }
                    }
                }
            }
        }

        if ($all) {
            // stderr("in if all","");
            $wherecatina = [];
            if ($showsubcat) {
                $wheresourceina = [];
                $wheremediumina = [];
                $wherecodecina = [];
                $wherestandardina = [];
                $whereprocessingina = [];
                $whereaudiocodecina = [];
            }
            $addparam .= '';
        }
        // stderr("", count($wherecatina)."-". count($wheresourceina));
        $wherecatin = $wheresourcein = $wheremediumin = $wherecodecin = $wherestandardin = $whereprocessingin = $whereaudiocodecin = '';
        if (empty($wherecatina) && ! (in_array($inclbookmarked, [1, 2]) && $allsec == 1)) {
            // require limit in some category
            $wherecatina = $allCategoryId;
        }
        $wherecatina = is_array($wherecatina) ? $wherecatina : [];
        if (count($wherecatina) > 1) {
            $wherecatin = implode(',', $wherecatina);
        } elseif (count($wherecatina) == 1) {
            $wherea[] = "category = $wherecatina[0]";
        }

        if ($showsubcat) {
            if ($showsource) {
                if (count($wheresourceina) > 1) {
                    $wheresourcein = implode(',', $wheresourceina);
                } elseif (count($wheresourceina) == 1) {
                    $wherea[] = "source = $wheresourceina[0]";
                }
            }

            if ($showmedium) {
                if (count($wheremediumina) > 1) {
                    $wheremediumin = implode(',', $wheremediumina);
                } elseif (count($wheremediumina) == 1) {
                    $wherea[] = "medium = $wheremediumina[0]";
                }
            }

            if ($showcodec) {
                if (count($wherecodecina) > 1) {
                    $wherecodecin = implode(',', $wherecodecina);
                } elseif (count($wherecodecina) == 1) {
                    $wherea[] = "codec = $wherecodecina[0]";
                }
            }

            if ($showstandard) {
                if (count($wherestandardina) > 1) {
                    $wherestandardin = implode(',', $wherestandardina);
                } elseif (count($wherestandardina) == 1) {
                    $wherea[] = "standard = $wherestandardina[0]";
                }
            }

            if ($showprocessing) {
                if (count($whereprocessingina) > 1) {
                    $whereprocessingin = implode(',', $whereprocessingina);
                } elseif (count($whereprocessingina) == 1) {
                    $wherea[] = "processing = $whereprocessingina[0]";
                }
            }
        }

        if ($showaudiocodec) {
            if (count($whereaudiocodecina) > 1) {
                $whereaudiocodecin = implode(',', $whereaudiocodecina);
            } elseif (count($whereaudiocodecina) == 1) {
                $wherea[] = "audiocodec = $whereaudiocodecina[0]";
            }
        }

        $wherebase = $wherea;
        $search_area = 0;
        if (isset($searchstr)) {
            if (! isset($searchParams['notnewword']) || ! $searchParams['notnewword']) {
                $notnewword = '';
            } else {
                $notnewword = 'notnewword=1&';
            }
            $search_mode = intval($searchParams['search_mode'] ?? 0);
            /**
             * Deprecated search mode: 1(OR)
             *
             * @since 1.8
             */
            if (! in_array($search_mode, [0, 2])) {
                $search_mode = 0;
                Log::writeWithContext('User '.$CURUSER['username'].','.$CURUSER['ip'].' is hacking search_mode field in'.SupportContext::getServerValue('SCRIPT_NAME', ''), 'mod');
            }

            $search_area = intval($searchParams['search_area'] ?? 0);
            $like_expression_array = [];

            switch ($search_mode) {
                case 0:	// AND, OR
                case 1:

                    $searchstr = str_replace('.', ' ', $searchstr);
                    $searchstr_exploded = explode(' ', $searchstr);
                    $searchstr_exploded_count = 0;
                    foreach ($searchstr_exploded as $searchstr_element) {
                        $searchstr_element = trim($searchstr_element);	// furthur trim to ensure that multi space seperated words still work
                        $searchstr_exploded_count++;
                        if ($searchstr_exploded_count > 3) {	// maximum 3 keywords
                            break;
                        }
                        $like_expression_array[] = " LIKE '%".$searchstr_element."%'";
                    }
                    break;

                case 2:	// exact

                    $like_expression_array[] = " LIKE '%".$searchstr."%'";
                    break;

                    /*case 3 :	// parsed
                    {
                    $like_expression_array[] = $searchstr;
                    break;
                    }*/
            }
            $ANDOR = ($search_mode == 0 ? ' AND ' : ' OR ');	// only affects mode 0 and mode 1

            switch ($search_area) {
                case 0:	// torrent name

                    foreach ($like_expression_array as &$like_expression_array_element) {
                        $like_expression_array_element = '(torrents.name'.$like_expression_array_element.')';
                    }
                    $wherea[] = implode($ANDOR, $like_expression_array);
                    break;

                case 1:	// torrent description

                    foreach ($like_expression_array as &$like_expression_array_element) {
                        //			$like_expression_array_element = "torrents.descr". $like_expression_array_element;
                        $like_expression_array_element = 'torrent_extras.descr'.$like_expression_array_element;
                    }
                    $wherea[] = implode($ANDOR, $like_expression_array);
                    break;

                    /*case 2	:	// torrent small description
                    {
                        foreach ($like_expression_array as &$like_expression_array_element)
                        $like_expression_array_element =  "torrents.small_descr". $like_expression_array_element;
                        $wherea[] =  implode($ANDOR, $like_expression_array);
                        break;
                    }*/
                case 3:	// torrent uploader

                    foreach ($like_expression_array as &$like_expression_array_element) {
                        $like_expression_array_element = 'users.username'.$like_expression_array_element;
                    }

                    if (empty($CURUSER['id'])) {	// not registered user, only show not anonymous torrents
                        $wherea[] = implode($ANDOR, $like_expression_array)." AND torrents.anonymous = 'no'";
                    } else {
                        if (Permission::canManageTorrent()) {	// moderator or above, show all
                            $wherea[] = implode($ANDOR, $like_expression_array);
                        } else { // only show normal torrents and anonymous torrents from hiself
                            $wherea[] = '('.implode($ANDOR, $like_expression_array)." AND torrents.anonymous = 'no') OR (".implode($ANDOR, $like_expression_array)." AND torrents.anonymous = 'yes' AND users.id=".$CURUSER['id'].') ';
                        }
                    }
                    break;

                default:	// unkonwn

                    $search_area = 0;
                    $wherea[] = "torrents.name LIKE '%".$searchstr."%'";
                    Log::writeWithContext('User '.$CURUSER['username'].','.$CURUSER['ip'].' is hacking search_area field in'.SupportContext::getServerValue('SCRIPT_NAME', ''), 'mod');
                    break;

            }
            $addparam .= 'search_area='.$search_area.'&';
            $addparam .= 'search='.rawurlencode($searchstr).'&'.$notnewword;
            $addparam .= 'search_mode='.$search_mode.'&';
        }

        // approval status
        $approvalStatusNoneVisible = SiteConfig::current()->torrent->approvalStatusNoneVisible();
        $approvalStatusIconEnabled = SiteConfig::current()->torrent->approvalStatusIconEnabled();
        $approvalStatus = null;
        $showApprovalStatusFilter = false;
        // when enable approval status icon, all user can use this filter, otherwise only staff member and approval none visible is 'no' can use
        if ($approvalStatusIconEnabled || (Permission::canApproveTorrent() && ! $approvalStatusNoneVisible)) {
            $showApprovalStatusFilter = true;
        }
        // when user can use approval status filter, and pass `approval_status` parameter, will affect
        // OR if [not approval can not be view] and not staff member, force to view  approval allowed
        if ($showApprovalStatusFilter && isset($searchParams['approval_status']) && is_numeric($searchParams['approval_status'])) {
            $approvalStatus = intval($searchParams['approval_status']);
            $wherea[] = "torrents.approval_status = $approvalStatus";
            $searchParams['approval_status'] = $approvalStatus;
            $addparam .= "approval_status=$approvalStatus&";
        } elseif (! $approvalStatusNoneVisible && ! Permission::canApproveTorrent()) {
            $wherea[] = 'torrents.approval_status = '.Torrent::APPROVAL_STATUS_ALLOW;
            $searchParams['approval_status'] = Torrent::APPROVAL_STATUS_ALLOW;
        }

        if (isset($searchParams['size_begin']) && ctype_digit($searchParams['size_begin'])) {
            $wherea[] = 'torrents.size >= '.intval($searchParams['size_begin']) * 1024 * 1024 * 1024;
            $addparam .= 'size_begin='.intval($searchParams['size_begin']).'&';
        }
        if (isset($searchParams['size_end']) && ctype_digit($searchParams['size_end'])) {
            $wherea[] = 'torrents.size <= '.intval($searchParams['size_end']) * 1024 * 1024 * 1024;
            $addparam .= 'size_end='.intval($searchParams['size_end']).'&';
        }

        if (isset($searchParams['seeders_begin']) && ctype_digit($searchParams['seeders_begin'])) {
            $wherea[] = 'torrents.seeders >= '.(int) $searchParams['seeders_begin'];
            $addparam .= 'seeders_begin='.intval($searchParams['seeders_begin']).'&';
        }
        if (isset($searchParams['seeders_end']) && ctype_digit($searchParams['seeders_end'])) {
            $wherea[] = 'torrents.seeders <= '.(int) $searchParams['seeders_end'];
            $addparam .= 'seeders_end='.intval($searchParams['seeders_end']).'&';
        }

        if (isset($searchParams['leechers_begin']) && ctype_digit($searchParams['leechers_begin'])) {
            $wherea[] = 'torrents.leechers >= '.(int) $searchParams['leechers_begin'];
            $addparam .= 'leechers_begin='.intval($searchParams['leechers_begin']).'&';
        }
        if (isset($searchParams['leechers_end']) && ctype_digit($searchParams['leechers_end'])) {
            $wherea[] = 'torrents.leechers <= '.(int) $searchParams['leechers_end'];
            $addparam .= 'leechers_end='.intval($searchParams['leechers_end']).'&';
        }

        if (isset($searchParams['times_completed_begin']) && ctype_digit($searchParams['times_completed_begin'])) {
            $wherea[] = 'torrents.times_completed >= '.(int) $searchParams['times_completed_begin'];
            $addparam .= 'times_completed_begin='.intval($searchParams['times_completed_begin']).'&';
        }
        if (isset($searchParams['times_completed_end']) && ctype_digit($searchParams['times_completed_end'])) {
            $wherea[] = 'torrents.times_completed <= '.(int) $searchParams['times_completed_end'];
            $addparam .= 'times_completed_end='.intval($searchParams['times_completed_end']).'&';
        }

        /** @var Connection $db */
        $db = DB::table('torrents')->getConnection();
        $quote = fn ($value) => (string) $db->getPdo()->quote((string) $value);

        if (isset($searchParams['added_begin']) && ! empty($searchParams['added_begin'])) {
            $wherea[] = 'torrents.added >= '.$quote($searchParams['added_begin']);
            $addparam .= 'added_begin='.$searchParams['added_begin'].'&';
        }
        if (isset($searchParams['added_end']) && ! empty($searchParams['added_end'])) {
            $wherea[] = 'torrents.added <= '.$quote(Carbon::parse($searchParams['added_end'])->endOfDay()->toDateTimeString());
            $addparam .= 'added_end='.$searchParams['added_end'].'&';
        }

        $where = implode(' AND ', $wherea);

        if ($wherecatin) {
            $where .= ($where ? ' AND ' : '').'category IN('.$wherecatin.')';
        }
        if ($showsubcat) {
            if ($wheresourcein) {
                $where .= ($where ? ' AND ' : '').'source IN('.$wheresourcein.')';
            }
            if ($wheremediumin) {
                $where .= ($where ? ' AND ' : '').'medium IN('.$wheremediumin.')';
            }
            if ($wherecodecin) {
                $where .= ($where ? ' AND ' : '').'codec IN('.$wherecodecin.')';
            }
            if ($wherestandardin) {
                $where .= ($where ? ' AND ' : '').'standard IN('.$wherestandardin.')';
            }
            if ($whereprocessingin) {
                $where .= ($where ? ' AND ' : '').'processing IN('.$whereprocessingin.')';
            }
            if ($whereaudiocodecin) {
                $where .= ($where ? ' AND ' : '').'audiocodec IN('.$whereaudiocodecin.')';
            }
        }
        // last
        if (! empty($whereothera)) {
            $where .= ($where ? ' AND ' : '').implode(' AND ', $whereothera);
        }

        $tagId = intval($searchParams['tag_id'] ?? 0);
        if ($tagId > 0) {
            $addparam .= "tag_id={$tagId}&";
        }
        $listingOptions = [
            'where' => $where,
            'join_users' => ($search_area == 3 || $column == 'owner'),
            'join_torrent_tags' => $tagId > 0,
            'tag_id' => $tagId,
            'join_torrent_extras' => $search_area == 1,
        ];

        if ($shouldUseMeili) {
            try {
                $searchRep = new MeiliSearchRepository;
                $resultFromSearchRep = $searchRep->search($searchParams, $CURUSER['id']);
                $count = $resultFromSearchRep['total'];
            } catch (\Throwable $e) {
                Logger::writeWithContext((string) ('MeiliSearch search failed, falling back to SQL: '.$e->getMessage()), (string) 'error', (bool) false);
                $shouldUseMeili = false;
                $count = TorrentListingRepository::getCount($listingOptions);
            }
        } else {
            $count = TorrentListingRepository::getCount($listingOptions);
        }
        $maxPageSize = 100;
        if (! empty($searchParams['pageSize'])) {
            $torrentsperpage = $searchParams['pageSize'];
        } elseif ($CURUSER['torrentsperpage']) {
            $torrentsperpage = (int) $CURUSER['torrentsperpage'];
        } elseif ($torrentsperpage_main) {
            $torrentsperpage = $torrentsperpage_main;
        } else {
            $torrentsperpage = $maxPageSize;
        }
        $torrentsperpage = min($maxPageSize, $torrentsperpage);

        if ($count) {
            if (isset($searchstr) && (! isset($searchParams['notnewword']) || ! $searchParams['notnewword'])) {
                SearchSuggest::add((string) $searchstr, $CURUSER['id'], (bool) true);
            }
            if ($pagerlink !== '') {
                if (substr($addparam, -1) === ';') {
                    $addparam .= $pagerlink;
                } else {
                    $addparam .= '&'.$pagerlink;
                }
            }
            // stderr("addparam",$addparam);
            // echo $addparam;

            [$pagertop, $pagerbottom, $limit, $offset, $size, $page] = Pagination::pager($torrentsperpage, $count, '?'.$addparam);

            $fieldsArr = Torrent::getFieldsForList(true);
            $rows = $shouldUseMeili
                ? $resultFromSearchRep['list']
                : TorrentListingRepository::getList(array_merge($listingOptions, [
                    'fields' => $fieldsArr,
                    'search_box_id' => $sectiontype,
                    'order_by' => $orderby,
                    'offset' => $offset,
                    'limit' => $size,
                ]));
            $rows = Hooks::applyFilter('torrent_list', $rows, $page, $sectiontype, $searchstr_raw);
        }

        if (isset($searchstr)) {
            $pageTitle = $lang_torrents['head_search_results_for'].$searchstr_ori;
        } elseif ($sectiontype == $browsecatmode) {
            $pageTitle = $lang_torrents['head_torrents'];
        } else {
            $pageTitle = $lang_torrents['head_special'];
        }

        return get_defined_vars();
    }
}

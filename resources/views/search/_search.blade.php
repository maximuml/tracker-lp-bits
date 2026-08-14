<?php

$search = (string) ($search ?? '');
$searchArea = (int) ($searchArea ?? \App\Repositories\MeiliSearchRepository::SEARCH_AREA_TITLE);
$count = (int) ($count ?? 0);
$rows = (array) ($rows ?? []);
$pagertop = (string) ($pagertop ?? '');
$pagerbottom = (string) ($pagerbottom ?? '');
$torrentsperpage = (int) ($torrentsperpage ?? 50);
$searchstr_ori = (string) ($searchstr_ori ?? htmlspecialchars($search));
$hasResults = (bool) ($hasResults ?? false);

$lang_torrents = \App\Support\SupportContext::getGlobal('lang_torrents', []);

\App\Support\Html::stdhead(\App\Support\Locale::trans('search.global_search', [], null));
print("<table width=\"97%\" class=\"main\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\"><tr><td class=\"embedded\">");

if ($hasResults) {
    print($pagertop);
    echo \App\Support\TorrentTable::render($rows);
    print($pagerbottom);
} elseif ($search !== '') {
    \App\Support\Html::stdMessage($lang_torrents['std_search_results_for'] . $searchstr_ori . "\"", $lang_torrents['std_try_again']);
}

print("</td></tr></table>");
\App\Support\Html::stdfoot();

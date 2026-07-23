<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class RulesTableSeeder extends Seeder
{

    /**
     * Auto generated seed file
     *
     * @return void
     */
    public function run()
    {
        

        DB::table('rules')->delete();
        
        DB::table('rules')->insert(array (
              0 => 
              array (
                'id' => 21,
                'lang_id' => 6,
                'title' => 'General rules - <font class=striking>Breaking these rules can and will get you banned!</font>',
                'text' => '[*]Do not do things we forbid.
            [*]Do not spam.
            [*]Cherish your user account. Inactive accounts would be deleted based on the following rules:
            1.[b]Veteran User[/b] or above would never be deleted.
            2.[b]Elite User[/b] or above would never be deleted if packed (at [url=usercp.php?action=personal]User CP[/url]).
            3.Packed accounts would be deleted if users have not logged in for more than 400 days in a row.
            4.Unpacked accounts would be deleted if users have not logged in for more than 150 days in a row.
            5.Accounts with both uploaded and downloaded amount being 0 would be deleted if users have not logged in for more than 100 days in a row.
            [*]User found cheating would be deleted. Don\'t take chances!
            [*]Possession of multiple [site] accounts will result in a ban!
            [*]Do not upload our torrents to other trackers! (See the [url=faq.php#38]FAQ[/url] for details.)
            [*]Disruptive behavior in the forums or on the server will result in a warning. You will only get [b]one[/b] warning! After that it\'s bye bye Kansas!',
              ),
              1 => 
              array (
                'id' => 25,
                'lang_id' => 6,
                'title' => 'Commenting Guidelines - <font class=striking>Always respect uploaders no matter what!</font>',
                'text' => '[*]Always respect uploaders no matter what!
            [*]All rules of forum posting apply to commenting, too.
            [*]Do not post negative comments about torrents that you don\'t plan to download.',
              ),
              3 => 
              array (
                'id' => 22,
                'lang_id' => 6,
                'title' => 'Downloading rules - <font class=striking>By not following these rules you will lose download privileges!</font>',
                'text' => '[*]Low ratios may result in severe consequences, including banning accounts. See [url=faq.php#22]FAQ[/url].
            [*]Rules for torrent promotion:
            [*]Random promotion (torrents promoted randomly by system upon uploading):
            [*]10% chance becoming [color=#7c7ff6][b]50% Leech[/b][/color],
            [*]5% chance becoming [color=#f0cc00][b]Free Leech[/b][/color],
            [*]5% chance becoming [color=#aaaaaa][b]2X up[/b][/color],
            [*]3% chance becoming [color=#7ad6ea][b]50% Leech and 2X up[/b][/color],
            [*]1% chance becoming [color=#99cc66][b]Free Leech and 2X up[/b][/color].
            [*]Torrents larger than 20GB will automatically be [color=#f0cc00][b]Free Leech[/b][/color].
            [*]Raw Blu-ray, HD DVD Discs will be [color=#f0cc00][b]Free Leech[/b][/color].
            [*]First episode of every season of TV Series, etc. will be [color=#f0cc00][b]Free Leech[/b][/color].
            [*]Highly popular torrents will be on promotion (decided by admins).
            [*]Promotion timeout:
            [*]Except [color=#aaaaaa][b]2X up[/b][/color], all the other types of promotion will be due after 7 days (counted from the time when the torrent is uploaded).
            [*][color=#aaaaaa][b]2X up[/b][/color] will never become due.
            [*]ALL the torrents will be [color=#aaaaaa][b]2X up[/b][/color] forever when they are on the site for over 1 month.
            [*]On special occasions, we would set the whole site [color=#f0cc00][b]Free Leech[/b][/color]. Grab as much as you can. :mml:  :mml:  :mml:
            [*]You may [b]only[/b] use allowed bittorrent clients at [site]. See [url=faq.php#29]FAQ[/url].',
              ),
              4 => 
              array (
                'id' => 24,
                'lang_id' => 6,
                'title' => 'General Forum Guidelines - <font class=stiking>Please follow these guidelines or else you might end up with a warning!</font>',
                'text' => '[*]No aggressive behavior or flaming in the forums.
            [*]No trashing of any topics (i.e. SPAM). Do not submit meaningless topics or posts (e.g. smiley only) in any forum except Water Jar.
            [*]Do not flood any forum in order to get bonus.
            [*]No foul language on title or text.
            [*]Do not discuss topics that are taboo, political sensitive or forbidden by local laws.
            [*]No language of discrimination based on race, national or ethnic origin, color, religion, gender, age, sexual preference or mental or physical disability. Violating this rule would result in permanent ban.
            [*]No bumping... (All bumped threads will be deleted.)
            [*]No double posting.
            [*]Please ensure all questions are posted in the correct section!
            [*]Topics without new reply in 365 days would be locked automatically by system.',
              ),
              5 => 
              array (
                'id' => 26,
                'lang_id' => 6,
                'title' => 'Avatar Guidelines - <font class=striking>Please try to follow these guidelines</font>',
                'text' => '[*]The allowed formats are .gif, .jpg and .png.
            [*]Be considerate. Resize your images to a width of 150 px and a size of no more than 150 KB. (Browsers will rescale them anyway: smaller images will be expanded and will not look good; larger images will just waste bandwidth and CPU cycles.)
            [*]Do not use potentially offensive material involving porn, religious material, animal / human cruelty or ideologically charged images. Staff members have wide discretion on what is acceptable. If in doubt PM one. ',
              ),
              6 => 
              array (
                'id' => 23,
                'lang_id' => 6,
                'title' => 'Uploading rules - <font class=striking>Torrents violating these rules may be deleted without notice</font>',
                'text' => 'Please respect the rules, and if you have any questions about something unclear or not understandable, please [url=contactstaff.php]consult the staff[/url]. Staff reserves the rights to adjudicate.
            [b]GENERAL[/b]
            [*]You must have legal rights to the file you upload.
            [*]Make sure your torrents are well-seeded. If you fail to seed for at least 24 hours or till someone else completes, or purposely keep a low uploading speed, you can be warned and your privilege to upload can be removed.
            [*]You would get 2 times as much of uploading credit for torrents uploaded by yourself.
            [*]If you have something interesting that somehow violates these rules, [url=contactstaff.php]ask the staff[/url] with a detailed description and we might make an exception.
            [b]PRIVILEGE[/b]
            [*]Everyone can upload.
            [*]However, some must go through the [url=offers.php]Offer section[/url]. See [url=faq.php#22]FAQ[/url] for details.
            [*]ONLY users in the class [color=#DC143C][b]Uploader[/b][/color] or above, or users specified by staff can freely upload games. Others should go through the [url=offers.php]Offer section[/url].
            [b]ALLOWED CONTENTS[/b]
            [*]High Definition (HD) videos, including
            [*]complete HD media, e.g. Blu-ray disc, HD DVD disc, etc. or remux,
            [*]captured HDTV streams,
            [*]encodes from above listed sources in HD resolution (at least 720p),
            [*]other HD videos such as HD DV.
            [*]Standard Definition (SD) videos, only
            [*]SD encodes from HD media (at least 480p),
            [*]DVDR/DVDISO,
            [*]DVDRip, CNDVDRip.
            [*]Lossless audio tracks (and corresponding cue sheets), e.g. FLAC, Monkey\'s Audio, etc.
            [*]5.1-channel (or higher) movie dubs and music tracks (DTS, DTS CD Image, etc.), and commentary tracks.
            [*]PC games (must be original images).
            [*]HD trailers released within 7 days.
            [*]HD-related software and documents.
            [b]NOT ALLOWED CONTENTS[/b]
            [*]Contents less than 100 MB in total.
            [*]Upscaled/partially upscaled in Standard Definition mastered/produced content.
            [*]Videos in SD resolution but with low quality, including CAM, TC, TS, SCR, DVDSCR, R5, R5.Line, HalfCD, etc.
            [*]RealVideo encoded videos (usually contained in RMVB or RM), flv files.
            [*]Individual samples (to be included in the "Main torrent").
            [*]Lossy audios that are not 5.1-channel (or higher), e.g. common lossy MP3\'s, lossy WMAs, etc.
            [*]Multi-track audio files without proper cue sheets.
            [*]Installation-free or highly compressed games, unofficial game images, third-party mods, collection of tiny games, individual game cracks or patches.
            [*]RAR, etc. archived files.
            [*]Dupe releases. (see beneath for dupe rules.)
            [*]Taboo or sensitive contents (such as porn or politically sensitive topics).
            [*]Damaged files, i.e. files that are erroneous upon reading or playback.
            [*]Spam files, such as viruses, trojans, website links, advertisements, torrents in torrent, etc., or irrelevant files.
            [b]DUPE RULES: QUALITY OVER QUANTITY[/b]
            [*]Video releases are prioritized according to their source media, and mainly: Blu-ray/HD DVD > HDTV > DVD > TV. High prioritized versions will dupe other versions with low priorities of the same video.
            [*]HD releases will dupe SD releases of the same video.
            [*]For animes, HDTV versions are equal in priority to DVD versions. This is an exception.
            [*]Encodes from the same type of media and in the same resolution
            [*]They are prioritized based on "[url=forums.php?action=viewtopic&forumid=6&topicid=1520]Scene & Internal, from Group to Quality-Degree. ONLY FOR HD-resources[/url]".
            [*]Releases from preferred groups will dupe releases from groups with the same or lower priority.
            [*]However, one DVD5 sized (i.e. approx. 4.38 GB) release from the best available source will always be allowed.
            [*]Based on lossless screenshots comparison, releases with higher quality will dupe those with low quality.
            [*]Blu-ray Disk/HD DVD Original Copy releases from another region containing different dubbing and/or subtitle aren\'t considered to be dupe.
            [*]Only one copy of the same lossless audio contents will be preserved, and copies of other formats will be duped. FLAC (in separate tracks) is most preferred.
            [*]For contents already on the site
            [*]If new release doesn\'t contain the confirmed errors/glitches/problems of the old release or is based on a better source, then it\'s allowed to be uploaded and the old release is duped.
            [*]If the old release is dead for 45 days or longer, or exists for 18 months or longer, then the new release is free from the dupe rules.
            [*]After uploading the new release, old releases won\'t be removed until they\'re dead of inactivity.
            [b]PACKING RULES (ON TRIAL)[/b]
            ONLY the following contents are allowed to be packed in principle:
            [*]HD movie collections sold as box set (e.g. [i]The Ultimate Matrix Collection Blu-ray Box[/i]).
            [*]Complete season(s) of TV Series/TV shows/animes.
            [*]Documentaries on the same specific subject matter.
            [*]HD trailers released within 7 days.
            [*]MVs of the same artist
            [*]SD MVs are allowed to be packed according to DVD discs only, and no upload of individual songs is allowed.
            [*]HD MVs in the same resolution.
            [*]Music of the same artist
            [*]Only 5 or more albums can be packed.
            [*]Albums released within 2 years can be individually uploaded.
            [*]Generally, contents that are already on the site should be removed from the pack upon uploading, otherwise include them all together in the pack.
            [*]Animes, character songs, dramas, etc. that are released in separate volumes.
            [*]Contents packed by formal groups.
            Packed video contents must be from media of the same type (e.g. Blu-ray discs), in the same resolution standard (e.g. 720p), and encoded in the same video codec (e.g. x264). However, trailer are exceptions. Moreover, a movie collection should be released from the same group. Packed audio contents must be encoded in the same audio codec (e.g. all in FLAC). Corresponding individual torrents can be removed upon packing, depending on actual situation.
            If you are not clear of anything about packing, please [url=contactstaff.php]consult the staff[/url]. Staff reserve all the rights to interpret and deal with packing-related issues.
            [b]EXCEPTIONS[/b]
            [*]ALLOWED: SD videos from TV/DSR in the category "Sports".
            [*]ALLOWED: contents less than 100 MB but related to software and documents.
            [*]ALLOWED: single albums that are less than 100 MB.
            [*]ALLOWED: 2.0-channel (or higher) Mandarin/Cantonese dubs.
            [*]ALLOWED: attached subtitles, game cracks and patches, fonts, scans (of packages, etc.). These files must be all either archived or unarchived.
            [*]ALLOWED: when uploading CD releases, attaching contents from the DVD given with the CD.
            [b]TORRENT INFORMATION[/b]
            All torrents shall have descriptive titles, necessary descriptions and other information. Following is a brief regulation. Please refer to "[url=forums.php?action=viewtopic&topicid=3438&page=0#56711]Standard and Guidance of Torrent Information[/url]" (in Chinese) for complete and detailed instructions.
            [*]Title
            [*]Movies: [i]Name [Year] [Cut] [Release Info] Resolution Source [Audio/]Video Codec-Tag[/i]
            e.g. [i]The Dark Knight 2008 PROPER 720p BluRay x264-SiNNERS[/i]
            [*]TV Series/Mini-serie: [i]Name [Year] S**E** [Release Info] Resolution Source [Audio/]Video Codec-Tag[/i]
            e.g. [i]Prison Break S04E01 PROPER 720p HDTV x264-CTU[/i]
            [*]Musics: [i]Artist - Album [Year] [Version] [Release Info] Audio Codec[-Tag][/i]
            e.g. [i]Enya - And Winter Came 2008 FLAC[/i]
            [*]Games: [i]Name [Year] [Version] [Release Info][-Tag][/i]
            e.g. [i]Command And Conquer Red Alert 3 Uprising-RELOADED[/i]
            [*]Small description
            [*]No advertisements or asking for a reseed/requests.
            [*]External Info
            [*]URL of external info for Movies and TV Series is required (if available).
            [*]Description
            [*]Do not use the description for your NFO-artwork! Limit those artistic expressions to the NFO only.
            [*]For Movies, TV Series/Mini-series and animes:
            [*]Poster, banner or BD/HDDVD/DVD cover is required (If available).
            [*]Adding screenshots or thumbnails and links to the screenshots is encouraged.
            [*]Adding detailed file information regarding format, runtime, codec, bitrate, resolution, language, subtitle, etc. is encouraged.
            [*]Adding a list of staff and cast and plot outline is encouraged.
            [*]For Sports:
            [*]Don\'t spoil the results trough text/screenshots/filenames/obvious filesize/detailed runtime.
            [*]For Music:
            [*]The CD cover and the track list are required (if available).
            [*]For PC Games:
            [*]Poster, banner or BD/HDDVD/DVD cover is required (If available).
            [*]Adding screenshots or thumbnails and links to the screenshots is encouraged.
            [*]Misc
            [*]Please correctly specify the category and quality info.
            [*]NOTES
            [*]Moderators will edit the torrent info according to the standard.
            [*]Do NOT remove or alter changes done by the staff (but some mistakes can be fixed by the uploader).
            [*]Torrents without required information can be deleted, depending on how they meet the standard.
            [*]The original torrent information can be used if it basically meets the standard.
            ',
              ),
              7 => 
              array (
                'id' => 28,
                'lang_id' => 6,
                'title' => 'Moderating Rules - <font class=striking>Use your better judgement!</font>',
                'text' => '[*]The most important rule: Use your better judgment!
            [*]Don\'t be afraid to say [b]NO[/b]!
            [*]Don\'t defy another staff member in public, instead send a PM or through IM.
            [*]Be tolerant! Give the user(s) a chance to reform.
            [*]Don\'t act prematurely, let the users make their mistakes and THEN correct them.
            [*]Try correcting any "off topics" rather then closing a thread.
            [*]Move topics rather than locking them.
            [*]Be tolerant when moderating the chat section (give them some slack).
            [*]If you lock a topic, give a brief explanation as to why you\'re locking it.
            [*]Before you disable a user account, send him/her a PM and if they reply, put them on a 2 week trial.
            [*]Don\'t disable a user account until he or she has been a member for at least 4 weeks.
            [*]Convince people by reasoning rather than authority.',
              ),
              8 => 
              array (
                'id' => 50,
                'lang_id' => 6,
                'title' => 'Rules for Subtitles - <font class=striking>Subtitles violating these rules will be deleted</font>',
                'text' => '(This text is translated from the Chinese version. In case of discrepancy, the original version in Chinese shall prevail.)
            [b]GENERAL PRINCIPLE:[/b]
            [*]All subtitles uploaded must conform to the rules (i.e. proper or qualified). Unqualified subtitles will be deleted.
            [*]Allowed file formats are srt/ssa/ass/cue/zip/rar.
            [*]If you\'re uploading Vobsub (idx+sub) subtitles or subtitles of other types, or a collection (e.g. subtitles for a season pack of some TV series), please zip/rar them before uploading.
            [*]Cue sheet of audio tracks is allowed as well. If there are several cue sheets, please pack them all.
            [*]Uploading lrc lyrics or other non-subtitle/non-cue files is not permitted. Irrelevant files if uploaded will be directly deleted.
            [b]QUALIFYING SUBTITLE/CUE FILES: improper subtitle/cue files will be directly deleted.[/b]
            In any of the following cases, a subtitle/cue file will be judged as improper:
            [*]Fail to match the corresponding torrent.
            [*]Fail to be in sync with the corresponding video/audio file.
            [*]Packed Improperly.
            [*]Contain irrelevant or spam stuff.
            [*]Encoded incorrectly.
            [*]Wrong cue file.
            [*]Wrong language mark.
            [*]The title is indefinite or contains redundant info/characters.
            [*]Duplicate.
            [*]Reported by several users and confirmed with other problems.
            [b]The staff group reserves rights to judge and deal with improper subtitles.[/b]
            Please refer to [url=http://www.nexushd.org/forums.php?action=viewtopic&forumid=13&topicid=2848][i]this thread[/i][/url] in the forum for detailed regulations on qualifying subtitle/cue files, other notes and suggestions on uploading subtitles, and subtitle naming and entitling guidance.
            [b]IMPLEMENTING REGULATIONS OF REWARDS AND PENALTIES [/b]
            [*]Reporting against improper subtitles and the uploaders who purposely upload improper subtitles is always welcomed. To report an improper subtitle, please click on the [i]REPORT[/i] button of the corresponding subtitle in the subtitle section. To report a user, please click on the [i]REPORT[/i] button at the bottom of the user details page.
            [*]The reporter will be rewarded 50 karma points (delivered in three days) for each case after confirmation.
            [*]Improper subtitles will be deleted and the corresponding uploader will be fined 100 karma points in each case.
            [*]Users who recklessly uploading improper subtitles for karma points or other purposes, or users who maliciously report, will be fined karma points or warned depending on the seriousness of the case.
            ',
              ),
              9 => 
              array (
                'id' => 53,
                'lang_id' => 6,
                'title' => 'Staff\'s retirement benefits',
                'text' => 'You can get retirement benefits when meeting these condition(s) below:
            [b]for [color=#DC143C]Uploaders[/color]: [/b]
            To join [color=#1cc6d5][b]Retiree[/b]: [/color]
            Been promoted for more than 1 year; have posted 200 or more torrents (special cases can be decided via vote among staffs, like Source-Disc posters, scene-uploaders; should be considered as having made rare and enduring contribution).
            To join [color=#009F00][b]VIP[/b]: [/color]
            Been promoted for more than 6 months; have posted 100 or more torrents (special cases can be decided via vote among staffs, like Source-Disc posters, scene-uploaders).
            Others:
            Demoted to [color=#F88C00][b]Extreme User[/b][/color] (if your profile meets the corresponding condition of classes [color=#F88C00][b]Extreme User[/b][/color] and above, then promoted to [color=#38ACEC][b]Nexus Master[/b][/color]).
            [b]for [color=#6495ED]Moderators[/color]: [/b]
            To join [color=#1cc6d5][b]Retiree[/b]: [/color]
            Been promoted for more than 1 year; Have participated at least 2 Staff [b]Official[/b] Meetings; Have participated in Rules/FAQ modifying.
            To join [color=#009F00][b]VIP[/b]: [/color]
            If you don\'t meet the condition of joining [color=#1cc6d5][b]Retiree[/b][/color], you can join [color=#009F00][b]VIP[/b][/color] [b]unconditionally[/b].
            [b]for [color=#4b0082]Administrators[/color] and above: [/b]
            You can join [color=#1cc6d5][b]Retiree[/b][/color] [b]unconditionally[/b].
            ',
              ),
        ));
        
        
    }
}

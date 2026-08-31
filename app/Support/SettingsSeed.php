<?php

declare(strict_types=1);

namespace App\Support;

use App\Enums\UserClass as UserClassEnum;
use App\Models\Attendance;
use App\Models\User;
use App\Repositories\SearchBoxRepository;
use App\Repositories\StyleRepository;

/**
 * Generated settings seed migrated from `include/config.php`.
 *
 * Flattens the nested `Settings::get()` category arrays into the legacy
 * flat variables the rest of the tracker still expects in `SupportContext`.
 */
final class SettingsSeed
{
    public static function seed(): void
    {
        // load settings from database
        $settings = Settings::get();
        foreach ($settings as $name => $value) {
            app(Globals::class)->set(strtoupper($name), $value);
        }

        $BASIC = app(Globals::class)->get('BASIC', []);
        $MAIN = app(Globals::class)->get('MAIN', []);
        $ACCOUNT = app(Globals::class)->get('ACCOUNT', []);
        $ATTACHMENT = app(Globals::class)->get('ATTACHMENT', []);
        $AUTHORITY = app(Globals::class)->get('AUTHORITY', []);
        $BONUS = app(Globals::class)->get('BONUS', []);
        $CODE = app(Globals::class)->get('CODE', []);
        $SECURITY = app(Globals::class)->get('SECURITY', []);
        $SMTP = app(Globals::class)->get('SMTP', []);
        $TORRENT = app(Globals::class)->get('TORRENT', []);
        $TWEAK = app(Globals::class)->get('TWEAK', []);

        app(Globals::class)->set('SITENAME', $BASIC['SITENAME']);
        app(Globals::class)->set('BASEURL', $BASIC['BASEURL'] ?: (Input::serverValue('HTTP_HOST', 'localhost')));
        $BASEURL = app(Globals::class)->get('BASEURL', '');
        $announce_urls = [];
        $announce_urls[] = $BASIC['announce_url'] ?: ($BASEURL.DEFAULT_TRACKER_URI);

        app(Globals::class)->set('SITE_ONLINE', $MAIN['site_online']);
        app(Globals::class)->set('max_torrent_size', (int) $MAIN['max_torrent_size']);
        app(Globals::class)->set('announce_interval', (int) $MAIN['announce_interval']);
        app(Globals::class)->set('annintertwoage', (int) $MAIN['annintertwoage']);
        app(Globals::class)->set('annintertwo', (int) $MAIN['annintertwo']);
        app(Globals::class)->set('anninterthreeage', (int) $MAIN['anninterthreeage']);
        app(Globals::class)->set('anninterthree', (int) $MAIN['anninterthree']);
        app(Globals::class)->set('signup_timeout', $MAIN['signup_timeout']);
        app(Globals::class)->set('minoffervotes', $MAIN['minoffervotes']);
        app(Globals::class)->set('offervotetimeout_main', $MAIN['offervotetimeout']);
        app(Globals::class)->set('offeruptimeout_main', $MAIN['offeruptimeout']);
        app(Globals::class)->set('maxsubsize_main', $MAIN['maxsubsize']);
        app(Globals::class)->set('maxnewsnum_main', $MAIN['maxnewsnum']);
        app(Globals::class)->set('forumpostsperpage', $MAIN['postsperpage']);
        app(Globals::class)->set('forumtopicsperpage_main', $MAIN['topicsperpage']);
        app(Globals::class)->set('torrentsperpage_main', (int) $MAIN['torrentsperpage']);
        app(Globals::class)->set('max_dead_torrent_time', $MAIN['max_dead_torrent_time']);
        app(Globals::class)->set('maxusers', (int) $MAIN['maxusers']);
        app(Globals::class)->set('torrent_dir', $MAIN['torrent_dir']);
        app(Globals::class)->set('iniupload_main', $MAIN['iniupload']);
        app(Globals::class)->set('SITEEMAIL', $MAIN['SITEEMAIL']);
        app(Globals::class)->set('ACCOUNTANTID', (int) $MAIN['ACCOUNTANTID']);
        app(Globals::class)->set('ALIPAYACCOUNT', $MAIN['ALIPAYACCOUNT']);
        app(Globals::class)->set('PAYPALACCOUNT', $MAIN['PAYPALACCOUNT']);
        app(Globals::class)->set('SLOGAN', $MAIN['SLOGAN']);
        app(Globals::class)->set('icplicense_main', $MAIN['icplicense']);
        app(Globals::class)->set('autoclean_interval_one', $MAIN['autoclean_interval_one']);
        app(Globals::class)->set('autoclean_interval_two', $MAIN['autoclean_interval_two']);
        app(Globals::class)->set('autoclean_interval_three', $MAIN['autoclean_interval_three']);
        app(Globals::class)->set('autoclean_interval_four', $MAIN['autoclean_interval_four']);
        app(Globals::class)->set('autoclean_interval_five', $MAIN['autoclean_interval_five']);
        app(Globals::class)->set('REPORTMAIL', $MAIN['reportemail']);
        app(Globals::class)->set('invitesystem', $MAIN['invitesystem']);
        app(Globals::class)->set('registration', $MAIN['registration']);
        app(Globals::class)->set('enablenfo_main', $MAIN['enablenfo']);
        app(Globals::class)->set('showpolls_main', $MAIN['showpolls']);
        app(Globals::class)->set('showstats_main', $MAIN['showstats']);
        app(Globals::class)->set('showlastxforumposts_main', $MAIN['showlastxforumposts']);
        app(Globals::class)->set('showlastxtorrents_main', $MAIN['showlastxtorrents']);
        app(Globals::class)->set('showtrackerload', $MAIN['showtrackerload']);
        app(Globals::class)->set('showshoutbox_main', $MAIN['showshoutbox']);
        app(Globals::class)->set('enableoffer', $MAIN['showoffer']);
        app(Globals::class)->set('sptime', $MAIN['sptime']);
        app(Globals::class)->set('enablebitbucket_main', $MAIN['enablebitbucket']);
        app(Globals::class)->set('altname_main', $MAIN['altname'] ?? '');
        app(Globals::class)->set('deflang', $MAIN['defaultlang']);
        $firstStylesheetId = app(StyleRepository::class)->firstId() ?? 3;
        app(Globals::class)->set('defcss', (int) ($MAIN['defstylesheet'] ?: $firstStylesheetId));
        app(Globals::class)->set('enabledonation', $MAIN['donation']);
        $searchBoxIds = app(SearchBoxRepository::class)->getOrderedIds();
        $defaultBrowsecat = (int) ($searchBoxIds[0] ?? 1);
        app(Globals::class)->set('browsecatmode', (int) ($MAIN['browsecat'] ?? $defaultBrowsecat));
        app(Globals::class)->set('waitsystem', $MAIN['waitsystem']);
        app(Globals::class)->set('maxdlsystem', $MAIN['maxdlsystem']);
        app(Globals::class)->set('bitbucket', $MAIN['bitbucket']);
        app(Globals::class)->set('torrentnameprefix', $MAIN['torrentnameprefix']);
        app(Globals::class)->set('showforumstats_main', $MAIN['showforumstats']);
        app(Globals::class)->set('verification', $MAIN['verification']);
        app(Globals::class)->set('invite_count', $MAIN['invite_count']);
        app(Globals::class)->set('invite_timeout', $MAIN['invite_timeout']);
        app(Globals::class)->set('seeding_leeching_time_calc_start', $MAIN['seeding_leeching_time_calc_start']);
        app(Globals::class)->set('logo_main', $MAIN['logo']);

        app(Globals::class)->set('emailnotify_smtp', $SMTP['emailnotify']);
        app(Globals::class)->set('smtptype', $SMTP['smtptype']);
        app(Globals::class)->set('smtp_host', $SMTP['smtp_host']);
        app(Globals::class)->set('smtp_port', $SMTP['smtp_port']);
        app(Globals::class)->set('smtp_from', $SMTP['smtp_from']);
        app(Globals::class)->set('smtpaddress', $SMTP['smtpaddress'] ?? '');
        app(Globals::class)->set('smtpport', $SMTP['smtpport'] ?? '');
        app(Globals::class)->set('accountname', $SMTP['accountname'] ?? '');
        app(Globals::class)->set('accountpassword', $SMTP['accountpassword'] ?? '');

        app(Globals::class)->set('securelogin', $SECURITY['securelogin']);
        app(Globals::class)->set('securetracker', $SECURITY['securetracker']);
        $https_announce_urls = [];
        $https_announce_urls[] = $SECURITY['https_announce_url'];
        app(Globals::class)->set('iv', $SECURITY['iv']);
        app(Globals::class)->set('maxip', $SECURITY['maxip']);
        app(Globals::class)->set('maxloginattempts', $SECURITY['maxloginattempts']);
        app(Globals::class)->set('disableemailchange', $SECURITY['changeemail']);
        app(Globals::class)->set('cheaterdet_security', $SECURITY['cheaterdet']);
        app(Globals::class)->set('nodetect_security', $SECURITY['nodetect']);

        app(Globals::class)->set('defaultclass_class', isset(User::$classes[$AUTHORITY['defaultclass']]) ? $AUTHORITY['defaultclass'] : UserClassEnum::USER->value);
        app(Globals::class)->set('staffmem_class', $AUTHORITY['staffmem']);
        app(Globals::class)->set('newsmanage_class', $AUTHORITY['newsmanage']);
        app(Globals::class)->set('sbmanage_class', $AUTHORITY['sbmanage']);
        app(Globals::class)->set('pollmanage_class', $AUTHORITY['pollmanage']);
        app(Globals::class)->set('postmanage_class', $AUTHORITY['postmanage']);
        app(Globals::class)->set('commanage_class', $AUTHORITY['commanage']);
        app(Globals::class)->set('forummanage_class', $AUTHORITY['forummanage']);
        app(Globals::class)->set('viewuserlist_class', $AUTHORITY['viewuserlist']);
        app(Globals::class)->set('torrentmanage_class', $AUTHORITY['torrentmanage']);
        app(Globals::class)->set('torrentsticky_class', $AUTHORITY['torrentsticky']);
        app(Globals::class)->set('torrentonpromotion_class', $AUTHORITY['torrentonpromotion'] ?? '');
        app(Globals::class)->set('askreseed_class', $AUTHORITY['askreseed']);
        app(Globals::class)->set('viewnfo_class', $AUTHORITY['viewnfo']);
        app(Globals::class)->set('torrentstructure_class', $AUTHORITY['torrentstructure']);
        app(Globals::class)->set('sendinvite_class', $AUTHORITY['sendinvite']);
        app(Globals::class)->set('viewhistory_class', $AUTHORITY['viewhistory']);
        app(Globals::class)->set('topten_class', $AUTHORITY['topten']);
        app(Globals::class)->set('log_class', $AUTHORITY['log']);
        app(Globals::class)->set('confilog_class', $AUTHORITY['confilog']);
        app(Globals::class)->set('userprofile_class', $AUTHORITY['userprofile']);
        app(Globals::class)->set('torrenthistory_class', $AUTHORITY['torrenthistory']);
        app(Globals::class)->set('prfmanage_class', $AUTHORITY['prfmanage']);
        app(Globals::class)->set('cruprfmanage_class', $AUTHORITY['cruprfmanage']);
        app(Globals::class)->set('uploadsub_class', $AUTHORITY['uploadsub']);
        app(Globals::class)->set('delownsub_class', $AUTHORITY['delownsub']);
        app(Globals::class)->set('submanage_class', $AUTHORITY['submanage']);
        app(Globals::class)->set('updateextinfo_class', $AUTHORITY['updateextinfo']);
        app(Globals::class)->set('viewanonymous_class', $AUTHORITY['viewanonymous']);
        app(Globals::class)->set('beanonymous_class', $AUTHORITY['beanonymous']);
        app(Globals::class)->set('addoffer_class', $AUTHORITY['addoffer']);
        app(Globals::class)->set('offermanage_class', $AUTHORITY['offermanage']);
        app(Globals::class)->set('upload_class', $AUTHORITY['upload']);
        app(Globals::class)->set('movetorrent_class', $AUTHORITY['movetorrent']);
        app(Globals::class)->set('chrmanage_class', $AUTHORITY['chrmanage']);
        app(Globals::class)->set('viewinvite_class', $AUTHORITY['viewinvite']);
        app(Globals::class)->set('buyinvite_class', $AUTHORITY['buyinvite']);
        app(Globals::class)->set('seebanned_class', $AUTHORITY['seebanned']);
        app(Globals::class)->set('againstoffer_class', $AUTHORITY['againstoffer']);
        app(Globals::class)->set('userbar_class', $AUTHORITY['userbar']);

        app(Globals::class)->set('where_tweak', $TWEAK['where']);
        app(Globals::class)->set('iplog1', $TWEAK['iplog1']);
        app(Globals::class)->set('bonus_tweak', $TWEAK['bonus']);
        app(Globals::class)->set('titlekeywords_tweak', $TWEAK['titlekeywords']);
        app(Globals::class)->set('metakeywords_tweak', $TWEAK['metakeywords']);
        app(Globals::class)->set('metadescription_tweak', $TWEAK['metadescription']);
        app(Globals::class)->set('datefounded', $TWEAK['datefounded']);
        app(Globals::class)->set('enablelocation_tweak', $TWEAK['enablelocation']);
        app(Globals::class)->set('enablesqldebug_tweak', $TWEAK['enablesqldebug']);
        app(Globals::class)->set('sqldebug_tweak', $TWEAK['sqldebug']);
        app(Globals::class)->set('cssdate_tweak', $TWEAK['cssdate']);
        app(Globals::class)->set('enabletooltip_tweak', $TWEAK['enabletooltip']);
        app(Globals::class)->set('analyticscode_tweak', $TWEAK['analyticscode']);

        app(Globals::class)->set('enableattach_attachment', $ATTACHMENT['enableattach']);
        app(Globals::class)->set('classone_attachment', $ATTACHMENT['classone']);
        app(Globals::class)->set('countone_attachment', $ATTACHMENT['countone']);
        app(Globals::class)->set('sizeone_attachment', $ATTACHMENT['sizeone']);
        app(Globals::class)->set('extone_attachment', $ATTACHMENT['extone']);
        app(Globals::class)->set('classtwo_attachment', $ATTACHMENT['classtwo']);
        app(Globals::class)->set('counttwo_attachment', $ATTACHMENT['counttwo']);
        app(Globals::class)->set('sizetwo_attachment', $ATTACHMENT['sizetwo']);
        app(Globals::class)->set('exttwo_attachment', $ATTACHMENT['exttwo']);
        app(Globals::class)->set('classthree_attachment', $ATTACHMENT['classthree']);
        app(Globals::class)->set('countthree_attachment', $ATTACHMENT['countthree']);
        app(Globals::class)->set('sizethree_attachment', $ATTACHMENT['sizethree']);
        app(Globals::class)->set('extthree_attachment', $ATTACHMENT['extthree']);
        app(Globals::class)->set('classfour_attachment', $ATTACHMENT['classfour']);
        app(Globals::class)->set('countfour_attachment', $ATTACHMENT['countfour']);
        app(Globals::class)->set('sizefour_attachment', $ATTACHMENT['sizefour']);
        app(Globals::class)->set('extfour_attachment', $ATTACHMENT['extfour']);
        app(Globals::class)->set('savedirectory_attachment', $ATTACHMENT['savedirectory']);
        app(Globals::class)->set('httpdirectory_attachment', $ATTACHMENT['httpdirectory']);
        app(Globals::class)->set('savedirectorytype_attachment', $ATTACHMENT['savedirectorytype']);
        app(Globals::class)->set('thumbnailtype_attachment', $ATTACHMENT['thumbnailtype']);
        app(Globals::class)->set('thumbquality_attachment', $ATTACHMENT['thumbquality']);
        app(Globals::class)->set('thumbwidth_attachment', $ATTACHMENT['thumbwidth']);
        app(Globals::class)->set('thumbheight_attachment', $ATTACHMENT['thumbheight']);
        app(Globals::class)->set('watermarkpos_attachment', $ATTACHMENT['watermarkpos']);
        app(Globals::class)->set('watermarkwidth_attachment', $ATTACHMENT['watermarkwidth']);
        app(Globals::class)->set('watermarkheight_attachment', $ATTACHMENT['watermarkheight']);
        app(Globals::class)->set('watermarkquality_attachment', $ATTACHMENT['watermarkquality']);
        app(Globals::class)->set('altthumbwidth_attachment', $ATTACHMENT['altthumbwidth']);
        app(Globals::class)->set('altthumbheight_attachment', $ATTACHMENT['altthumbheight']);

        app(Globals::class)->set('mainversion_code', $CODE['mainversion']);
        app(Globals::class)->set('subversion_code', $CODE['subversion']);
        app(Globals::class)->set('releasedate_code', $CODE['releasedate']);
        app(Globals::class)->set('website_code', $CODE['website']);

        // The BONUS array comes from the database settings cache. Provide an empty
        // fallback so missing/uncached bonus keys do not emit undefined-variable
        // warnings when downstream pages (delete, fastdelete, mybonus) use them.

        app(Globals::class)->set('donortimes_bonus', $BONUS['donortimes'] ?? 0);
        app(Globals::class)->set('perseeding_bonus', $BONUS['perseeding'] ?? 0);
        app(Globals::class)->set('maxseeding_bonus', $BONUS['maxseeding'] ?? 0);
        app(Globals::class)->set('tzero_bonus', $BONUS['tzero'] ?? 0);
        app(Globals::class)->set('nzero_bonus', $BONUS['nzero'] ?? 0);
        app(Globals::class)->set('bzero_bonus', $BONUS['bzero'] ?? 0);
        app(Globals::class)->set('l_bonus', $BONUS['l'] ?? 0);
        app(Globals::class)->set('uploadtorrent_bonus', $BONUS['uploadtorrent'] ?? 0);
        app(Globals::class)->set('starttopic_bonus', $BONUS['starttopic'] ?? 0);
        app(Globals::class)->set('makepost_bonus', $BONUS['makepost'] ?? 0);
        app(Globals::class)->set('addcomment_bonus', $BONUS['addcomment'] ?? 0);
        app(Globals::class)->set('pollvote_bonus', $BONUS['pollvote'] ?? 0);
        app(Globals::class)->set('offervote_bonus', $BONUS['offervote'] ?? 0);
        app(Globals::class)->set('saythanks_bonus', $BONUS['saythanks'] ?? 0);
        app(Globals::class)->set('receivethanks_bonus', $BONUS['receivethanks'] ?? 0);
        app(Globals::class)->set('onegbupload_bonus', $BONUS['onegbupload'] ?? 0);
        app(Globals::class)->set('fivegbupload_bonus', $BONUS['fivegbupload'] ?? 0);
        app(Globals::class)->set('tengbupload_bonus', $BONUS['tengbupload'] ?? 0);
        app(Globals::class)->set('ratiolimit_bonus', $BONUS['ratiolimit'] ?? 0);
        app(Globals::class)->set('dlamountlimit_bonus', $BONUS['dlamountlimit'] ?? 0);
        app(Globals::class)->set('oneinvite_bonus', $BONUS['oneinvite'] ?? 0);
        app(Globals::class)->set('customtitle_bonus', $BONUS['customtitle'] ?? 0);
        app(Globals::class)->set('vipstatus_bonus', $BONUS['vipstatus'] ?? 0);
        app(Globals::class)->set('bonusgift_bonus', $BONUS['bonusgift'] ?? 0);
        app(Globals::class)->set('basictax_bonus', $BONUS['basictax'] ?? 0);
        app(Globals::class)->set('taxpercentage_bonus', $BONUS['taxpercentage'] ?? 0);
        app(Globals::class)->set('attendance_initial_bonus', isset($BONUS['attendance_initial']) ? (int) $BONUS['attendance_initial'] : Attendance::INITIAL_BONUS);
        app(Globals::class)->set('attendance_step_bonus', isset($BONUS['attendance_step']) ? (int) $BONUS['attendance_step'] : Attendance::STEP_BONUS);
        app(Globals::class)->set('attendance_max_bonus', isset($BONUS['attendance_max']) ? (int) $BONUS['attendance_max'] : Attendance::MAX_BONUS);
        app(Globals::class)->set('attendance_continuous_bonus', isset($BONUS['attendance_continuous']) && is_array($BONUS['attendance_continuous']) ? $BONUS['attendance_continuous'] : Attendance::CONTINUOUS_BONUS);

        app(Globals::class)->set('neverdelete_account', $ACCOUNT['neverdelete']);
        app(Globals::class)->set('neverdeletepacked_account', $ACCOUNT['neverdeletepacked']);
        app(Globals::class)->set('deletepacked_account', $ACCOUNT['deletepacked']);
        app(Globals::class)->set('deleteunpacked_account', $ACCOUNT['deleteunpacked']);
        app(Globals::class)->set('deletenotransfer_account', $ACCOUNT['deletenotransfer']);
        app(Globals::class)->set('deletenotransfertwo_account', $ACCOUNT['deletenotransfertwo']);
        app(Globals::class)->set('deletepeasant_account', $ACCOUNT['deletepeasant']);
        app(Globals::class)->set('psdlone_account', $ACCOUNT['psdlone']);
        app(Globals::class)->set('psratioone_account', $ACCOUNT['psratioone']);
        app(Globals::class)->set('psdltwo_account', $ACCOUNT['psdltwo']);
        app(Globals::class)->set('psratiotwo_account', $ACCOUNT['psratiotwo']);
        app(Globals::class)->set('psdlthree_account', $ACCOUNT['psdlthree']);
        app(Globals::class)->set('psratiothree_account', $ACCOUNT['psratiothree']);
        app(Globals::class)->set('psdlfour_account', $ACCOUNT['psdlfour']);
        app(Globals::class)->set('psratiofour_account', $ACCOUNT['psratiofour']);
        app(Globals::class)->set('psdlfive_account', $ACCOUNT['psdlfive']);
        app(Globals::class)->set('psratiofive_account', $ACCOUNT['psratiofive']);
        app(Globals::class)->set('putime_account', $ACCOUNT['putime']);
        app(Globals::class)->set('pudl_account', $ACCOUNT['pudl']);
        app(Globals::class)->set('puprratio_account', $ACCOUNT['puprratio']);
        app(Globals::class)->set('puderatio_account', $ACCOUNT['puderatio']);
        app(Globals::class)->set('eutime_account', $ACCOUNT['eutime']);
        app(Globals::class)->set('eudl_account', $ACCOUNT['eudl']);
        app(Globals::class)->set('euprratio_account', $ACCOUNT['euprratio']);
        app(Globals::class)->set('euderatio_account', $ACCOUNT['euderatio']);
        app(Globals::class)->set('cutime_account', $ACCOUNT['cutime']);
        app(Globals::class)->set('cudl_account', $ACCOUNT['cudl']);
        app(Globals::class)->set('cuprratio_account', $ACCOUNT['cuprratio']);
        app(Globals::class)->set('cuderatio_account', $ACCOUNT['cuderatio']);
        app(Globals::class)->set('iutime_account', $ACCOUNT['iutime']);
        app(Globals::class)->set('iudl_account', $ACCOUNT['iudl']);
        app(Globals::class)->set('iuprratio_account', $ACCOUNT['iuprratio']);
        app(Globals::class)->set('iuderatio_account', $ACCOUNT['iuderatio']);
        app(Globals::class)->set('vutime_account', $ACCOUNT['vutime']);
        app(Globals::class)->set('vudl_account', $ACCOUNT['vudl']);
        app(Globals::class)->set('vuprratio_account', $ACCOUNT['vuprratio']);
        app(Globals::class)->set('vuderatio_account', $ACCOUNT['vuderatio']);
        app(Globals::class)->set('exutime_account', $ACCOUNT['exutime']);
        app(Globals::class)->set('exudl_account', $ACCOUNT['exudl']);
        app(Globals::class)->set('exuprratio_account', $ACCOUNT['exuprratio']);
        app(Globals::class)->set('exuderatio_account', $ACCOUNT['exuderatio']);
        app(Globals::class)->set('uutime_account', $ACCOUNT['uutime']);
        app(Globals::class)->set('uudl_account', $ACCOUNT['uudl']);
        app(Globals::class)->set('uuprratio_account', $ACCOUNT['uuprratio']);
        app(Globals::class)->set('uuderatio_account', $ACCOUNT['uuderatio']);
        app(Globals::class)->set('nmtime_account', $ACCOUNT['nmtime']);
        app(Globals::class)->set('nmdl_account', $ACCOUNT['nmdl']);
        app(Globals::class)->set('nmprratio_account', $ACCOUNT['nmprratio']);
        app(Globals::class)->set('nmderatio_account', $ACCOUNT['nmderatio']);
        app(Globals::class)->set('getInvitesByPromotion_class', $ACCOUNT['getInvitesByPromotion']);

        app(Globals::class)->set('prorules_torrent', $TORRENT['prorules']);
        app(Globals::class)->set('randomhalfleech_torrent', $TORRENT['randomhalfleech']);
        app(Globals::class)->set('randomfree_torrent', $TORRENT['randomfree']);
        app(Globals::class)->set('randomtwoup_torrent', $TORRENT['randomtwoup']);
        app(Globals::class)->set('randomtwoupfree_torrent', $TORRENT['randomtwoupfree']);
        app(Globals::class)->set('randomtwouphalfdown_torrent', $TORRENT['randomtwouphalfdown']);
        app(Globals::class)->set('randomthirtypercentdown_torrent', $TORRENT['randomthirtypercentdown']);
        app(Globals::class)->set('largesize_torrent', (int) $TORRENT['largesize']);
        app(Globals::class)->set('largepro_torrent', $TORRENT['largepro']);
        app(Globals::class)->set('expirehalfleech_torrent', $TORRENT['expirehalfleech']);
        app(Globals::class)->set('expirefree_torrent', $TORRENT['expirefree']);
        app(Globals::class)->set('expiretwoup_torrent', $TORRENT['expiretwoup']);
        app(Globals::class)->set('expiretwoupfree_torrent', $TORRENT['expiretwoupfree']);
        app(Globals::class)->set('expiretwouphalfleech_torrent', $TORRENT['expiretwouphalfleech']);
        app(Globals::class)->set('expirethirtypercentleech_torrent', $TORRENT['expirethirtypercentleech']);
        app(Globals::class)->set('expirenormal_torrent', $TORRENT['expirenormal']);
        app(Globals::class)->set('hotdays_torrent', $TORRENT['hotdays']);
        app(Globals::class)->set('hotseeder_torrent', $TORRENT['hotseeder']);
        app(Globals::class)->set('halfleechbecome_torrent', $TORRENT['halfleechbecome']);
        app(Globals::class)->set('freebecome_torrent', $TORRENT['freebecome']);
        app(Globals::class)->set('twoupbecome_torrent', $TORRENT['twoupbecome']);
        app(Globals::class)->set('twoupfreebecome_torrent', $TORRENT['twoupfreebecome']);
        app(Globals::class)->set('twouphalfleechbecome_torrent', $TORRENT['twouphalfleechbecome']);
        app(Globals::class)->set('thirtypercentleechbecome_torrent', $TORRENT['thirtypercentleechbecome']);
        app(Globals::class)->set('normalbecome_torrent', $TORRENT['normalbecome']);
        app(Globals::class)->set('uploaderdouble_torrent', $TORRENT['uploaderdouble']);
        app(Globals::class)->set('deldeadtorrent_torrent', $TORRENT['deldeadtorrent']);

        // Directory for subs
        app(Globals::class)->set('SUBSPATH', 'subs');
        // Whether clean-up is triggered by cron, instead of the default browser clicks.
        // Set this to true ONLY if you have setup other method to schedule the clean-up process.
        // e.g. cron on *nix, add the following line (without "") in your crontab file
        // "*/5 * * * * wget -O - -q -t 1 https://nexusphp.org/cron.php"
        // NOTE:
        // Make sure you have wget installed on your OS
        // replace "https://nexusphp.org/" with your own site address

        app(Globals::class)->set('useCronTriggerCleanUp', true);
        app(Globals::class)->set('promotionrules_torrent', []);
        app(Globals::class)->set('announce_urls', $announce_urls);
        app(Globals::class)->set('https_announce_urls', $https_announce_urls);
    }
}

<?php

declare(strict_types=1);

namespace App\Support;

use App\Contracts\Repositories\SearchBoxRepositoryInterface;
use App\Enums\UserClass as UserClassEnum;
use App\Models\Attendance;
use App\Models\User;
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
        $globals = app(Globals::class);

        // load settings from database
        $settings = Settings::get();
        foreach ($settings as $name => $value) {
            $globals->set(strtoupper($name), $value);
        }

        $BASIC = $globals->get('BASIC', []);
        $MAIN = $globals->get('MAIN', []);
        $ACCOUNT = $globals->get('ACCOUNT', []);
        $ATTACHMENT = $globals->get('ATTACHMENT', []);
        $AUTHORITY = $globals->get('AUTHORITY', []);
        $BONUS = $globals->get('BONUS', []);
        $CODE = $globals->get('CODE', []);
        $SECURITY = $globals->get('SECURITY', []);
        $SMTP = $globals->get('SMTP', []);
        $TORRENT = $globals->get('TORRENT', []);
        $TWEAK = $globals->get('TWEAK', []);

        $globals->set('SITENAME', $BASIC['SITENAME']);
        $globals->set('BASEURL', $BASIC['BASEURL'] ?: (Input::serverValue('HTTP_HOST', 'localhost')));
        $BASEURL = $globals->get('BASEURL', '');
        $announce_urls = [];
        $announce_urls[] = $BASIC['announce_url'] ?: ($BASEURL.DEFAULT_TRACKER_URI);

        $globals->set('SITE_ONLINE', $MAIN['site_online']);
        $globals->set('max_torrent_size', (int) $MAIN['max_torrent_size']);
        $globals->set('announce_interval', (int) $MAIN['announce_interval']);
        $globals->set('annintertwoage', (int) $MAIN['annintertwoage']);
        $globals->set('annintertwo', (int) $MAIN['annintertwo']);
        $globals->set('anninterthreeage', (int) $MAIN['anninterthreeage']);
        $globals->set('anninterthree', (int) $MAIN['anninterthree']);
        $globals->set('signup_timeout', $MAIN['signup_timeout']);
        $globals->set('minoffervotes', $MAIN['minoffervotes']);
        $globals->set('offervotetimeout_main', $MAIN['offervotetimeout']);
        $globals->set('offeruptimeout_main', $MAIN['offeruptimeout']);
        $globals->set('maxsubsize_main', $MAIN['maxsubsize']);
        $globals->set('maxnewsnum_main', $MAIN['maxnewsnum']);
        $globals->set('forumpostsperpage', $MAIN['postsperpage']);
        $globals->set('forumtopicsperpage_main', $MAIN['topicsperpage']);
        $globals->set('torrentsperpage_main', (int) $MAIN['torrentsperpage']);
        $globals->set('max_dead_torrent_time', $MAIN['max_dead_torrent_time']);
        $globals->set('maxusers', (int) $MAIN['maxusers']);
        $globals->set('torrent_dir', $MAIN['torrent_dir']);
        $globals->set('iniupload_main', $MAIN['iniupload']);
        $globals->set('SITEEMAIL', $MAIN['SITEEMAIL']);
        $globals->set('ACCOUNTANTID', (int) $MAIN['ACCOUNTANTID']);
        $globals->set('ALIPAYACCOUNT', $MAIN['ALIPAYACCOUNT']);
        $globals->set('PAYPALACCOUNT', $MAIN['PAYPALACCOUNT']);
        $globals->set('SLOGAN', $MAIN['SLOGAN']);
        $globals->set('icplicense_main', $MAIN['icplicense']);
        $globals->set('autoclean_interval_one', $MAIN['autoclean_interval_one']);
        $globals->set('autoclean_interval_two', $MAIN['autoclean_interval_two']);
        $globals->set('autoclean_interval_three', $MAIN['autoclean_interval_three']);
        $globals->set('autoclean_interval_four', $MAIN['autoclean_interval_four']);
        $globals->set('autoclean_interval_five', $MAIN['autoclean_interval_five']);
        $globals->set('REPORTMAIL', $MAIN['reportemail']);
        $globals->set('invitesystem', $MAIN['invitesystem']);
        $globals->set('registration', $MAIN['registration']);
        $globals->set('enablenfo_main', $MAIN['enablenfo']);
        $globals->set('showpolls_main', $MAIN['showpolls']);
        $globals->set('showstats_main', $MAIN['showstats']);
        $globals->set('showlastxforumposts_main', $MAIN['showlastxforumposts']);
        $globals->set('showlastxtorrents_main', $MAIN['showlastxtorrents']);
        $globals->set('showtrackerload', $MAIN['showtrackerload']);
        $globals->set('showshoutbox_main', $MAIN['showshoutbox']);
        $globals->set('enableoffer', $MAIN['showoffer']);
        $globals->set('sptime', $MAIN['sptime']);
        $globals->set('enablebitbucket_main', $MAIN['enablebitbucket']);
        $globals->set('altname_main', $MAIN['altname'] ?? '');
        $globals->set('deflang', $MAIN['defaultlang']);
        $firstStylesheetId = app(StyleRepository::class)->firstId() ?? 3;
        $globals->set('defcss', (int) ($MAIN['defstylesheet'] ?: $firstStylesheetId));
        $globals->set('enabledonation', $MAIN['donation']);
        $searchBoxIds = app(SearchBoxRepositoryInterface::class)->getOrderedIds();
        $defaultBrowsecat = (int) ($searchBoxIds[0] ?? 1);
        $globals->set('browsecatmode', (int) ($MAIN['browsecat'] ?? $defaultBrowsecat));
        $globals->set('waitsystem', $MAIN['waitsystem']);
        $globals->set('maxdlsystem', $MAIN['maxdlsystem']);
        $globals->set('bitbucket', $MAIN['bitbucket']);
        $globals->set('torrentnameprefix', $MAIN['torrentnameprefix']);
        $globals->set('showforumstats_main', $MAIN['showforumstats']);
        $globals->set('verification', $MAIN['verification']);
        $globals->set('invite_count', $MAIN['invite_count']);
        $globals->set('invite_timeout', $MAIN['invite_timeout']);
        $globals->set('seeding_leeching_time_calc_start', $MAIN['seeding_leeching_time_calc_start']);
        $globals->set('logo_main', $MAIN['logo']);

        $globals->set('emailnotify_smtp', $SMTP['emailnotify']);
        $globals->set('smtptype', $SMTP['smtptype']);
        $globals->set('smtp_host', $SMTP['smtp_host']);
        $globals->set('smtp_port', $SMTP['smtp_port']);
        $globals->set('smtp_from', $SMTP['smtp_from']);
        $globals->set('smtpaddress', $SMTP['smtpaddress'] ?? '');
        $globals->set('smtpport', $SMTP['smtpport'] ?? '');
        $globals->set('accountname', $SMTP['accountname'] ?? '');
        $globals->set('accountpassword', $SMTP['accountpassword'] ?? '');

        $globals->set('securelogin', $SECURITY['securelogin']);
        $globals->set('securetracker', $SECURITY['securetracker']);
        $https_announce_urls = [];
        $https_announce_urls[] = $SECURITY['https_announce_url'];
        $globals->set('iv', $SECURITY['iv']);
        $globals->set('maxip', $SECURITY['maxip']);
        $globals->set('maxloginattempts', $SECURITY['maxloginattempts']);
        $globals->set('disableemailchange', $SECURITY['changeemail']);
        $globals->set('cheaterdet_security', $SECURITY['cheaterdet']);
        $globals->set('nodetect_security', $SECURITY['nodetect']);

        $globals->set('defaultclass_class', isset(User::$classes[$AUTHORITY['defaultclass']]) ? $AUTHORITY['defaultclass'] : UserClassEnum::USER->value);
        $globals->set('staffmem_class', $AUTHORITY['staffmem']);
        $globals->set('newsmanage_class', $AUTHORITY['newsmanage']);
        $globals->set('sbmanage_class', $AUTHORITY['sbmanage']);
        $globals->set('pollmanage_class', $AUTHORITY['pollmanage']);
        $globals->set('postmanage_class', $AUTHORITY['postmanage']);
        $globals->set('commanage_class', $AUTHORITY['commanage']);
        $globals->set('forummanage_class', $AUTHORITY['forummanage']);
        $globals->set('viewuserlist_class', $AUTHORITY['viewuserlist']);
        $globals->set('torrentmanage_class', $AUTHORITY['torrentmanage']);
        $globals->set('torrentsticky_class', $AUTHORITY['torrentsticky']);
        $globals->set('torrentonpromotion_class', $AUTHORITY['torrentonpromotion'] ?? '');
        $globals->set('askreseed_class', $AUTHORITY['askreseed']);
        $globals->set('viewnfo_class', $AUTHORITY['viewnfo']);
        $globals->set('torrentstructure_class', $AUTHORITY['torrentstructure']);
        $globals->set('sendinvite_class', $AUTHORITY['sendinvite']);
        $globals->set('viewhistory_class', $AUTHORITY['viewhistory']);
        $globals->set('topten_class', $AUTHORITY['topten']);
        $globals->set('log_class', $AUTHORITY['log']);
        $globals->set('confilog_class', $AUTHORITY['confilog']);
        $globals->set('userprofile_class', $AUTHORITY['userprofile']);
        $globals->set('torrenthistory_class', $AUTHORITY['torrenthistory']);
        $globals->set('prfmanage_class', $AUTHORITY['prfmanage']);
        $globals->set('cruprfmanage_class', $AUTHORITY['cruprfmanage']);
        $globals->set('uploadsub_class', $AUTHORITY['uploadsub']);
        $globals->set('delownsub_class', $AUTHORITY['delownsub']);
        $globals->set('submanage_class', $AUTHORITY['submanage']);
        $globals->set('updateextinfo_class', $AUTHORITY['updateextinfo']);
        $globals->set('viewanonymous_class', $AUTHORITY['viewanonymous']);
        $globals->set('beanonymous_class', $AUTHORITY['beanonymous']);
        $globals->set('addoffer_class', $AUTHORITY['addoffer']);
        $globals->set('offermanage_class', $AUTHORITY['offermanage']);
        $globals->set('upload_class', $AUTHORITY['upload']);
        $globals->set('movetorrent_class', $AUTHORITY['movetorrent']);
        $globals->set('chrmanage_class', $AUTHORITY['chrmanage']);
        $globals->set('viewinvite_class', $AUTHORITY['viewinvite']);
        $globals->set('buyinvite_class', $AUTHORITY['buyinvite']);
        $globals->set('seebanned_class', $AUTHORITY['seebanned']);
        $globals->set('againstoffer_class', $AUTHORITY['againstoffer']);
        $globals->set('userbar_class', $AUTHORITY['userbar']);

        $globals->set('where_tweak', $TWEAK['where']);
        $globals->set('iplog1', $TWEAK['iplog1']);
        $globals->set('bonus_tweak', $TWEAK['bonus']);
        $globals->set('titlekeywords_tweak', $TWEAK['titlekeywords']);
        $globals->set('metakeywords_tweak', $TWEAK['metakeywords']);
        $globals->set('metadescription_tweak', $TWEAK['metadescription']);
        $globals->set('datefounded', $TWEAK['datefounded']);
        $globals->set('enablelocation_tweak', $TWEAK['enablelocation']);
        $globals->set('enablesqldebug_tweak', $TWEAK['enablesqldebug']);
        $globals->set('sqldebug_tweak', $TWEAK['sqldebug']);
        $globals->set('cssdate_tweak', $TWEAK['cssdate']);
        $globals->set('enabletooltip_tweak', $TWEAK['enabletooltip']);
        $globals->set('analyticscode_tweak', $TWEAK['analyticscode']);

        $globals->set('enableattach_attachment', $ATTACHMENT['enableattach']);
        $globals->set('classone_attachment', $ATTACHMENT['classone']);
        $globals->set('countone_attachment', $ATTACHMENT['countone']);
        $globals->set('sizeone_attachment', $ATTACHMENT['sizeone']);
        $globals->set('extone_attachment', $ATTACHMENT['extone']);
        $globals->set('classtwo_attachment', $ATTACHMENT['classtwo']);
        $globals->set('counttwo_attachment', $ATTACHMENT['counttwo']);
        $globals->set('sizetwo_attachment', $ATTACHMENT['sizetwo']);
        $globals->set('exttwo_attachment', $ATTACHMENT['exttwo']);
        $globals->set('classthree_attachment', $ATTACHMENT['classthree']);
        $globals->set('countthree_attachment', $ATTACHMENT['countthree']);
        $globals->set('sizethree_attachment', $ATTACHMENT['sizethree']);
        $globals->set('extthree_attachment', $ATTACHMENT['extthree']);
        $globals->set('classfour_attachment', $ATTACHMENT['classfour']);
        $globals->set('countfour_attachment', $ATTACHMENT['countfour']);
        $globals->set('sizefour_attachment', $ATTACHMENT['sizefour']);
        $globals->set('extfour_attachment', $ATTACHMENT['extfour']);
        $globals->set('savedirectory_attachment', $ATTACHMENT['savedirectory']);
        $globals->set('httpdirectory_attachment', $ATTACHMENT['httpdirectory']);
        $globals->set('savedirectorytype_attachment', $ATTACHMENT['savedirectorytype']);
        $globals->set('thumbnailtype_attachment', $ATTACHMENT['thumbnailtype']);
        $globals->set('thumbquality_attachment', $ATTACHMENT['thumbquality']);
        $globals->set('thumbwidth_attachment', $ATTACHMENT['thumbwidth']);
        $globals->set('thumbheight_attachment', $ATTACHMENT['thumbheight']);
        $globals->set('watermarkpos_attachment', $ATTACHMENT['watermarkpos']);
        $globals->set('watermarkwidth_attachment', $ATTACHMENT['watermarkwidth']);
        $globals->set('watermarkheight_attachment', $ATTACHMENT['watermarkheight']);
        $globals->set('watermarkquality_attachment', $ATTACHMENT['watermarkquality']);
        $globals->set('altthumbwidth_attachment', $ATTACHMENT['altthumbwidth']);
        $globals->set('altthumbheight_attachment', $ATTACHMENT['altthumbheight']);

        $globals->set('mainversion_code', $CODE['mainversion']);
        $globals->set('subversion_code', $CODE['subversion']);
        $globals->set('releasedate_code', $CODE['releasedate']);
        $globals->set('website_code', $CODE['website']);

        // The BONUS array comes from the database settings cache. Provide an empty
        // fallback so missing/uncached bonus keys do not emit undefined-variable
        // warnings when downstream pages (delete, fastdelete, mybonus) use them.

        $globals->set('donortimes_bonus', $BONUS['donortimes'] ?? 0);
        $globals->set('perseeding_bonus', $BONUS['perseeding'] ?? 0);
        $globals->set('maxseeding_bonus', $BONUS['maxseeding'] ?? 0);
        $globals->set('tzero_bonus', $BONUS['tzero'] ?? 0);
        $globals->set('nzero_bonus', $BONUS['nzero'] ?? 0);
        $globals->set('bzero_bonus', $BONUS['bzero'] ?? 0);
        $globals->set('l_bonus', $BONUS['l'] ?? 0);
        $globals->set('uploadtorrent_bonus', $BONUS['uploadtorrent'] ?? 0);
        $globals->set('starttopic_bonus', $BONUS['starttopic'] ?? 0);
        $globals->set('makepost_bonus', $BONUS['makepost'] ?? 0);
        $globals->set('addcomment_bonus', $BONUS['addcomment'] ?? 0);
        $globals->set('pollvote_bonus', $BONUS['pollvote'] ?? 0);
        $globals->set('offervote_bonus', $BONUS['offervote'] ?? 0);
        $globals->set('saythanks_bonus', $BONUS['saythanks'] ?? 0);
        $globals->set('receivethanks_bonus', $BONUS['receivethanks'] ?? 0);
        $globals->set('onegbupload_bonus', $BONUS['onegbupload'] ?? 0);
        $globals->set('fivegbupload_bonus', $BONUS['fivegbupload'] ?? 0);
        $globals->set('tengbupload_bonus', $BONUS['tengbupload'] ?? 0);
        $globals->set('ratiolimit_bonus', $BONUS['ratiolimit'] ?? 0);
        $globals->set('dlamountlimit_bonus', $BONUS['dlamountlimit'] ?? 0);
        $globals->set('oneinvite_bonus', $BONUS['oneinvite'] ?? 0);
        $globals->set('customtitle_bonus', $BONUS['customtitle'] ?? 0);
        $globals->set('vipstatus_bonus', $BONUS['vipstatus'] ?? 0);
        $globals->set('bonusgift_bonus', $BONUS['bonusgift'] ?? 0);
        $globals->set('basictax_bonus', $BONUS['basictax'] ?? 0);
        $globals->set('taxpercentage_bonus', $BONUS['taxpercentage'] ?? 0);
        $globals->set('attendance_initial_bonus', isset($BONUS['attendance_initial']) ? (int) $BONUS['attendance_initial'] : Attendance::INITIAL_BONUS);
        $globals->set('attendance_step_bonus', isset($BONUS['attendance_step']) ? (int) $BONUS['attendance_step'] : Attendance::STEP_BONUS);
        $globals->set('attendance_max_bonus', isset($BONUS['attendance_max']) ? (int) $BONUS['attendance_max'] : Attendance::MAX_BONUS);
        $globals->set('attendance_continuous_bonus', isset($BONUS['attendance_continuous']) && is_array($BONUS['attendance_continuous']) ? $BONUS['attendance_continuous'] : Attendance::CONTINUOUS_BONUS);

        $globals->set('neverdelete_account', $ACCOUNT['neverdelete']);
        $globals->set('neverdeletepacked_account', $ACCOUNT['neverdeletepacked']);
        $globals->set('deletepacked_account', $ACCOUNT['deletepacked']);
        $globals->set('deleteunpacked_account', $ACCOUNT['deleteunpacked']);
        $globals->set('deletenotransfer_account', $ACCOUNT['deletenotransfer']);
        $globals->set('deletenotransfertwo_account', $ACCOUNT['deletenotransfertwo']);
        $globals->set('deletepeasant_account', $ACCOUNT['deletepeasant']);
        $globals->set('psdlone_account', $ACCOUNT['psdlone']);
        $globals->set('psratioone_account', $ACCOUNT['psratioone']);
        $globals->set('psdltwo_account', $ACCOUNT['psdltwo']);
        $globals->set('psratiotwo_account', $ACCOUNT['psratiotwo']);
        $globals->set('psdlthree_account', $ACCOUNT['psdlthree']);
        $globals->set('psratiothree_account', $ACCOUNT['psratiothree']);
        $globals->set('psdlfour_account', $ACCOUNT['psdlfour']);
        $globals->set('psratiofour_account', $ACCOUNT['psratiofour']);
        $globals->set('psdlfive_account', $ACCOUNT['psdlfive']);
        $globals->set('psratiofive_account', $ACCOUNT['psratiofive']);
        $globals->set('putime_account', $ACCOUNT['putime']);
        $globals->set('pudl_account', $ACCOUNT['pudl']);
        $globals->set('puprratio_account', $ACCOUNT['puprratio']);
        $globals->set('puderatio_account', $ACCOUNT['puderatio']);
        $globals->set('eutime_account', $ACCOUNT['eutime']);
        $globals->set('eudl_account', $ACCOUNT['eudl']);
        $globals->set('euprratio_account', $ACCOUNT['euprratio']);
        $globals->set('euderatio_account', $ACCOUNT['euderatio']);
        $globals->set('cutime_account', $ACCOUNT['cutime']);
        $globals->set('cudl_account', $ACCOUNT['cudl']);
        $globals->set('cuprratio_account', $ACCOUNT['cuprratio']);
        $globals->set('cuderatio_account', $ACCOUNT['cuderatio']);
        $globals->set('iutime_account', $ACCOUNT['iutime']);
        $globals->set('iudl_account', $ACCOUNT['iudl']);
        $globals->set('iuprratio_account', $ACCOUNT['iuprratio']);
        $globals->set('iuderatio_account', $ACCOUNT['iuderatio']);
        $globals->set('vutime_account', $ACCOUNT['vutime']);
        $globals->set('vudl_account', $ACCOUNT['vudl']);
        $globals->set('vuprratio_account', $ACCOUNT['vuprratio']);
        $globals->set('vuderatio_account', $ACCOUNT['vuderatio']);
        $globals->set('exutime_account', $ACCOUNT['exutime']);
        $globals->set('exudl_account', $ACCOUNT['exudl']);
        $globals->set('exuprratio_account', $ACCOUNT['exuprratio']);
        $globals->set('exuderatio_account', $ACCOUNT['exuderatio']);
        $globals->set('uutime_account', $ACCOUNT['uutime']);
        $globals->set('uudl_account', $ACCOUNT['uudl']);
        $globals->set('uuprratio_account', $ACCOUNT['uuprratio']);
        $globals->set('uuderatio_account', $ACCOUNT['uuderatio']);
        $globals->set('nmtime_account', $ACCOUNT['nmtime']);
        $globals->set('nmdl_account', $ACCOUNT['nmdl']);
        $globals->set('nmprratio_account', $ACCOUNT['nmprratio']);
        $globals->set('nmderatio_account', $ACCOUNT['nmderatio']);
        $globals->set('getInvitesByPromotion_class', $ACCOUNT['getInvitesByPromotion']);

        $globals->set('prorules_torrent', $TORRENT['prorules']);
        $globals->set('randomhalfleech_torrent', $TORRENT['randomhalfleech']);
        $globals->set('randomfree_torrent', $TORRENT['randomfree']);
        $globals->set('randomtwoup_torrent', $TORRENT['randomtwoup']);
        $globals->set('randomtwoupfree_torrent', $TORRENT['randomtwoupfree']);
        $globals->set('randomtwouphalfdown_torrent', $TORRENT['randomtwouphalfdown']);
        $globals->set('randomthirtypercentdown_torrent', $TORRENT['randomthirtypercentdown']);
        $globals->set('largesize_torrent', (int) $TORRENT['largesize']);
        $globals->set('largepro_torrent', $TORRENT['largepro']);
        $globals->set('expirehalfleech_torrent', $TORRENT['expirehalfleech']);
        $globals->set('expirefree_torrent', $TORRENT['expirefree']);
        $globals->set('expiretwoup_torrent', $TORRENT['expiretwoup']);
        $globals->set('expiretwoupfree_torrent', $TORRENT['expiretwoupfree']);
        $globals->set('expiretwouphalfleech_torrent', $TORRENT['expiretwouphalfleech']);
        $globals->set('expirethirtypercentleech_torrent', $TORRENT['expirethirtypercentleech']);
        $globals->set('expirenormal_torrent', $TORRENT['expirenormal']);
        $globals->set('hotdays_torrent', $TORRENT['hotdays']);
        $globals->set('hotseeder_torrent', $TORRENT['hotseeder']);
        $globals->set('halfleechbecome_torrent', $TORRENT['halfleechbecome']);
        $globals->set('freebecome_torrent', $TORRENT['freebecome']);
        $globals->set('twoupbecome_torrent', $TORRENT['twoupbecome']);
        $globals->set('twoupfreebecome_torrent', $TORRENT['twoupfreebecome']);
        $globals->set('twouphalfleechbecome_torrent', $TORRENT['twouphalfleechbecome']);
        $globals->set('thirtypercentleechbecome_torrent', $TORRENT['thirtypercentleechbecome']);
        $globals->set('normalbecome_torrent', $TORRENT['normalbecome']);
        $globals->set('uploaderdouble_torrent', $TORRENT['uploaderdouble']);
        $globals->set('deldeadtorrent_torrent', $TORRENT['deldeadtorrent']);

        // Directory for subs
        $globals->set('SUBSPATH', 'subs');
        // Whether clean-up is triggered by cron, instead of the default browser clicks.
        // Set this to true ONLY if you have setup other method to schedule the clean-up process.
        // e.g. cron on *nix, add the following line (without "") in your crontab file
        // "*/5 * * * * wget -O - -q -t 1 https://nexusphp.org/cron.php"
        // NOTE:
        // Make sure you have wget installed on your OS
        // replace "https://nexusphp.org/" with your own site address

        $globals->set('useCronTriggerCleanUp', true);
        $globals->set('promotionrules_torrent', []);
        $globals->set('announce_urls', $announce_urls);
        $globals->set('https_announce_urls', $https_announce_urls);
    }
}

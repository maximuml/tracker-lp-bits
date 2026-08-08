<?php

namespace App\Support;

use App\Models\SearchBox;

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
        //load settings from database
        $settings = Settings::get();
        foreach ($settings as $name => $value) {
            SupportContext::setGlobal(strtoupper($name), $value);
        }

        $BASIC = SupportContext::getGlobal('BASIC', []);
        $MAIN = SupportContext::getGlobal('MAIN', []);
        $ACCOUNT = SupportContext::getGlobal('ACCOUNT', []);
        $ATTACHMENT = SupportContext::getGlobal('ATTACHMENT', []);
        $AUTHORITY = SupportContext::getGlobal('AUTHORITY', []);
        $BONUS = SupportContext::getGlobal('BONUS', []);
        $CODE = SupportContext::getGlobal('CODE', []);
        $SECURITY = SupportContext::getGlobal('SECURITY', []);
        $SMTP = SupportContext::getGlobal('SMTP', []);
        $TORRENT = SupportContext::getGlobal('TORRENT', []);
        $TWEAK = SupportContext::getGlobal('TWEAK', []);


        SupportContext::setGlobal('SITENAME', $BASIC['SITENAME']);
        SupportContext::setGlobal('BASEURL', $BASIC['BASEURL'] ?: (SupportContext::getServerValue('HTTP_HOST', 'localhost')));
        $BASEURL = SupportContext::getGlobal('BASEURL', '');
        $announce_urls = [];
        $announce_urls[] = $BASIC['announce_url'] ?: ($BASEURL . DEFAULT_TRACKER_URI);

        SupportContext::setGlobal('SITE_ONLINE', $MAIN['site_online']);
        SupportContext::setGlobal('max_torrent_size', (int)$MAIN['max_torrent_size']);
        SupportContext::setGlobal('announce_interval', (int)$MAIN['announce_interval']);
        SupportContext::setGlobal('annintertwoage', (int)$MAIN['annintertwoage']);
        SupportContext::setGlobal('annintertwo', (int)$MAIN['annintertwo']);
        SupportContext::setGlobal('anninterthreeage', (int)$MAIN['anninterthreeage']);
        SupportContext::setGlobal('anninterthree', (int)$MAIN['anninterthree']);
        SupportContext::setGlobal('signup_timeout', $MAIN['signup_timeout']);
        SupportContext::setGlobal('minoffervotes', $MAIN['minoffervotes']);
        SupportContext::setGlobal('offervotetimeout_main', $MAIN['offervotetimeout']);
        SupportContext::setGlobal('offeruptimeout_main', $MAIN['offeruptimeout']);
        SupportContext::setGlobal('maxsubsize_main', $MAIN['maxsubsize']);
        SupportContext::setGlobal('maxnewsnum_main', $MAIN['maxnewsnum']);
        SupportContext::setGlobal('forumpostsperpage', $MAIN['postsperpage']);
        SupportContext::setGlobal('forumtopicsperpage_main', $MAIN['topicsperpage']);
        SupportContext::setGlobal('torrentsperpage_main', (int)$MAIN['torrentsperpage']);
        SupportContext::setGlobal('max_dead_torrent_time', $MAIN['max_dead_torrent_time']);
        SupportContext::setGlobal('maxusers', (int)$MAIN['maxusers']);
        SupportContext::setGlobal('torrent_dir', $MAIN['torrent_dir']);
        SupportContext::setGlobal('iniupload_main', $MAIN['iniupload']);
        SupportContext::setGlobal('SITEEMAIL', $MAIN['SITEEMAIL']);
        SupportContext::setGlobal('ACCOUNTANTID', (int)$MAIN['ACCOUNTANTID']);
        SupportContext::setGlobal('ALIPAYACCOUNT', $MAIN['ALIPAYACCOUNT']);
        SupportContext::setGlobal('PAYPALACCOUNT', $MAIN['PAYPALACCOUNT']);
        SupportContext::setGlobal('SLOGAN', $MAIN['SLOGAN']);
        SupportContext::setGlobal('icplicense_main', $MAIN['icplicense']);
        SupportContext::setGlobal('autoclean_interval_one', $MAIN['autoclean_interval_one']);
        SupportContext::setGlobal('autoclean_interval_two', $MAIN['autoclean_interval_two']);
        SupportContext::setGlobal('autoclean_interval_three', $MAIN['autoclean_interval_three']);
        SupportContext::setGlobal('autoclean_interval_four', $MAIN['autoclean_interval_four']);
        SupportContext::setGlobal('autoclean_interval_five', $MAIN['autoclean_interval_five']);
        SupportContext::setGlobal('REPORTMAIL', $MAIN['reportemail']);
        SupportContext::setGlobal('invitesystem', $MAIN['invitesystem']);
        SupportContext::setGlobal('registration', $MAIN['registration']);
        SupportContext::setGlobal('enablenfo_main', $MAIN['enablenfo']);
        SupportContext::setGlobal('showpolls_main', $MAIN['showpolls']);
        SupportContext::setGlobal('showstats_main', $MAIN['showstats']);
        SupportContext::setGlobal('showlastxforumposts_main', $MAIN['showlastxforumposts']);
        SupportContext::setGlobal('showlastxtorrents_main', $MAIN['showlastxtorrents']);
        SupportContext::setGlobal('showtrackerload', $MAIN['showtrackerload']);
        SupportContext::setGlobal('showshoutbox_main', $MAIN['showshoutbox']);
        SupportContext::setGlobal('enableoffer', $MAIN['showoffer']);
        SupportContext::setGlobal('sptime', $MAIN['sptime']);
        SupportContext::setGlobal('enablebitbucket_main', $MAIN['enablebitbucket']);
        SupportContext::setGlobal('altname_main', $MAIN['altname'] ?? '');
        SupportContext::setGlobal('deflang', $MAIN['defaultlang']);
        $firstStylesheetId = (int) (\Nexus\Database\NexusDB::table('stylesheets')->orderBy('id')->value('id') ?? 3);
        SupportContext::setGlobal('defcss', (int) ($MAIN['defstylesheet'] ?: $firstStylesheetId));
        SupportContext::setGlobal('enabledonation', $MAIN['donation']);
        SupportContext::setGlobal('enablespecial', $MAIN['spsct']);
        $searchBoxIds = SearchBox::query()->orderBy('id')->pluck('id')->all();
        $defaultBrowsecat = (int) ($searchBoxIds[0] ?? 1);
        $defaultSpecialcat = (int) ($searchBoxIds[1] ?? $defaultBrowsecat);
        SupportContext::setGlobal('browsecatmode', (int) ($MAIN['browsecat'] ?? $defaultBrowsecat));
        SupportContext::setGlobal('specialcatmode', (int) ($MAIN['specialcat'] ?? $defaultSpecialcat));
        SupportContext::setGlobal('waitsystem', $MAIN['waitsystem']);
        SupportContext::setGlobal('maxdlsystem', $MAIN['maxdlsystem']);
        SupportContext::setGlobal('bitbucket', $MAIN['bitbucket']);
        SupportContext::setGlobal('torrentnameprefix', $MAIN['torrentnameprefix']);
        SupportContext::setGlobal('showforumstats_main', $MAIN['showforumstats']);
        SupportContext::setGlobal('verification', $MAIN['verification']);
        SupportContext::setGlobal('invite_count', $MAIN['invite_count']);
        SupportContext::setGlobal('invite_timeout', $MAIN['invite_timeout']);
        SupportContext::setGlobal('seeding_leeching_time_calc_start', $MAIN['seeding_leeching_time_calc_start']);
        SupportContext::setGlobal('logo_main', $MAIN['logo']);


        SupportContext::setGlobal('emailnotify_smtp', $SMTP['emailnotify']);
        SupportContext::setGlobal('smtptype', $SMTP['smtptype']);
        SupportContext::setGlobal('smtp_host', $SMTP['smtp_host']);
        SupportContext::setGlobal('smtp_port', $SMTP['smtp_port']);
        SupportContext::setGlobal('smtp_from', $SMTP['smtp_from']);
        SupportContext::setGlobal('smtpaddress', $SMTP['smtpaddress'] ?? '');
        SupportContext::setGlobal('smtpport', $SMTP['smtpport'] ?? '');
        SupportContext::setGlobal('accountname', $SMTP['accountname'] ?? '');
        SupportContext::setGlobal('accountpassword', $SMTP['accountpassword'] ?? '');

        SupportContext::setGlobal('securelogin', $SECURITY['securelogin']);
        SupportContext::setGlobal('securetracker', $SECURITY['securetracker']);
        $https_announce_urls = [];
        $https_announce_urls[] = $SECURITY['https_announce_url'];
        SupportContext::setGlobal('iv', $SECURITY['iv']);
        SupportContext::setGlobal('maxip', $SECURITY['maxip']);
        SupportContext::setGlobal('maxloginattempts', $SECURITY['maxloginattempts']);
        SupportContext::setGlobal('disableemailchange', $SECURITY['changeemail']);
        SupportContext::setGlobal('cheaterdet_security', $SECURITY['cheaterdet']);
        SupportContext::setGlobal('nodetect_security', $SECURITY['nodetect']);

        SupportContext::setGlobal('defaultclass_class', isset(\App\Models\User::$classes[$AUTHORITY['defaultclass']]) ? $AUTHORITY['defaultclass'] : \App\Models\User::CLASS_USER);
        SupportContext::setGlobal('staffmem_class', $AUTHORITY['staffmem']);
        SupportContext::setGlobal('newsmanage_class', $AUTHORITY['newsmanage']);
        SupportContext::setGlobal('sbmanage_class', $AUTHORITY['sbmanage']);
        SupportContext::setGlobal('pollmanage_class', $AUTHORITY['pollmanage']);
        SupportContext::setGlobal('postmanage_class', $AUTHORITY['postmanage']);
        SupportContext::setGlobal('commanage_class', $AUTHORITY['commanage']);
        SupportContext::setGlobal('forummanage_class', $AUTHORITY['forummanage']);
        SupportContext::setGlobal('viewuserlist_class', $AUTHORITY['viewuserlist']);
        SupportContext::setGlobal('torrentmanage_class', $AUTHORITY['torrentmanage']);
        SupportContext::setGlobal('torrentsticky_class', $AUTHORITY['torrentsticky']);
        SupportContext::setGlobal('torrentonpromotion_class', $AUTHORITY['torrentonpromotion'] ?? '');
        SupportContext::setGlobal('askreseed_class', $AUTHORITY['askreseed']);
        SupportContext::setGlobal('viewnfo_class', $AUTHORITY['viewnfo']);
        SupportContext::setGlobal('torrentstructure_class', $AUTHORITY['torrentstructure']);
        SupportContext::setGlobal('sendinvite_class', $AUTHORITY['sendinvite']);
        SupportContext::setGlobal('viewhistory_class', $AUTHORITY['viewhistory']);
        SupportContext::setGlobal('topten_class', $AUTHORITY['topten']);
        SupportContext::setGlobal('log_class', $AUTHORITY['log']);
        SupportContext::setGlobal('confilog_class', $AUTHORITY['confilog']);
        SupportContext::setGlobal('userprofile_class', $AUTHORITY['userprofile']);
        SupportContext::setGlobal('torrenthistory_class', $AUTHORITY['torrenthistory']);
        SupportContext::setGlobal('prfmanage_class', $AUTHORITY['prfmanage']);
        SupportContext::setGlobal('cruprfmanage_class', $AUTHORITY['cruprfmanage']);
        SupportContext::setGlobal('uploadsub_class', $AUTHORITY['uploadsub']);
        SupportContext::setGlobal('delownsub_class', $AUTHORITY['delownsub']);
        SupportContext::setGlobal('submanage_class', $AUTHORITY['submanage']);
        SupportContext::setGlobal('updateextinfo_class', $AUTHORITY['updateextinfo']);
        SupportContext::setGlobal('viewanonymous_class', $AUTHORITY['viewanonymous']);
        SupportContext::setGlobal('beanonymous_class', $AUTHORITY['beanonymous']);
        SupportContext::setGlobal('addoffer_class', $AUTHORITY['addoffer']);
        SupportContext::setGlobal('offermanage_class', $AUTHORITY['offermanage']);
        SupportContext::setGlobal('upload_class', $AUTHORITY['upload']);
        SupportContext::setGlobal('uploadspecial_class', $AUTHORITY['uploadspecial']);
        SupportContext::setGlobal('movetorrent_class', $AUTHORITY['movetorrent']);
        SupportContext::setGlobal('chrmanage_class', $AUTHORITY['chrmanage']);
        SupportContext::setGlobal('viewinvite_class', $AUTHORITY['viewinvite']);
        SupportContext::setGlobal('buyinvite_class', $AUTHORITY['buyinvite']);
        SupportContext::setGlobal('seebanned_class', $AUTHORITY['seebanned']);
        SupportContext::setGlobal('againstoffer_class', $AUTHORITY['againstoffer']);
        SupportContext::setGlobal('userbar_class', $AUTHORITY['userbar']);

        SupportContext::setGlobal('where_tweak', $TWEAK['where']);
        SupportContext::setGlobal('iplog1', $TWEAK['iplog1']);
        SupportContext::setGlobal('bonus_tweak', $TWEAK['bonus']);
        SupportContext::setGlobal('titlekeywords_tweak', $TWEAK['titlekeywords']);
        SupportContext::setGlobal('metakeywords_tweak', $TWEAK['metakeywords']);
        SupportContext::setGlobal('metadescription_tweak', $TWEAK['metadescription']);
        SupportContext::setGlobal('datefounded', $TWEAK['datefounded']);
        SupportContext::setGlobal('enablelocation_tweak', $TWEAK['enablelocation']);
        SupportContext::setGlobal('enablesqldebug_tweak', $TWEAK['enablesqldebug']);
        SupportContext::setGlobal('sqldebug_tweak', $TWEAK['sqldebug']);
        SupportContext::setGlobal('cssdate_tweak', $TWEAK['cssdate']);
        SupportContext::setGlobal('enabletooltip_tweak', $TWEAK['enabletooltip']);
        SupportContext::setGlobal('analyticscode_tweak', $TWEAK['analyticscode']);

        SupportContext::setGlobal('enableattach_attachment', $ATTACHMENT['enableattach']);
        SupportContext::setGlobal('classone_attachment', $ATTACHMENT['classone']);
        SupportContext::setGlobal('countone_attachment', $ATTACHMENT['countone']);
        SupportContext::setGlobal('sizeone_attachment', $ATTACHMENT['sizeone']);
        SupportContext::setGlobal('extone_attachment', $ATTACHMENT['extone']);
        SupportContext::setGlobal('classtwo_attachment', $ATTACHMENT['classtwo']);
        SupportContext::setGlobal('counttwo_attachment', $ATTACHMENT['counttwo']);
        SupportContext::setGlobal('sizetwo_attachment', $ATTACHMENT['sizetwo']);
        SupportContext::setGlobal('exttwo_attachment', $ATTACHMENT['exttwo']);
        SupportContext::setGlobal('classthree_attachment', $ATTACHMENT['classthree']);
        SupportContext::setGlobal('countthree_attachment', $ATTACHMENT['countthree']);
        SupportContext::setGlobal('sizethree_attachment', $ATTACHMENT['sizethree']);
        SupportContext::setGlobal('extthree_attachment', $ATTACHMENT['extthree']);
        SupportContext::setGlobal('classfour_attachment', $ATTACHMENT['classfour']);
        SupportContext::setGlobal('countfour_attachment', $ATTACHMENT['countfour']);
        SupportContext::setGlobal('sizefour_attachment', $ATTACHMENT['sizefour']);
        SupportContext::setGlobal('extfour_attachment', $ATTACHMENT['extfour']);
        SupportContext::setGlobal('savedirectory_attachment', $ATTACHMENT['savedirectory']);
        SupportContext::setGlobal('httpdirectory_attachment', $ATTACHMENT['httpdirectory']);
        SupportContext::setGlobal('savedirectorytype_attachment', $ATTACHMENT['savedirectorytype']);
        SupportContext::setGlobal('thumbnailtype_attachment', $ATTACHMENT['thumbnailtype']);
        SupportContext::setGlobal('thumbquality_attachment', $ATTACHMENT['thumbquality']);
        SupportContext::setGlobal('thumbwidth_attachment', $ATTACHMENT['thumbwidth']);
        SupportContext::setGlobal('thumbheight_attachment', $ATTACHMENT['thumbheight']);
        SupportContext::setGlobal('watermarkpos_attachment', $ATTACHMENT['watermarkpos']);
        SupportContext::setGlobal('watermarkwidth_attachment', $ATTACHMENT['watermarkwidth']);
        SupportContext::setGlobal('watermarkheight_attachment', $ATTACHMENT['watermarkheight']);
        SupportContext::setGlobal('watermarkquality_attachment', $ATTACHMENT['watermarkquality']);
        SupportContext::setGlobal('altthumbwidth_attachment', $ATTACHMENT['altthumbwidth']);
        SupportContext::setGlobal('altthumbheight_attachment', $ATTACHMENT['altthumbheight']);



        SupportContext::setGlobal('mainversion_code', $CODE['mainversion']);
        SupportContext::setGlobal('subversion_code', $CODE['subversion']);
        SupportContext::setGlobal('releasedate_code', $CODE['releasedate']);
        SupportContext::setGlobal('website_code', $CODE['website']);

        // The BONUS array comes from the database settings cache. Provide an empty
        // fallback so missing/uncached bonus keys do not emit undefined-variable
        // warnings when downstream pages (delete, fastdelete, mybonus) use them.

        SupportContext::setGlobal('donortimes_bonus', $BONUS['donortimes'] ?? 0);
        SupportContext::setGlobal('perseeding_bonus', $BONUS['perseeding'] ?? 0);
        SupportContext::setGlobal('maxseeding_bonus', $BONUS['maxseeding'] ?? 0);
        SupportContext::setGlobal('tzero_bonus', $BONUS['tzero'] ?? 0);
        SupportContext::setGlobal('nzero_bonus', $BONUS['nzero'] ?? 0);
        SupportContext::setGlobal('bzero_bonus', $BONUS['bzero'] ?? 0);
        SupportContext::setGlobal('l_bonus', $BONUS['l'] ?? 0);
        SupportContext::setGlobal('uploadtorrent_bonus', $BONUS['uploadtorrent'] ?? 0);
        SupportContext::setGlobal('starttopic_bonus', $BONUS['starttopic'] ?? 0);
        SupportContext::setGlobal('makepost_bonus', $BONUS['makepost'] ?? 0);
        SupportContext::setGlobal('addcomment_bonus', $BONUS['addcomment'] ?? 0);
        SupportContext::setGlobal('pollvote_bonus', $BONUS['pollvote'] ?? 0);
        SupportContext::setGlobal('offervote_bonus', $BONUS['offervote'] ?? 0);
        SupportContext::setGlobal('saythanks_bonus', $BONUS['saythanks'] ?? 0);
        SupportContext::setGlobal('receivethanks_bonus', $BONUS['receivethanks'] ?? 0);
        SupportContext::setGlobal('onegbupload_bonus', $BONUS['onegbupload'] ?? 0);
        SupportContext::setGlobal('fivegbupload_bonus', $BONUS['fivegbupload'] ?? 0);
        SupportContext::setGlobal('tengbupload_bonus', $BONUS['tengbupload'] ?? 0);
        SupportContext::setGlobal('ratiolimit_bonus', $BONUS['ratiolimit'] ?? 0);
        SupportContext::setGlobal('dlamountlimit_bonus', $BONUS['dlamountlimit'] ?? 0);
        SupportContext::setGlobal('oneinvite_bonus', $BONUS['oneinvite'] ?? 0);
        SupportContext::setGlobal('customtitle_bonus', $BONUS['customtitle'] ?? 0);
        SupportContext::setGlobal('vipstatus_bonus', $BONUS['vipstatus'] ?? 0);
        SupportContext::setGlobal('bonusgift_bonus', $BONUS['bonusgift'] ?? 0);
        SupportContext::setGlobal('basictax_bonus', $BONUS['basictax'] ?? 0);
        SupportContext::setGlobal('taxpercentage_bonus', $BONUS['taxpercentage'] ?? 0);
        SupportContext::setGlobal('attendance_initial_bonus', isset($BONUS['attendance_initial']) ? (int) $BONUS['attendance_initial'] : \App\Models\Attendance::INITIAL_BONUS);
        SupportContext::setGlobal('attendance_step_bonus', isset($BONUS['attendance_step']) ? (int) $BONUS['attendance_step'] : \App\Models\Attendance::STEP_BONUS);
        SupportContext::setGlobal('attendance_max_bonus', isset($BONUS['attendance_max']) ? (int) $BONUS['attendance_max'] : \App\Models\Attendance::MAX_BONUS);
        SupportContext::setGlobal('attendance_continuous_bonus', isset($BONUS['attendance_continuous']) && is_array($BONUS['attendance_continuous']) ? $BONUS['attendance_continuous'] : \App\Models\Attendance::CONTINUOUS_BONUS);

        SupportContext::setGlobal('neverdelete_account', $ACCOUNT['neverdelete']);
        SupportContext::setGlobal('neverdeletepacked_account', $ACCOUNT['neverdeletepacked']);
        SupportContext::setGlobal('deletepacked_account', $ACCOUNT['deletepacked']);
        SupportContext::setGlobal('deleteunpacked_account', $ACCOUNT['deleteunpacked']);
        SupportContext::setGlobal('deletenotransfer_account', $ACCOUNT['deletenotransfer']);
        SupportContext::setGlobal('deletenotransfertwo_account', $ACCOUNT['deletenotransfertwo']);
        SupportContext::setGlobal('deletepeasant_account', $ACCOUNT['deletepeasant']);
        SupportContext::setGlobal('psdlone_account', $ACCOUNT['psdlone']);
        SupportContext::setGlobal('psratioone_account', $ACCOUNT['psratioone']);
        SupportContext::setGlobal('psdltwo_account', $ACCOUNT['psdltwo']);
        SupportContext::setGlobal('psratiotwo_account', $ACCOUNT['psratiotwo']);
        SupportContext::setGlobal('psdlthree_account', $ACCOUNT['psdlthree']);
        SupportContext::setGlobal('psratiothree_account', $ACCOUNT['psratiothree']);
        SupportContext::setGlobal('psdlfour_account', $ACCOUNT['psdlfour']);
        SupportContext::setGlobal('psratiofour_account', $ACCOUNT['psratiofour']);
        SupportContext::setGlobal('psdlfive_account', $ACCOUNT['psdlfive']);
        SupportContext::setGlobal('psratiofive_account', $ACCOUNT['psratiofive']);
        SupportContext::setGlobal('putime_account', $ACCOUNT['putime']);
        SupportContext::setGlobal('pudl_account', $ACCOUNT['pudl']);
        SupportContext::setGlobal('puprratio_account', $ACCOUNT['puprratio']);
        SupportContext::setGlobal('puderatio_account', $ACCOUNT['puderatio']);
        SupportContext::setGlobal('eutime_account', $ACCOUNT['eutime']);
        SupportContext::setGlobal('eudl_account', $ACCOUNT['eudl']);
        SupportContext::setGlobal('euprratio_account', $ACCOUNT['euprratio']);
        SupportContext::setGlobal('euderatio_account', $ACCOUNT['euderatio']);
        SupportContext::setGlobal('cutime_account', $ACCOUNT['cutime']);
        SupportContext::setGlobal('cudl_account', $ACCOUNT['cudl']);
        SupportContext::setGlobal('cuprratio_account', $ACCOUNT['cuprratio']);
        SupportContext::setGlobal('cuderatio_account', $ACCOUNT['cuderatio']);
        SupportContext::setGlobal('iutime_account', $ACCOUNT['iutime']);
        SupportContext::setGlobal('iudl_account', $ACCOUNT['iudl']);
        SupportContext::setGlobal('iuprratio_account', $ACCOUNT['iuprratio']);
        SupportContext::setGlobal('iuderatio_account', $ACCOUNT['iuderatio']);
        SupportContext::setGlobal('vutime_account', $ACCOUNT['vutime']);
        SupportContext::setGlobal('vudl_account', $ACCOUNT['vudl']);
        SupportContext::setGlobal('vuprratio_account', $ACCOUNT['vuprratio']);
        SupportContext::setGlobal('vuderatio_account', $ACCOUNT['vuderatio']);
        SupportContext::setGlobal('exutime_account', $ACCOUNT['exutime']);
        SupportContext::setGlobal('exudl_account', $ACCOUNT['exudl']);
        SupportContext::setGlobal('exuprratio_account', $ACCOUNT['exuprratio']);
        SupportContext::setGlobal('exuderatio_account', $ACCOUNT['exuderatio']);
        SupportContext::setGlobal('uutime_account', $ACCOUNT['uutime']);
        SupportContext::setGlobal('uudl_account', $ACCOUNT['uudl']);
        SupportContext::setGlobal('uuprratio_account', $ACCOUNT['uuprratio']);
        SupportContext::setGlobal('uuderatio_account', $ACCOUNT['uuderatio']);
        SupportContext::setGlobal('nmtime_account', $ACCOUNT['nmtime']);
        SupportContext::setGlobal('nmdl_account', $ACCOUNT['nmdl']);
        SupportContext::setGlobal('nmprratio_account', $ACCOUNT['nmprratio']);
        SupportContext::setGlobal('nmderatio_account', $ACCOUNT['nmderatio']);
        SupportContext::setGlobal('getInvitesByPromotion_class', $ACCOUNT['getInvitesByPromotion']);

        SupportContext::setGlobal('prorules_torrent', $TORRENT['prorules']);
        SupportContext::setGlobal('randomhalfleech_torrent', $TORRENT['randomhalfleech']);
        SupportContext::setGlobal('randomfree_torrent', $TORRENT['randomfree']);
        SupportContext::setGlobal('randomtwoup_torrent', $TORRENT['randomtwoup']);
        SupportContext::setGlobal('randomtwoupfree_torrent', $TORRENT['randomtwoupfree']);
        SupportContext::setGlobal('randomtwouphalfdown_torrent', $TORRENT['randomtwouphalfdown']);
        SupportContext::setGlobal('randomthirtypercentdown_torrent', $TORRENT['randomthirtypercentdown']);
        SupportContext::setGlobal('largesize_torrent', (int)$TORRENT['largesize']);
        SupportContext::setGlobal('largepro_torrent', $TORRENT['largepro']);
        SupportContext::setGlobal('expirehalfleech_torrent', $TORRENT['expirehalfleech']);
        SupportContext::setGlobal('expirefree_torrent', $TORRENT['expirefree']);
        SupportContext::setGlobal('expiretwoup_torrent', $TORRENT['expiretwoup']);
        SupportContext::setGlobal('expiretwoupfree_torrent', $TORRENT['expiretwoupfree']);
        SupportContext::setGlobal('expiretwouphalfleech_torrent', $TORRENT['expiretwouphalfleech']);
        SupportContext::setGlobal('expirethirtypercentleech_torrent', $TORRENT['expirethirtypercentleech']);
        SupportContext::setGlobal('expirenormal_torrent', $TORRENT['expirenormal']);
        SupportContext::setGlobal('hotdays_torrent', $TORRENT['hotdays']);
        SupportContext::setGlobal('hotseeder_torrent', $TORRENT['hotseeder']);
        SupportContext::setGlobal('halfleechbecome_torrent', $TORRENT['halfleechbecome']);
        SupportContext::setGlobal('freebecome_torrent', $TORRENT['freebecome']);
        SupportContext::setGlobal('twoupbecome_torrent', $TORRENT['twoupbecome']);
        SupportContext::setGlobal('twoupfreebecome_torrent', $TORRENT['twoupfreebecome']);
        SupportContext::setGlobal('twouphalfleechbecome_torrent', $TORRENT['twouphalfleechbecome']);
        SupportContext::setGlobal('thirtypercentleechbecome_torrent', $TORRENT['thirtypercentleechbecome']);
        SupportContext::setGlobal('normalbecome_torrent', $TORRENT['normalbecome']);
        SupportContext::setGlobal('uploaderdouble_torrent', $TORRENT['uploaderdouble']);
        SupportContext::setGlobal('deldeadtorrent_torrent', $TORRENT['deldeadtorrent']);

        //Directory for subs
        SupportContext::setGlobal('SUBSPATH', "subs");
        //Whether clean-up is triggered by cron, instead of the default browser clicks.
        //Set this to true ONLY if you have setup other method to schedule the clean-up process.
        //e.g. cron on *nix, add the following line (without "") in your crontab file
        //"*/5 * * * * wget -O - -q -t 1 https://nexusphp.org/cron.php"
        //NOTE:
        //Make sure you have wget installed on your OS
        //replace "https://nexusphp.org/" with your own site address

        SupportContext::setGlobal('useCronTriggerCleanUp', true);
        //some promotion rules
        //$promotionrules_torrent = array(0 => array("mediumid" => array(1), "promotion" => 5), 1 => array("mediumid" => array(3), "promotion" => 5), 2 => array("catid" => array(402), "standardid" => array(3), "promotion" => 4), 3 => array("catid" => array(403), "standardid" => array(3), "promotion" => 4));
        SupportContext::setGlobal('promotionrules_torrent', []);
        SupportContext::setGlobal('announce_urls', $announce_urls);
        SupportContext::setGlobal('https_announce_urls', $https_announce_urls);
    }
}

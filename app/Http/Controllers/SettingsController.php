<?php

namespace App\Http\Controllers;

use App\Models\Language;
use App\Models\Setting;
use App\Models\Torrent;
use App\Models\User;
use App\Repositories\TagRepository;
use App\Support\CurrentUser;
use App\Support\Globals;
use App\Support\Hooks;
use App\Support\Log;
use App\Support\Settings;
use App\Support\UserDisplay;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class SettingsController extends LegacyController
{
    private TagRepository $tagRepository;

    public function __construct(TagRepository $tagRepository)
    {
        $this->tagRepository = $tagRepository;
    }

    /** @var array<string, array<int, string>> */
    private array $validConfigs = [
        'basic' => ['SITENAME', 'BASEURL', 'announce_url'],
        'code' => ['mainversion', 'subversion', 'releasedate', 'website'],
        'main' => [
            'site_online', 'max_torrent_size', 'announce_interval', 'annintertwoage', 'annintertwo', 'anninterthreeage', 'anninterthree', 'signup_timeout',
            'minoffervotes', 'offervotetimeout', 'offeruptimeout', 'maxsubsize', 'postsperpage', 'topicsperpage', 'torrentsperpage', 'maxnewsnum',
            'max_dead_torrent_time', 'maxusers', 'torrent_dir', 'iniupload', 'SITEEMAIL', 'ACCOUNTANTID', 'ALIPAYACCOUNT', 'PAYPALACCOUNT', 'SLOGAN',
            'icplicense', 'autoclean_interval_one', 'autoclean_interval_two', 'autoclean_interval_three', 'autoclean_interval_four', 'autoclean_interval_five',
            'reportemail', 'invitesystem', 'registration', 'enablenfo',
            'showpolls', 'showstats', 'showlastxtorrents', 'showtrackerload', 'showshoutbox', 'showoffer', 'sptime', 'enablebitbucket',
            'smalldescription', 'altname', 'defaultlang', 'defstylesheet', 'donation', 'browsecat', 'waitsystem',
            'maxdlsystem', 'bitbucket', 'torrentnameprefix', 'showforumstats', 'verification', 'invite_count', 'invite_timeout', 'seeding_leeching_time_calc_start',
            'startsubid', 'logo', 'showlastxforumposts', 'enable_technical_info', 'site_language_enabled', 'show_top_uploader', 'offer_skip_approved_count',
            'upload_deny_approval_deny_count', 'enable_global_search', 'tmp_invite_count', 'complain_enabled',
        ],
        'smtp' => ['smtptype', 'emailnotify', 'smtp_host', 'smtp_port', 'smtp_from', 'smtpaddress', 'smtpport', 'encryption', 'accountname', 'accountpassword'],
        'security' => [
            'securelogin', 'securetracker', 'https_announce_url', 'iv', 'maxip', 'maxloginattempts', 'changeemail', 'cheaterdet', 'nodetect',
            'guest_visit_type', 'guest_visit_value_static_page', 'guest_visit_value_custom_content', 'guest_visit_value_redirect',
            'login_type', 'login_secret_lifetime', 'use_challenge_response_authentication',
        ],
        'authority' => [
            'defaultclass', 'staffmem', 'newsmanage', 'sbmanage', 'pollmanage', 'postmanage',
            'commanage', 'forummanage', 'viewuserlist', 'torrentmanage', 'torrentsticky', 'torrenton_promotion', 'torrent_hr', 'askreseed', 'viewnfo',
            'torrentstructure', 'sendinvite', 'viewhistory', 'topten', 'log', 'confilog', 'userprofile', 'torrenthistory', 'prfmanage', 'cruprfmanage',
            'uploadsub', 'delownsub', 'submanage', 'updateextinfo', 'viewanonymous', 'beanonymous', 'addoffer', 'offermanage', 'upload', 'movetorrent', 'chrmanage', 'viewinvite', 'buyinvite', 'seebanned', 'againstoffer', 'userbar', 'torrent-approval',
            'torrent-delete', 'user-delete', 'user-change-class', 'torrent-set-special-tag', 'torrent-approval-allow-automatic', 'torrent-set-price',
        ],
        'tweak' => ['where', 'iplog1', 'bonus', 'datefounded', 'enablelocation', 'titlekeywords', 'metakeywords', 'metadescription', 'enablesqldebug', 'sqldebug', 'cssdate', 'enabletooltip', 'analyticscode'],
        'bonus' => [
            'donortimes', 'perseeding', 'maxseeding', 'tzero', 'nzero', 'bzero', 'l', 'uploadtorrent', 'starttopic', 'makepost',
            'addcomment', 'pollvote', 'offervote', 'saythanks', 'receivethanks', 'onegbupload', 'fivegbupload',
            'tengbupload', 'ratiolimit', 'dlamountlimit', 'oneinvite', 'customtitle', 'vipstatus', 'bonusgift', 'basictax', 'taxpercentage',
            'attendance_initial', 'attendance_step', 'attendance_max', 'cancel_hr', 'attendance_card',
            'harem_addition', 'hundredgbupload', 'tengbdownload', 'hundredgbdownload', 'official_addition', 'official_tag', 'zero_bonus_tag', 'zero_bonus_factor',
            'one_tmp_invite', 'rainbow_id', 'change_username_card', 'min_size', 'self_enable',
        ],
        'account' => [
            'neverdelete', 'neverdeletepacked', 'deletepacked', 'deleteunpacked', 'deletenotransfer', 'deletenotransfertwo', 'deletepeasant',
            'psdlone', 'psratioone', 'psdltwo', 'psratiotwo', 'psdlthree', 'psratiothree', 'psdlfour', 'psratiofour', 'psdlfive', 'psratiofive', User::CLASS_PEASANT.'_alias',
            User::CLASS_USER.'_alias',
            'putime', 'pudl', User::CLASS_POWER_USER.'_min_seed_points', 'puprratio', 'puderatio', User::CLASS_POWER_USER.'_alias',
            'eutime', 'eudl', User::CLASS_ELITE_USER.'_min_seed_points', 'euprratio', 'euderatio', User::CLASS_ELITE_USER.'_alias',
            'cutime', 'cudl', User::CLASS_CRAZY_USER.'_min_seed_points', 'cuprratio', 'cuderatio', User::CLASS_CRAZY_USER.'_alias',
            'iutime', 'iudl', User::CLASS_INSANE_USER.'_min_seed_points', 'iuprratio', 'iuderatio', User::CLASS_INSANE_USER.'_alias',
            'vutime', 'vudl', User::CLASS_VETERAN_USER.'_min_seed_points', 'vuprratio', 'vuderatio', User::CLASS_VETERAN_USER.'_alias',
            'exutime', 'exudl', User::CLASS_EXTREME_USER.'_min_seed_points', 'exuprratio', 'exuderatio', User::CLASS_EXTREME_USER.'_alias',
            'uutime', 'uudl', User::CLASS_ULTIMATE_USER.'_min_seed_points', 'uuprratio', 'uuderatio', User::CLASS_ULTIMATE_USER.'_alias',
            'nmtime', 'nmdl', User::CLASS_NEXUS_MASTER.'_min_seed_points', 'nmprratio', 'nmderatio', User::CLASS_NEXUS_MASTER.'_alias',
            'getInvitesByPromotion', 'destroy_disabled',
        ],
        'torrent' => [
            'prorules', 'randomhalfleech', 'randomfree', 'randomtwoup', 'randomtwoupfree', 'randomtwouphalfdown', 'largesize', 'largepro', 'expirehalfleech',
            'expirefree', 'expiretwoup', 'expiretwoupfree', 'expiretwouphalfleech', 'expirenormal', 'hotdays', 'hotseeder', 'halfleechbecome', 'freebecome',
            'twoupbecome', 'twoupfreebecome', 'twouphalfleechbecome', 'normalbecome', 'uploaderdouble', 'deldeadtorrent', 'randomthirtypercentdown',
            'thirtypercentleechbecome', 'expirethirtypercentleech', 'sticky_first_level_background_color', 'sticky_second_level_background_color',
            'download_support_passkey', 'approval_status_icon_enabled', 'approval_status_none_visible',
            'nfo_view_style_default', 'tax_factor', 'max_price', 'paid_torrent_enabled', 'reward_bonus_options', 'reward_times_limit',
        ],
        'attachment' => ['enableattach', 'classone', 'countone', 'sizeone', 'extone', 'classtwo', 'counttwo', 'sizetwo', 'exttwo', 'classthree', 'countthree', 'sizethree', 'extthree', 'classfour', 'countfour', 'sizefour', 'extfour', 'savedirectory', 'httpdirectory', 'savedirectorytype', 'thumbnailtype', 'thumbquality', 'thumbwidth', 'thumbheight', 'watermarkpos', 'watermarkwidth', 'watermarkheight', 'watermarkquality', 'altthumbwidth', 'altthumbheight'],
        'misc' => ['donation_custom', 'protected_forum'],
    ];

    public function settings(Request $request): View|RedirectResponse|Response
    {
        $currentUser = app(CurrentUser::class)->get();
        if ($currentUser === null) {
            return redirect('/settings.php');
        }

        if (UserDisplay::currentClass() < User::CLASS_SYSOP) {
            return $this->legacyAbortResponse('Error', 'Permission denied.');
        }

        if ($request->isMethod('post')) {
            return $this->handleSave($request) ?? redirect('/settings.php');
        }

        $action = (string) ($request->query('action') ?? 'showmenu');
        $allowedActions = ['showmenu', 'basicsettings', 'mainsettings', 'smtpsettings', 'securitysettings', 'authoritysettings', 'tweaksettings', 'bonussettings', 'accountsettings', 'torrentsettings', 'attachmentsettings', 'codesettings', 'miscsettings'];
        if (! in_array($action, $allowedActions, true)) {
            $action = 'showmenu';
        }

        $lang = (array) (app(Globals::class)->get('lang_settings') ?? []);

        $data = [
            'action' => $action,
            'lang' => $lang,
            'currentUser' => (array) $currentUser,
        ];

        $sectionMap = [
            'basicsettings' => 'basic',
            'mainsettings' => 'main',
            'smtpsettings' => 'smtp',
            'securitysettings' => 'security',
            'authoritysettings' => 'authority',
            'tweaksettings' => 'tweak',
            'bonussettings' => 'bonus',
            'accountsettings' => 'account',
            'torrentsettings' => 'torrent',
            'attachmentsettings' => 'attachment',
            'codesettings' => 'code',
            'miscsettings' => 'misc',
        ];

        if (isset($sectionMap[$action])) {
            $section = $sectionMap[$action];
            $data['section'] = $section;
            $data['config'] = Settings::fromDb($section);

            if ($section === 'main') {
                $data['searchboxes'] = DB::table('searchbox')->get(['id', 'name']);
                $data['stylesheets'] = DB::table('stylesheets')->orderBy('name')->get();
                $allSiteLanguages = Language::query()->where('site_lang', 1)->get();
                $data['allSiteLanguages'] = $allSiteLanguages;
                $data['allEnabledLangs'] = Language::listEnabled(true);
            }

            if ($section === 'bonus') {
                $data['attendance_continuous'] = (array) ($data['config']['attendance_continuous'] ?? []);
                $tagRep = $this->tagRepository;
                $data['tagRep'] = $tagRep;
            }

            if ($section === 'torrent') {
                $data['nfoViewStyles'] = Torrent::$nfoViewStyles;
            }

            if ($section === 'security') {
                $data['staticPages'] = $this->getStaticPages();
                $data['authority'] = Settings::fromDb('authority');
            }
        }

        return view('settings.index', $data);
    }

    private function handleSave(Request $request): RedirectResponse|Response|null
    {
        $action = (string) ($request->post('action') ?? '');
        $currentUser = (array) (app(CurrentUser::class)->get() ?? []);
        $username = (string) ($currentUser['username'] ?? 'unknown');
        $actiontime = date('F j, Y, g:i a');

        $saveMap = [
            'savesettings_basic' => 'basic',
            'savesettings_main' => 'main',
            'savesettings_smtp' => 'smtp',
            'savesettings_security' => 'security',
            'savesettings_authority' => 'authority',
            'savesettings_tweak' => 'tweak',
            'savesettings_bonus' => 'bonus',
            'savesettings_account' => 'account',
            'savesettings_torrent' => 'torrent',
            'savesettings_attachment' => 'attachment',
            'savesettings_code' => 'code',
            'savesettings_misc' => 'misc',
        ];

        if (! isset($saveMap[$action])) {
            return null;
        }

        $section = $saveMap[$action];
        $validConfig = $this->validConfigs[$section] ?? [];

        // SMTP: conditional fields based on smtptype
        if ($section === 'smtp') {
            $smtpType = (string) ($request->post('smtptype') ?? '');
            $validConfig = ['smtptype', 'emailnotify'];
            if ($smtpType === 'advanced') {
                $validConfig = array_merge($validConfig, ['smtp_host', 'smtp_port', 'smtp_from']);
            } elseif ($smtpType === 'external') {
                $validConfig = array_merge($validConfig, ['smtpaddress', 'smtpport', 'encryption', 'accountname', 'accountpassword']);
            }
        }

        // Torrent: allow hooks to extend valid config
        if ($section === 'torrent') {
            $validConfig = Hooks::applyFilter('setting_valid_config', $validConfig);
        }

        $data = [];
        foreach ($validConfig as $config) {
            $data[$config] = $request->post($config) ?? null;
        }

        // Special: site_language_enabled is an array
        if ($section === 'main') {
            $data['site_language_enabled'] = $request->post('site_language_enabled', []);
            if (is_array($data['site_language_enabled'])) {
                $data['site_language_enabled'] = implode(',', $data['site_language_enabled']);
            }
        }

        // Special: getInvitesByPromotion is an array
        if ($section === 'account') {
            $invitesByPromotion = $request->post('getInvitesByPromotion', []);
            if (is_array($invitesByPromotion)) {
                $data['getInvitesByPromotion'] = $invitesByPromotion;
            }
        }

        // Special: attendance_continuous is day=>value pairs
        if ($section === 'bonus') {
            $days = (array) $request->post('attendance_continuous_day', []);
            $values = (array) $request->post('attendance_continuous_value', []);
            $continuous = [];
            if (count($days) === count($values)) {
                foreach ($days as $k => $day) {
                    $value = (int) ($values[$k] ?? 0);
                    if ($day > 0 && $value > 0) {
                        $continuous[(int) $day] = $value;
                    }
                }
            }
            ksort($continuous);
            $data['attendance_continuous'] = $continuous;
        }

        // Special: security login_secret regeneration
        if ($section === 'security') {
            if ($request->post('login_secret_regenerate') === 'yes') {
                $minute = (int) ($request->post('login_secret_lifetime') ?? 0);
                $timestamp = strtotime("+ {$minute} minutes");
                $data['login_secret_deadline'] = $timestamp !== false ? date('Y-m-d H:i:s', $timestamp) : date('Y-m-d H:i:s');
                $data['login_secret'] = md5((string) microtime(true));
            }
        }

        // Special: authority validation
        if ($section === 'authority') {
            foreach ($validConfig as $config) {
                if (in_array($config, Setting::$permissionMustHaveClass, true)) {
                    $classValue = is_string($data[$config] ?? null) || is_int($data[$config] ?? null) ? (string) ($data[$config] ?? '') : '';
                    if (! isset(User::$classes[$classValue])) {
                        return $this->legacyAbortResponse('Error', 'Invalid user class: '.$classValue);
                    }
                }
            }
        }

        // Special: misc protected_forum validation
        if ($section === 'misc') {
            $protectedForum = is_string($data['protected_forum'] ?? null) ? (string) $data['protected_forum'] : '';
            if (! empty($protectedForum) && ! preg_match('/^[,\d]*\d+$/', $protectedForum)) {
                return $this->legacyAbortResponse('Error', 'Forum format error: use comma-separated IDs.');
            }
        }

        $autoload = $section === 'misc' ? 'no' : 'yes';
        Settings::saveBatch($section, $data, $autoload);

        // Cache clearing
        if ($section === 'main') {
            Cache::forget('recent_news');
            Cache::forget('stats_users');
            Cache::forget('stats_torrents');
            Cache::forget('peers_count');
            Cache::forget('site_lang_lang_list');
        }
        if ($section === 'account') {
            Cache::forget('stats_classes');
        }

        $sectionLabel = ucfirst($section);
        Log::writeWithContext("Tracker {$sectionLabel} settings updated by {$username}. {$actiontime}", 'mod');

        return redirect('/settings.php?action='.$section.'settings');
    }

    /** @return array<int, string> */
    private function getStaticPages(): array
    {
        $pages = [];
        $dir = base_path('resources/static-pages');
        if (is_dir($dir)) {
            $matches = glob($dir.'/*');
            if ($matches !== false) {
                foreach ($matches as $page) {
                    $pages[] = basename($page);
                }
            }
        }

        return $pages;
    }
}

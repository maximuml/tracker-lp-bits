<?php

declare(strict_types=1);

namespace App\Support\Http;

/**
 * T-21: Allow-list for mixed-method routes (Route::match / Route::any).
 *
 * The architecture test `MixedRouteAllowListTest` enforces that no new
 * mixed-method routes are added without being registered here. Each entry
 * must include a justification for why GET and POST (or other method
 * combinations) share the same controller action.
 *
 * Categories:
 * - "form": GET displays a form, POST submits it (legacy NexusPHP pattern)
 * - "protocol": BitTorrent protocol endpoints that accept any method
 * - "api": RESTful endpoints combining PUT+PATCH
 * - "ajax": JSON endpoints that read params from both GET and POST
 * - "command": GET displays page, POST triggers destructive action
 *
 * To reduce this list: split the controller action into separate
 * GET (display) and POST (submit) methods, then change the route to
 * Route::get() or Route::post() respectively.
 */
final class MixedRouteAllowList
{
    /**
     * @return array<string, array{category: string, reason: string}>
     */
    public static function entries(): array
    {
        return [
            // --- BitTorrent protocol (Route::any) ---
            'GET /announce' => ['category' => 'protocol', 'reason' => 'BitTorrent announce — clients may use GET or UDP-over-HTTP, any method accepted'],
            'GET /announce.php' => ['category' => 'protocol', 'reason' => 'Legacy .php alias for announce'],
            'GET /scrape' => ['category' => 'protocol', 'reason' => 'BitTorrent scrape — same as announce'],
            'GET /scrape.php' => ['category' => 'protocol', 'reason' => 'Legacy .php alias for scrape'],

            // --- web.php: form display + submit ---
            'GET /recover' => ['category' => 'form', 'reason' => 'Password recovery: GET shows form, POST submits email'],
            'GET /forums' => ['category' => 'form', 'reason' => 'Forum listing: GET displays, POST handles forum actions'],
            'GET /usercp' => ['category' => 'form', 'reason' => 'User control panel: GET displays settings, POST saves'],

            // --- api.php: RESTful ---
            'PUT api/v1/messages/{message}' => ['category' => 'api', 'reason' => 'RESTful message update — PUT+PATCH combined for compatibility'],
            'PUT api/v1/news/{news}' => ['category' => 'api', 'reason' => 'RESTful news update — PUT+PATCH combined for compatibility'],
            'PUT api/v1/polls/{poll}' => ['category' => 'api', 'reason' => 'RESTful poll update — PUT+PATCH combined for compatibility'],
            'PUT api/v1/forums/{forum}' => ['category' => 'api', 'reason' => 'RESTful forum update — PUT+PATCH combined for compatibility'],
            'PUT api/v1/topics/{topic}' => ['category' => 'api', 'reason' => 'RESTful topic update — PUT+PATCH combined for compatibility'],
            'PUT api/v1/topics/{topic}/posts/{post}' => ['category' => 'api', 'reason' => 'RESTful post update — PUT+PATCH combined for compatibility'],
            'PUT api/v1/agent-allows/{agent_allow}' => ['category' => 'api', 'reason' => 'RESTful agent-allow update — PUT+PATCH combined for compatibility'],
            'PUT api/v1/agent-denies/{agent_deny}' => ['category' => 'api', 'reason' => 'RESTful agent-deny update — PUT+PATCH combined for compatibility'],
            'PUT api/v1/exams/{exam}' => ['category' => 'api', 'reason' => 'RESTful exam update — PUT+PATCH combined for compatibility'],
            'PUT api/v1/medals/{medal}' => ['category' => 'api', 'reason' => 'RESTful medal update — PUT+PATCH combined for compatibility'],
            'PUT api/v1/user-medals/{user_medal}' => ['category' => 'api', 'reason' => 'RESTful user-medal update — PUT+PATCH combined for compatibility'],
            'PUT api/v1/tags/{tag}' => ['category' => 'api', 'reason' => 'RESTful tag update — PUT+PATCH combined for compatibility'],
            'PUT api/v1/hr/{hr}' => ['category' => 'api', 'reason' => 'RESTful H&R update — PUT+PATCH combined for compatibility'],
            'GET api/v1/usercp/settings' => ['category' => 'form', 'reason' => 'API usercp settings: GET retrieves, POST updates'],

            // --- public.php: genuinely mixed ---
            'GET /faq' => ['category' => 'form', 'reason' => 'FAQ page: GET displays, POST handles FAQ search/action'],
            'GET /donate' => ['category' => 'form', 'reason' => 'Donation page: GET displays info, POST processes donation'],
            'GET /complains' => ['category' => 'form', 'reason' => 'Complaints: GET displays list, POST submits complaint'],
            'GET /shoutbox' => ['category' => 'form', 'reason' => 'Shoutbox: GET displays messages, POST posts new message'],
            'GET /bookmark' => ['category' => 'command', 'reason' => 'Bookmark: GET displays page, POST toggles bookmark'],

            // --- auth.php: form display + submit (legacy NexusPHP pattern) ---
            'GET /offers' => ['category' => 'form', 'reason' => 'Offers: GET displays list, POST creates offer'],
            'GET /mybonus' => ['category' => 'form', 'reason' => 'Bonus shop: GET displays, POST exchanges'],
            'GET /my_bonus' => ['category' => 'form', 'reason' => 'Underscore alias for mybonus'],
            'GET /log' => ['category' => 'form', 'reason' => 'Site log: GET displays, POST filters/deletes'],
            'GET /index' => ['category' => 'form', 'reason' => 'Homepage: GET displays, POST handles poll voting'],
            'GET /friends' => ['category' => 'form', 'reason' => 'Friends: GET displays list, POST adds/removes friend'],
            'GET /messages' => ['category' => 'form', 'reason' => 'Messages: GET displays inbox, POST handles message actions'],
            'GET /getrss' => ['category' => 'form', 'reason' => 'RSS builder: GET displays form, POST generates feed'],
            'GET /sendmessage' => ['category' => 'form', 'reason' => 'Send PM: GET displays form, POST preview'],
            'GET /userhistory' => ['category' => 'form', 'reason' => 'User history: GET displays, POST handles actions'],
            'GET /invite' => ['category' => 'form', 'reason' => 'Invite: GET displays form, POST sends invite'],
            'GET /news' => ['category' => 'form', 'reason' => 'News: GET displays, POST creates/edits news'],
            'GET /makepoll' => ['category' => 'form', 'reason' => 'Poll creation: GET displays form, POST submits'],
            'GET /polloverview' => ['category' => 'form', 'reason' => 'Poll overview: GET displays, POST handles actions'],
            'GET /attendance' => ['category' => 'form', 'reason' => 'Attendance: GET displays calendar, POST marks attendance'],
            'GET /report' => ['category' => 'form', 'reason' => 'Report: GET displays form, POST submits report'],
            'GET /reports' => ['category' => 'form', 'reason' => 'Reports list: GET displays, POST handles actions'],
            'GET /modtask' => ['category' => 'command', 'reason' => 'Moderation task: GET displays form, POST executes moderation action'],
            'GET /staffmess' => ['category' => 'form', 'reason' => 'Mass PM: GET displays form, POST sends'],
            'GET /contactstaff' => ['category' => 'form', 'reason' => 'Contact staff: GET displays form, POST sends message'],
            'GET /modrules' => ['category' => 'form', 'reason' => 'Moderate rules: GET displays, POST edits rules'],
            'GET /user-ban-log' => ['category' => 'form', 'reason' => 'Ban log: GET displays, POST handles actions'],
            'GET /clearcache' => ['category' => 'command', 'reason' => 'Clear cache: GET displays confirmation, POST clears'],
            'GET /donated' => ['category' => 'form', 'reason' => 'Donation list: GET displays, POST handles actions'],
            'GET /faqmanage' => ['category' => 'form', 'reason' => 'FAQ management: GET displays, POST handles CRUD'],
            'GET /faqactions' => ['category' => 'command', 'reason' => 'FAQ actions: GET displays, POST executes reorder/delete'],
            'GET /search' => ['category' => 'form', 'reason' => 'Search: GET displays form, POST performs search'],
            'GET /attachment' => ['category' => 'command', 'reason' => 'Attachment upload: GET displays form, POST uploads file'],
            'GET /getattachment' => ['category' => 'command', 'reason' => 'Attachment download: GET streams file, POST used by some legacy forms'],
            'GET /settings' => ['category' => 'form', 'reason' => 'Settings: GET displays form, POST saves settings'],
            'GET /freeleech' => ['category' => 'form', 'reason' => 'Freeleech shop: GET displays, POST purchases'],
            'GET /location' => ['category' => 'command', 'reason' => 'Location management: GET displays, POST deletes location'],
            'GET /mailtest' => ['category' => 'form', 'reason' => 'Mail test: GET displays form, POST sends test email'],
            'GET /reset' => ['category' => 'form', 'reason' => 'Reset user: GET displays form, POST resets'],
            'GET /self-enable' => ['category' => 'command', 'reason' => 'Self-enable: GET displays, POST enables account'],
            'GET /unco' => ['category' => 'form', 'reason' => 'Unconfirmed users: GET displays, POST confirms'],
            'GET /adduser' => ['category' => 'form', 'reason' => 'Add user: GET displays form, POST creates user'],
            'GET /bitbucketlog' => ['category' => 'command', 'reason' => 'Bitbucket log: GET displays, POST deletes entries'],
            'GET /downloadnotice' => ['category' => 'form', 'reason' => 'Download notice: GET displays, POST acknowledges'],
            'GET /increment-bulk' => ['category' => 'form', 'reason' => 'Bulk increment: GET displays form, POST previews'],
            'GET /setlist_lookup' => ['category' => 'ajax', 'reason' => 'JSON endpoint: reads params from both GET and POST via $request->input()'],
            'GET /testip' => ['category' => 'form', 'reason' => 'IP test: GET displays form, POST tests IP'],
        ];
    }
}

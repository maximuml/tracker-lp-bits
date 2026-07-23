<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class FaqTableSeeder extends Seeder
{

    /**
     * Auto generated seed file
     *
     * @return void
     */
    public function run()
    {
        

        \DB::table('faq')->delete();
        
        \DB::table('faq')->insert(array (
              0 => 
              array (
                'id' => 401,
                'link_id' => 1,
                'lang_id' => 6,
                'type' => 'categ',
                'question' => 'Site information',
                'answer' => '',
                'flag' => 1,
                'categ' => 0,
                'order' => 1,
              ),
              1 => 
              array (
                'id' => 402,
                'link_id' => 2,
                'lang_id' => 6,
                'type' => 'categ',
                'question' => 'User information',
                'answer' => '',
                'flag' => 1,
                'categ' => 0,
                'order' => 2,
              ),
              2 => 
              array (
                'id' => 403,
                'link_id' => 3,
                'lang_id' => 6,
                'type' => 'categ',
                'question' => 'Stats',
                'answer' => '',
                'flag' => 1,
                'categ' => 0,
                'order' => 3,
              ),
              3 => 
              array (
                'id' => 404,
                'link_id' => 4,
                'lang_id' => 6,
                'type' => 'categ',
                'question' => 'Uploading',
                'answer' => '',
                'flag' => 1,
                'categ' => 0,
                'order' => 4,
              ),
              4 => 
              array (
                'id' => 405,
                'link_id' => 5,
                'lang_id' => 6,
                'type' => 'categ',
                'question' => 'Downloading',
                'answer' => '',
                'flag' => 1,
                'categ' => 0,
                'order' => 5,
              ),
              5 => 
              array (
                'id' => 406,
                'link_id' => 6,
                'lang_id' => 6,
                'type' => 'categ',
                'question' => 'How can I improve my download speed?',
                'answer' => '',
                'flag' => 1,
                'categ' => 0,
                'order' => 6,
              ),
              6 => 
              array (
                'id' => 407,
                'link_id' => 7,
                'lang_id' => 6,
                'type' => 'categ',
                'question' => 'My ISP uses a transparent proxy. What should I do?',
                'answer' => '',
                'flag' => 1,
                'categ' => 0,
                'order' => 7,
              ),
              7 => 
              array (
                'id' => 408,
                'link_id' => 8,
                'lang_id' => 6,
                'type' => 'categ',
                'question' => 'Why can\'t I connect? Is the site blocking me?',
                'answer' => '',
                'flag' => 1,
                'categ' => 0,
                'order' => 8,
              ),
              8 => 
              array (
                'id' => 409,
                'link_id' => 9,
                'lang_id' => 6,
                'type' => 'categ',
                'question' => 'What if I can\'t find the answer to my problem here?',
                'answer' => '',
                'flag' => 1,
                'categ' => 0,
                'order' => 9,
              ),
              9 => 
              array (
                'id' => 410,
                'link_id' => 10,
                'lang_id' => 6,
                'type' => 'item',
                'question' => 'What is this bittorrent all about anyway? How do I get the files?',
                'answer' => 'Check out <a class="faqlink" href="http://www.btfaq.com/">Brian\'s BitTorrent FAQ and Guide</a>.',
                'flag' => 1,
                'categ' => 1,
                'order' => 1,
              ),
              10 => 
              array (
                'id' => 411,
                'link_id' => 11,
                'lang_id' => 6,
                'type' => 'item',
                'question' => 'Where does the donated money go?',
                'answer' => 'All donated money goes to the cost of server that this tracker is on.',
                'flag' => 1,
                'categ' => 1,
                'order' => 2,
              ),
              11 => 
              array (
                'id' => 412,
                'link_id' => 12,
                'lang_id' => 6,
                'type' => 'item',
                'question' => 'Where can I get a copy of the source code?',
                'answer' => 'This tracker is powered by NexusPHP. If you like to use NexusPHP to power your tracker, <a class="faqlink" href="aboutnexus.php">Contact Us</a>.<br />
            The first stage of NexusPHP forks from TBSource. You may find more information about TBSource at <a class="faqlink" href="http://www.tbdev.net/">TBDev.net</a>.',
                'flag' => 1,
                'categ' => 1,
                'order' => 3,
              ),
              12 => 
              array (
                'id' => 413,
                'link_id' => 13,
                'lang_id' => 6,
                'type' => 'item',
                'question' => 'I registered an account but did not receive the confirmation e-mail!',
                'answer' => 'One possible reason may be that the network around the tracker has encountered some problems. You can use <a href="confirm_resend.php" class="faqlink">this form</a> ask the tracker to resend confirmation e-mail.<br />
            Typically registered users would be deleted after 24 hours if not confirmed, so you may try again the next day. Note though that if you didn\'t receive the email the first time it will probably not succeed the second time either so you should really try another email address.',
                'flag' => 1,
                'categ' => 2,
                'order' => 1,
              ),
              13 => 
              array (
                'id' => 414,
                'link_id' => 14,
                'lang_id' => 6,
                'type' => 'item',
                'question' => 'I\'ve lost my user name or password! Can you send it to me?',
                'answer' => 'Please use <a class="faqlink" href="recover.php">this form</a> to have the login details mailed back to you.',
                'flag' => 1,
                'categ' => 2,
                'order' => 2,
              ),
              14 => 
              array (
                'id' => 415,
                'link_id' => 15,
                'lang_id' => 6,
                'type' => 'item',
                'question' => 'Can you rename my account? ',
                'answer' => 'No, we do not rename accounts. Don\'t ask for it.',
                'flag' => 1,
                'categ' => 2,
                'order' => 3,
              ),
              15 => 
              array (
                'id' => 416,
                'link_id' => 16,
                'lang_id' => 6,
                'type' => 'item',
                'question' => 'Can you delete my (confirmed) account?',
                'answer' => 'No, we don\'t delete accounts. Don\'t ask for it.',
                'flag' => 1,
                'categ' => 2,
                'order' => 4,
              ),
              16 => 
              array (
                'id' => 417,
                'link_id' => 17,
                'lang_id' => 6,
                'type' => 'item',
                'question' => 'So, what\'s MY ratio?',
                'answer' => 'It\'s on the left-top of pages.<br />
            <br />
            <img src="pic/ratio.png" alt="ratio" />
            <br />
            <br />
            It\'s important to distinguish between your overall ratio and the individual ratio on each torrent you may be seeding or leeching. The overall ratio takes into account the total uploaded and downloaded from your account since you joined the site. The individual ratio takes into account those values for each torrent.
            <br />
            <br />
            You may see two symbols instead of a number: "Inf.", which is just an abbreviation for Infinity, and means that you have downloaded 0 bytes while uploading a non-zero amount (ul/dl becomes infinity); "---", which should be read as "non-available", and shows up when you have both downloaded and uploaded 0 bytes (ul/dl = 0/0 which is an indeterminate amount).
            ',
                'flag' => 1,
                'categ' => 2,
                'order' => 5,
              ),
              17 => 
              array (
                'id' => 418,
                'link_id' => 18,
                'lang_id' => 6,
                'type' => 'item',
                'question' => 'Why is my IP displayed on my details page?',
                'answer' => 'Only you and the site moderators can view your IP address and email. Regular users do not see that information.',
                'flag' => 1,
                'categ' => 2,
                'order' => 6,
              ),
              18 => 
              array (
                'id' => 421,
                'link_id' => 21,
                'lang_id' => 6,
                'type' => 'item',
                'question' => 'Why am I listed as not connectable? (And why should I care?)',
                'answer' => 'The tracker has determined that you are firewalled or NATed and cannot accept incoming connections.
            <br />
            <br />
            This means that other peers in the swarm will be unable to connect to you, only you to them. Even worse, if two peers are both in this state they will not be able to connect at all. This has obviously a detrimental effect on the overall speed.
            <br />
            <br />
            The way to solve the problem involves opening the ports used for incoming connections (the same range you defined in your client) on the firewall and/or configuring your NAT server to use a basic form of NAT for that range instead of NAPT (the actual process differs widely between different router models. Check your router documentation and/or support forum. You will also find lots of information on the subject at <a class="faqlink" href="http://portforward.com/">PortForward</a>).
            ',
                'flag' => 1,
                'categ' => 2,
                'order' => 9,
              ),
              19 => 
              array (
                'id' => 422,
                'link_id' => 22,
                'lang_id' => 6,
                'type' => 'item',
                'question' => 'What are the different user classes?',
                'answer' => '<table cellspacing="3" cellpadding="0">
            <tr>
            <td class="embedded" width="200" valign="top">&nbsp; <b class="Peasant_Name">Peasant</b></td>
            <td class="embedded" width="5">&nbsp;</td>
            <td class="embedded"> Demoted users. They must improve their ratio within 30 days or they will be banned. Cannot post funbox item, apply for links or upload subtitles.
            </td>
            </tr>
            <tr>
            <td class="embedded" valign="top">&nbsp; <b class="User_Name">User</b></td>
            <td class="embedded">&nbsp;</td>
            <td class="embedded">The default class of new members. may upload torrents between 12:00, Saturday and 23:59 Sunday of every week.</td>
            </tr>
            <tr>
            <td class="embedded" valign="top">&nbsp; <b  class="PowerUser_Name">Power User</b></td>
            <td class="embedded">&nbsp;</td>
            <td class="embedded">Get a invitation. Can upload torrents, view NFO files, view user list, ask for reseed, send invitation, access Power User and External Trackers forums, view Top 10, view other users\' torrent history (if user\'s privacy level is not set \'strong\'), delete subtitle uploaded by oneself.</td>
            </tr>
            <tr>
            <td class="embedded" valign="top">&nbsp; <b class="EliteUser_Name">Elite User</b></td>
            <td class="embedded">&nbsp;</td>
            <td class="embedded"><b class="EliteUser_Name">Elite User</b> or above would never be deleted if parked.</td>
            </tr>
            <tr>
            <td class="embedded" valign="top">&nbsp; <b class="CrazyUser_Name">Crazy User</b></td>
            <td class="embedded">&nbsp;</td>
            <td class="embedded">Get two invitations. Can be anonymous when seeding/leeching/uploading. </td>
            </tr>
            <tr>
            <td class="embedded" valign="top">&nbsp; <b class="InsaneUser_Name">Insane User</b></td>
            <td class="embedded">&nbsp;</td>
            <td class="embedded">Can view general logs.</td>
            </tr>
            <tr>
            <td class="embedded" valign="top">&nbsp; <b class="VeteranUser_Name">Veteran User</b></td>
            <td class="embedded">&nbsp;</td>
            <td class="embedded">Get three invitations. Can view other users\' history of comments and forum posts. <b class="VeteranUser_Name">Veteran User</b> or above would never be deleted whether parked or not.</td>
            </tr>
            <tr>
            <td class="embedded" valign="top">&nbsp; <b  class="ExtremeUser_Name">Extreme User</b></td>
            <td class="embedded">&nbsp;</td>
            <td class="embedded">Can update outdated external information and access Extreme User forum.</td>
            </tr>
            <tr>
            <td class="embedded" valign="top">&nbsp; <b  class="UltimateUser_Name">Ultimate User</b></td>
            <td class="embedded">&nbsp;</td>
            <td class="embedded">Get 5 invitations.</td>
            </tr>
            <tr>
            <td class="embedded" valign="top">&nbsp; <b class="NexusMaster_Name">Nexus Master</b></td>
            <td class="embedded">&nbsp;</td>
            <td class="embedded">Get 10 invitations.</td>
            </tr>
            <tr>
            <td class="embedded" valign="top">&nbsp; <img class="star" src="pic/trans.gif" alt="Star" /></td>
            <td class="embedded">&nbsp;</td>
            <td class="embedded">Has donated money to this tracker.</td>
            </tr>
            <tr>
            <td class="embedded" valign="top">&nbsp; <b  class="VIP_Name">VIP</b></td>
            <td class="embedded">&nbsp;</td>
            <td class="embedded" valign="top">Same privileges as <b class="NexusMaster_Name">Nexus Master</b> and is considered an Elite Member of this tracker. Immune to automatic demotion.</td>
            </tr>
            <tr>
            <td class="embedded" valign="top">&nbsp; <b class="Retiree_Name">Retiree</b></td>
            <td class="embedded">&nbsp;</td>
            <td class="embedded">Former staff members.</td>
            </tr>
            <tr>
            <td class="embedded" valign="top">&nbsp; <b class="User_Name">Other</b></td>
            <td class="embedded">&nbsp;</td>
            <td class="embedded">Customized title. </td>
            </tr>
            <tr>
            <td class="embedded" valign="top">&nbsp; <b  class="Uploader_Name">Uploader</b></td>
            <td class="embedded">&nbsp;</td>
            <td class="embedded">Dedicated uploader, immune to automatic demotion. Can view who anonymous ones are.</td>
            </tr>
            <tr>
            <td class="embedded" valign="top">&nbsp; <b  class="Moderator_Name">Moderator</b></td>
            <td class="embedded">&nbsp;</td>
            <td class="embedded" valign="top">Can view staffbox and reportbox, manage funbox and polls, edit and delete any uploaded torrent, manage offers, manage forum posts and user comments, view confidential logs, delete any uploaded subtitle, manage code updates and chronicles at logs, view users\' invitation history, change general user account information. <b>Cannot</b> manage links,recent news or forums. <b>Cannot</b> set torrents sticky or on promotion.<b>Cannot</b> view users\' confidential information (e.g. IP address and Email address). <b>Cannot</b> delete user account.</td>
            </tr>
            <tr>
            <td class="embedded" valign="top">&nbsp; <b  class="Administrator_Name">Administrator</b></td>
            <td class="embedded">&nbsp;</td>
            <td class="embedded">Other than changing site settings and managing donation, can do just about anything.</td>
            </tr>
            <tr>
            <td class="embedded" valign="top">&nbsp; <b  class="SysOp_Name">SysOp</b></td>
            <td class="embedded">&nbsp;</td>
            <td class="embedded">Dedicated site developer. Except managing donation, can do anything (including changing site settings)</td>
            </tr>
            <tr>
            <td class="embedded" valign="top">&nbsp; <b  class="StaffLeader_Name">Staff Leader</b></td>
            <td class="embedded">&nbsp;</td>
            <td class="embedded">The boss. Can do anything.</td>
            </tr>
            </table>',
                'flag' => 1,
                'categ' => 2,
                'order' => 10,
              ),
              20 => 
              array (
                'id' => 423,
                'link_id' => 23,
                'lang_id' => 6,
                'type' => 'item',
                'question' => 'How does this promotion thing work anyway?',
                'answer' => '<table cellspacing=\\\\\\\\\\\\\\"3\\\\\\\\\\\\\\" cellpadding=\\\\\\\\\\\\\\"0\\\\\\\\\\\\\\">
            <tr>
            <td class=\\\\\\\\\\\\\\"embedded\\\\\\\\\\\\\\" width=\\\\\\\\\\\\\\"200\\\\\\\\\\\\\\" valign=\\\\\\\\\\\\\\"top\\\\\\\\\\\\\\">&nbsp; <b class=\\\\\\\\\\\\\\"Peasant_Name\\\\\\\\\\\\\\">Peasant</b></td>
            <td class=\\\\\\\\\\\\\\"embedded\\\\\\\\\\\\\\" width=\\\\\\\\\\\\\\"5\\\\\\\\\\\\\\">&nbsp;</td>
            <td class=\\\\\\\\\\\\\\"embedded\\\\\\\\\\\\\\" valign=\\\\\\\\\\\\\\"top\\\\\\\\\\\\\\">User would be demoted to this class under any of the following circumstances:<br />
            1.Downloaded more than 50 GB and with ratio below 0.4<br />
            2.Downloaded more than 100 GB and with ratio below 0.5<br />
            3.Downloaded more than 200 GB and with ratio below 0.6<br />
            4.Downloaded more than 400 GB and with ratio below 0.7<br />
            5.Downloaded more than 800 GB and with ratio below 0.8</td>
            </tr>
            <tr>
            <td class=\\\\\\\\\\\\\\"embedded\\\\\\\\\\\\\\" valign=\\\\\\\\\\\\\\"top\\\\\\\\\\\\\\">&nbsp; <b  class=\\\\\\\\\\\\\\"PowerUser_Name\\\\\\\\\\\\\\">Power User</b></td>
            <td class=\\\\\\\\\\\\\\"embedded\\\\\\\\\\\\\\">&nbsp;</td>
            <td class=\\\\\\\\\\\\\\"embedded\\\\\\\\\\\\\\" valign=\\\\\\\\\\\\\\"top\\\\\\\\\\\\\\">Must have been a member for at least 4 weeks, have downloaded at least 50GB and have a ratio at or above 1.05. The promotion is automatic when these conditions are met. <br />
            Note that you will be automatically demoted from this status if your ratio drops below 0.95 at any time.</td>
            </tr>
            <tr>
            <td class=\\\\\\\\\\\\\\"embedded\\\\\\\\\\\\\\" valign=\\\\\\\\\\\\\\"top\\\\\\\\\\\\\\">&nbsp; <b class=\\\\\\\\\\\\\\"EliteUser_Name\\\\\\\\\\\\\\">Elite User</b></td>
            <td class=\\\\\\\\\\\\\\"embedded\\\\\\\\\\\\\\">&nbsp;</td>
            <td class=\\\\\\\\\\\\\\"embedded\\\\\\\\\\\\\\" valign=\\\\\\\\\\\\\\"top\\\\\\\\\\\\\\">Must have been a member for at least 8 weeks, have downloaded at least 120GB and have a ratio at or above 1.55. The promotion is automatic when these conditions are met. <br />
            Note that you will be automatically demoted from this status if your ratio drops below 1.45 at any time.</td>
            </tr>
            <tr>
            <td class=\\\\\\\\\\\\\\"embedded\\\\\\\\\\\\\\" valign=\\\\\\\\\\\\\\"top\\\\\\\\\\\\\\">&nbsp; <b class=\\\\\\\\\\\\\\"CrazyUser_Name\\\\\\\\\\\\\\">Crazy User</b></td>
            <td class=\\\\\\\\\\\\\\"embedded\\\\\\\\\\\\\\">&nbsp;</td>
            <td class=\\\\\\\\\\\\\\"embedded\\\\\\\\\\\\\\" valign=\\\\\\\\\\\\\\"top\\\\\\\\\\\\\\">Must have been a member for at least 15 weeks, have downloaded at least 300GB and have a ratio at or above 2.05. The promotion is automatic when these conditions are met. <br />
            Note that you will be automatically demoted from this status if your ratio drops below 1.95 at any time.</td>
            </tr>
            <tr>
            <td class=\\\\\\\\\\\\\\"embedded\\\\\\\\\\\\\\" valign=\\\\\\\\\\\\\\"top\\\\\\\\\\\\\\">&nbsp; <b class=\\\\\\\\\\\\\\"InsaneUser_Name\\\\\\\\\\\\\\">Insane User</b></td>
            <td class=\\\\\\\\\\\\\\"embedded\\\\\\\\\\\\\\">&nbsp;</td>
            <td class=\\\\\\\\\\\\\\"embedded\\\\\\\\\\\\\\" valign=\\\\\\\\\\\\\\"top\\\\\\\\\\\\\\">Must have been a member for at least 25 weeks, have downloaded at least 500GB and have a ratio at or above 2.55. The promotion is automatic when these conditions are met. <br />
            Note that you will be automatically demoted from this status if your ratio drops below 2.45 at any time.</td>
            </tr>
            <tr>
            <td class=\\\\\\\\\\\\\\"embedded\\\\\\\\\\\\\\" valign=\\\\\\\\\\\\\\"top\\\\\\\\\\\\\\">&nbsp; <b class=\\\\\\\\\\\\\\"VeteranUser_Name\\\\\\\\\\\\\\">Veteran User</b></td>
            <td class=\\\\\\\\\\\\\\"embedded\\\\\\\\\\\\\\">&nbsp;</td>
            <td class=\\\\\\\\\\\\\\"embedded\\\\\\\\\\\\\\" valign=\\\\\\\\\\\\\\"top\\\\\\\\\\\\\\">Must have been a member for at least 40 weeks, have downloaded at least 750GB and have a ratio at or above 3.05. The promotion is automatic when these conditions are met. <br />
            Note that you will be automatically demoted from this status if your ratio drops below 2.95 at any time.</td>
            </tr>
            <tr>
            <td class=\\\\\\\\\\\\\\"embedded\\\\\\\\\\\\\\" valign=\\\\\\\\\\\\\\"top\\\\\\\\\\\\\\">&nbsp; <b  class=\\\\\\\\\\\\\\"ExtremeUser_Name\\\\\\\\\\\\\\">Extreme User</b></td>
            <td class=\\\\\\\\\\\\\\"embedded\\\\\\\\\\\\\\">&nbsp;</td>
            <td class=\\\\\\\\\\\\\\"embedded\\\\\\\\\\\\\\" valign=\\\\\\\\\\\\\\"top\\\\\\\\\\\\\\">Must have been a member for at least 60 weeks, have downloaded at least 1TB and have a ratio at or above 3.55. The promotion is automatic when these conditions are met. <br />
            Note that you will be automatically demoted from this status if your ratio drops below 3.45 at any time.</td>
            </tr>
            <tr>
            <td class=\\\\\\\\\\\\\\"embedded\\\\\\\\\\\\\\" valign=\\\\\\\\\\\\\\"top\\\\\\\\\\\\\\">&nbsp; <b  class=\\\\\\\\\\\\\\"UltimateUser_Name\\\\\\\\\\\\\\">Ultimate User</b></td>
            <td class=\\\\\\\\\\\\\\"embedded\\\\\\\\\\\\\\">&nbsp;</td>
            <td class=\\\\\\\\\\\\\\"embedded\\\\\\\\\\\\\\" valign=\\\\\\\\\\\\\\"top\\\\\\\\\\\\\\">Must have been a member for at least 80 weeks, have downloaded at least 1.5TB and have a ratio at or above 4.05. The promotion is automatic when these conditions are met. <br />
            Note that you will be automatically demoted from this status if your ratio drops below 3.95 at any time.</td>
            </tr>
            <tr>
            <td class=\\\\\\\\\\\\\\"embedded\\\\\\\\\\\\\\" valign=\\\\\\\\\\\\\\"top\\\\\\\\\\\\\\">&nbsp; <b class=\\\\\\\\\\\\\\"NexusMaster_Name\\\\\\\\\\\\\\">Nexus Master</b></td>
            <td class=\\\\\\\\\\\\\\"embedded\\\\\\\\\\\\\\">&nbsp;</td>
            <td class=\\\\\\\\\\\\\\"embedded\\\\\\\\\\\\\\" valign=\\\\\\\\\\\\\\"top\\\\\\\\\\\\\\">Must have been a member for at least 100 weeks, have downloaded at least 3TB and have a ratio at or above 4.55. The promotion is automatic when these conditions are met. <br />
            Note that you will be automatically demoted from this status if your ratio drops below 4.45 at any time.</td>
            </tr>
            <tr>
            <td class=\\\\\\\\\\\\\\"embedded\\\\\\\\\\\\\\" valign=\\\\\\\\\\\\\\"top\\\\\\\\\\\\\\">&nbsp; <img class=\\\\\\\\\\\\\\"star\\\\\\\\\\\\\\" src=\\\\\\\\\\\\\\"pic/trans.gif\\\\\\\\\\\\\\" alt=\\\\\\\\\\\\\\"Star\\\\\\\\\\\\\\" /></td>
            <td class=\\\\\\\\\\\\\\"embedded\\\\\\\\\\\\\\">&nbsp;</td>
            <td class=\\\\\\\\\\\\\\"embedded\\\\\\\\\\\\\\">Just donate, see <a class=\\\\\\\\\\\\\\"faqlink\\\\\\\\\\\\\\" href=\\\\\\\\\\\\\\"donate.php\\\\\\\\\\\\\\">here</a> for the details.</td>
            </tr>
            <tr>
            <td class=\\\\\\\\\\\\\\"embedded\\\\\\\\\\\\\\" valign=\\\\\\\\\\\\\\"top\\\\\\\\\\\\\\">&nbsp; <b  class=\\\\\\\\\\\\\\"VIP_Name\\\\\\\\\\\\\\">VIP</b></td>
            <td class=\\\\\\\\\\\\\\"embedded\\\\\\\\\\\\\\">&nbsp;</td>
            <td class=\\\\\\\\\\\\\\"embedded\\\\\\\\\\\\\\" valign=\\\\\\\\\\\\\\"top\\\\\\\\\\\\\\">Assigned by mods at their discretion to users they feel contribute something special to the site. (Anyone begging for VIP status will be automatically disqualified.)</td>
            </tr>
            <tr>
            <td class=\\\\\\\\\\\\\\"embedded\\\\\\\\\\\\\\" valign=\\\\\\\\\\\\\\"top\\\\\\\\\\\\\\">&nbsp; <b class=\\\\\\\\\\\\\\"User_Name\\\\\\\\\\\\\\">Other</b></td>
            <td class=\\\\\\\\\\\\\\"embedded\\\\\\\\\\\\\\">&nbsp;</td>
            <td class=\\\\\\\\\\\\\\"embedded\\\\\\\\\\\\\\">Customized title. Exchanged at bonus system or granted by admins.</td>
            </tr>
            <tr>
            <td class=\\\\\\\\\\\\\\"embedded\\\\\\\\\\\\\\" valign=\\\\\\\\\\\\\\"top\\\\\\\\\\\\\\">&nbsp; <b  class=\\\\\\\\\\\\\\"Uploader_Name\\\\\\\\\\\\\\">Uploader</b></td>
            <td class=\\\\\\\\\\\\\\"embedded\\\\\\\\\\\\\\">&nbsp;</td>
            <td class=\\\\\\\\\\\\\\"embedded\\\\\\\\\\\\\\">Appointed by Admins/SysOp/Staff Leader (see the \\\\\\\\\\\\\\\'Uploading\\\\\\\\\\\\\\\' section for conditions).</td>
            </tr>
            <tr>
            <td class=\\\\\\\\\\\\\\"embedded\\\\\\\\\\\\\\" valign=\\\\\\\\\\\\\\"top\\\\\\\\\\\\\\">&nbsp; <b class=\\\\\\\\\\\\\\"Retiree_Name\\\\\\\\\\\\\\">Retiree</b></td>
            <td class=\\\\\\\\\\\\\\"embedded\\\\\\\\\\\\\\">&nbsp;</td>
            <td class=\\\\\\\\\\\\\\"embedded\\\\\\\\\\\\\\">Granted by Admins/SysOp/Staff Leader</td>
            </tr>
            <tr>
            <td class=\\\\\\\\\\\\\\"embedded\\\\\\\\\\\\\\" valign=\\\\\\\\\\\\\\"top\\\\\\\\\\\\\\">&nbsp; <b  class=\\\\\\\\\\\\\\"Moderator_Name\\\\\\\\\\\\\\">Moderator</b></td>
            <td class=\\\\\\\\\\\\\\"embedded\\\\\\\\\\\\\\">&nbsp;</td>
            <td class=\\\\\\\\\\\\\\"embedded\\\\\\\\\\\\\\">You don\\\\\\\\\\\\\\\'t ask us, we\\\\\\\\\\\\\\\'ll ask you!</td>
            </tr>
            </table>',
                'flag' => 0,
                'categ' => 2,
                'order' => 11,
              ),
              21 => 
              array (
                'id' => 425,
                'link_id' => 25,
                'lang_id' => 6,
                'type' => 'item',
                'question' => 'Why can\'t my friend become a member?',
                'answer' => 'There is a users limit (it is list at Home -&gt; Tracker Statistics -&gt; Limit). When that number is reached we stop accepting new members. Accounts inactive (i.e. not logged in for a long time) are automatically deleted, so keep trying.<br />
            When are inactive user accounts deleted? See <a class="faqlink" href="rules.php">Rules</a>',
                'flag' => 1,
                'categ' => 2,
                'order' => 13,
              ),
              22 => 
              array (
                'id' => 426,
                'link_id' => 26,
                'lang_id' => 6,
                'type' => 'item',
                'question' => 'How do I add an avatar to my profile?',
                'answer' => 'First, find an image that you like, and that is within the <a class="faqlink" href="rules.php">rules</a>. Then you will have to find a place to host it, such as our own <a class="faqlink" href="bitbucket-upload.php">BitBucket</a>. To lighten tracker\'s load, we recommend you upload it to other websites and copy the URL you were given when uploading it to the Avatar URL field in <a class="faqlink" href="usercp.php?action=personal">UserCP</a>.<br />
            <br />
            Please do not make a post just to test your avatar. If everything is all right you\'ll see it in your details page. ',
                'flag' => 1,
                'categ' => 2,
                'order' => 14,
              ),
              23 => 
              array (
                'id' => 427,
                'link_id' => 27,
                'lang_id' => 6,
                'type' => 'item',
                'question' => 'Most common reason for stats not updating',
                'answer' => '<ul>
            <li>The server is overloaded and unresponsive. Just try to keep the session open until the server responds again. (Flooding the server with consecutive manual updates is not recommended.)</li>
            <li>You are using a faulty client. If you want to use an experimental or CVS version you do it at your own risk.</li>
            </ul>',
                'flag' => 1,
                'categ' => 3,
                'order' => 1,
              ),
              24 => 
              array (
                'id' => 428,
                'link_id' => 28,
                'lang_id' => 6,
                'type' => 'item',
                'question' => 'Best practices',
                'answer' => '<ul>
            <li>If a torrent you are currently leeching/seeding is not listed on your detail page, just wait or force a manual update.</li>
            <li>Make sure you exit your client properly, so that the tracker receives "event=completed".</li>
            <li>If the tracker is down, do not stop seeding. As long as the tracker is back up before you exit the client the stats should update properly.</li>
            </ul>',
                'flag' => 1,
                'categ' => 3,
                'order' => 2,
              ),
              25 => 
              array (
                'id' => 429,
                'link_id' => 29,
                'lang_id' => 6,
                'type' => 'item',
                'question' => 'May I use any bittorrent client?',
                'answer' => 'No. According to tests of common bittorrent clients by <a class="faqlink" href="aboutnexus.php">NexusPHP</a>, we allowed <b>only</b> the following bittorrent clients.<br />
            The test report by <a class="faqlink" href="aboutnexus.php">NexusPHP</a> is <a class="faqlink" href="https://nexusphp.org/wiki/%E5%AE%A2%E6%88%B7%E7%AB%AF%E6%B5%8B%E8%AF%95%E6%8A%A5%E5%91%8A">here</a>.
            <br />
            <b>Windows:</b>
            <ul>
            <li><a class="faqlink" href="http://azureus.sourceforge.net">Azureus</a>: 2.5.0.4, 3.0.5.0, 3.0.5.2 and later versions</li>
            <li><a class="faqlink" href="http://www.utorrent.com">uTorrent</a>: 1.6.1, 1.7.5, 1.7.6, 1.7.7, 1.8Beta(Build 10364), 2.0(Build 17624) and later versions</li>
            <li><a class="faqlink" href="http://www.bittorrent.com">BitTorrent</a>: 6.0.1, 6.0.2, 6.0.3 and later versions</li>
            <li><a class="faqlink" href="http://deluge-torrent.org">Deluge</a>: 0.5.9.1, 1.1.6 and later versions</li>
            <li><a class="faqlink" href="http://rufus.sourceforge.net">Rufus</a>: 0.6.9, 0.7.0 and later versions</li>
            </ul>
            <b>Linux:</b>
            <ul>
            <li><a class="faqlink" href="http://azureus.sourceforge.net">Azureus</a>: 2.5.0.4, 3.0.5.0, 3.0.5.2 and later versions</li>
            <li><a class="faqlink" href="http://deluge-torrent.org">Deluge</a>: 0.5.9.1, 1.1.6 and later versions</li>
            <li><a class="faqlink" href="http://rufus.sourceforge.net">Rufus</a>: 0.6.9, 0.7.0 and later versions</li>
            <li><a class="faqlink" href="http://www.transmissionbt.com">Transmission</a>: 1.21 and later versions</li>
            <li><a class="faqlink" href="http://libtorrent.rakshasa.no">rTorrent</a>: 0.8.0(with libtorrent 0.12.0 or later) and later versions</li>
            <li><a class="faqlink" href="http://www.rahul.net/dholmes/ctorrent/">Enhanced CTorrent</a>: 3.3.2 and later versions</li>
            </ul>
            <b>MacOS X:</b>
            <ul>
            <li><a class="faqlink" href="http://azureus.sourceforge.net">Azureus</a>: 2.5.0.4, 3.0.5.0, 3.0.5.2 and later versions</li>
            <li><a class="faqlink" href="http://www.transmissionbt.com">Transmission</a>: 1.21 and later versions</li>
            <li><a class="faqlink" href="http://sourceforge.net/projects/bitrocket/">BitRocket</a>: 0.3.3(32) and later versions</li>
            </ul>
            <b>Symbian (For Testing Only):</b>
            <ul>
            <li><a class="faqlink" href="http://amorg.aut.bme.hu/projects/symtorrent">SymTorrent</a>: 1.41 and later versions</li>
            </ul>
            <br />
            <b>Support for https:</b>
            <ul>
            <li>uTorrent 1.61: cannot parse tracker https url, and marks itself as uTorrent 1.5</li>
            <li>Rufus: no support for https, and development ceased for years.</li>
            <li>rtorrent: needs to add SSL certification manually, see User Guide at its official site.</li>
            </ul>
            <br />
            Please do not use any beta or testing version of bittorrent clients, e.g. uTorrent 1.8.0B. To get the best downloading experience, we highly recommend latest stable version of <a class="faqlink" href="http://www.utorrent.com/download.php">uTorrent</a> and <a class="faqlink" href="http://azureus.sourceforge.net/download.php">Azureus</a>.',
                'flag' => 1,
                'categ' => 5,
                'order' => 3,
              ),
              26 => 
              array (
                'id' => 430,
                'link_id' => 30,
                'lang_id' => 6,
                'type' => 'item',
                'question' => 'Why is a torrent I\'m leeching/seeding listed several times in my profile?',
                'answer' => 'If for some reason (e.g. PC crash, or frozen client) your client exits improperly and you restart it, it will have a new peer_id, so it will show as a new torrent. The old one will never receive a "event=completed" or "event=stopped" and will be listed until some tracker timeout. Just ignore it, it will eventually go away.',
                'flag' => 1,
                'categ' => 3,
                'order' => 4,
              ),
              27 => 
              array (
                'id' => 431,
                'link_id' => 31,
                'lang_id' => 6,
                'type' => 'item',
                'question' => 'I\'ve finished or cancelled a torrent. Why is it still listed in my profile?',
                'answer' => 'Some clients, notably TorrentStorm and Nova Torrent, do not report properly to the tracker when canceling or finishing a torrent. In that case the tracker will keep waiting for some message - and thus listing the torrent as seeding or leeching - until some timeout occurs. Just ignore it, it will eventually go away.',
                'flag' => 1,
                'categ' => 3,
                'order' => 5,
              ),
              28 => 
              array (
                'id' => 433,
                'link_id' => 33,
                'lang_id' => 6,
                'type' => 'item',
                'question' => 'Multiple IPs (Can I login from different computers?)',
                'answer' => 'Yes, the tracker is capable of following sessions from different IPs for the same user. You may access the site and seed/leech simultaneously from as many computers as you want with the same account.<br />
            However, there is a limit for a single torrent. Per torrent 3 simultaneous connections are permitted per user, and in case of leeching only 1, which means you can leech a torrent from one location only at a time.',
                'flag' => 1,
                'categ' => 3,
                'order' => 7,
              ),
              29 => 
              array (
                'id' => 436,
                'link_id' => 36,
                'lang_id' => 6,
                'type' => 'item',
                'question' => 'Why can\'t I upload torrents?',
                'answer' => 'See <a class="faqlink" href="rules.php">Rules</a>.',
                'flag' => 1,
                'categ' => 4,
                'order' => 1,
              ),
              30 => 
              array (
                'id' => 437,
                'link_id' => 37,
                'lang_id' => 6,
                'type' => 'item',
                'question' => 'What criteria must I meet before I can join the Uploader team?',
                'answer' => 'You must:
            <ul>
            <li>have steady access to resources.</li>
            <li>upload not less than 5 torrents per week.</li>
            </ul>
            You must be able to provide releases that:
            <ul>
            <li>are not older than 7 days</li>
            <li>you\'ll be able to seed, or make sure are well-seeded, for at least 24 hours.</li>
            <li>Also, you should have at least 2MBit upload bandwith.</li>
            </ul>
            If you think you can match these criteria do not hesitate to <a class="faqlink" href="contactstaff.php">contact the staff</a>.<br />
            <b>Remember!</b> Write your application carefully! Be sure to include your upload speed and what kind of stuff you\'re planning to upload.<br />
            Only well written letters with serious intent will be considered.',
                'flag' => 1,
                'categ' => 4,
                'order' => 2,
              ),
              31 => 
              array (
                'id' => 438,
                'link_id' => 38,
                'lang_id' => 6,
                'type' => 'item',
                'question' => 'Can I upload your torrents to other trackers?',
                'answer' => 'No. We are a closed, limited-membership community. Only registered users can use the tracker. Posting our torrents on other trackers is useless, since most people who attempt to download them will be unable to connect with us. This generates a lot of frustration and bad-will against us, and will therefore not be tolerated.<br />
            <br />
            Complaints from other sites\' administrative staff about our torrents being posted on their sites will result in the banning of the users responsible.
            <br />
            <br />
            However, the files you download from us are yours to do as you please (except those marked as <b>EXCLUSIVE</b> by the uploader). You can always create another torrent, pointing to some other tracker, and upload it to the site of your choice.',
                'flag' => 1,
                'categ' => 4,
                'order' => 3,
              ),
              32 => 
              array (
                'id' => 439,
                'link_id' => 39,
                'lang_id' => 6,
                'type' => 'item',
                'question' => 'How do I use the files I\'ve downloaded?',
                'answer' => 'Check out <a class="faqlink" href="formats.php">this guide</a>.',
                'flag' => 1,
                'categ' => 5,
                'order' => 1,
              ),
              33 => 
              array (
                'id' => 440,
                'link_id' => 40,
                'lang_id' => 6,
                'type' => 'item',
                'question' => 'Downloaded a movie and don\'t know what CAM/TS/TC/SCR means?',
                'answer' => 'Check out <a class="faqlink" href="videoformats.php">this guide</a>.',
                'flag' => 1,
                'categ' => 5,
                'order' => 2,
              ),
              34 => 
              array (
                'id' => 441,
                'link_id' => 41,
                'lang_id' => 6,
                'type' => 'item',
                'question' => 'Why did an active torrent suddenly disappear?',
                'answer' => 'There may be three reasons for this:<br />
            (<b>1</b>) The torrent may have been against the site <a class="faqlink" href="rules.php">rules</a>.
            <br />
            (<b>2</b>) The uploader may have deleted it because it was a bad release. A replacement will probably be uploaded to take its place.<br />
            (<b>3</b>) Torrents are automatically deleted after being dead for a long time.',
                'flag' => 1,
                'categ' => 5,
                'order' => 3,
              ),
              35 => 
              array (
                'id' => 442,
                'link_id' => 42,
                'lang_id' => 6,
                'type' => 'item',
                'question' => 'How do I resume a broken download or reseed something?',
                'answer' => 'Open the .torrent file. When your client asks you for a location, choose the location of the existing file(s) and it will resume/reseed the torrent.',
                'flag' => 1,
                'categ' => 5,
                'order' => 4,
              ),
              36 => 
              array (
                'id' => 443,
                'link_id' => 43,
                'lang_id' => 6,
                'type' => 'item',
                'question' => 'Why do my downloads sometimes stall at 99%?',
                'answer' => 'The more pieces you have, the harder it becomes to find peers who have pieces you are missing. That is why downloads sometimes slow down or even stall when there are just a few percent remaining. Just be patient and you will, sooner or later, get the remaining pieces.',
                'flag' => 1,
                'categ' => 5,
                'order' => 5,
              ),
              37 => 
              array (
                'id' => 444,
                'link_id' => 44,
                'lang_id' => 6,
                'type' => 'item',
                'question' => 'What are these "a piece has failed a hash check" messages? ',
                'answer' => 'Bittorrent clients check the data they receive for integrity. When a piece fails this check it is automatically re-downloaded. Occasional hash fails are a common occurrence, and you shouldn\'t worry.<br />
            <br />
            Some clients have an (advanced) option/preference to \'kick/ban clients that send you bad data\' or similar. It should be turned on, since it makes sure that if a peer repeatedly sends you pieces that fail the hash check it will be ignored in the future.',
                'flag' => 1,
                'categ' => 5,
                'order' => 6,
              ),
              38 => 
              array (
                'id' => 445,
                'link_id' => 45,
                'lang_id' => 6,
                'type' => 'item',
                'question' => 'The torrent is supposed to be 100MB. How come I downloaded 120MB? ',
                'answer' => 'See the hash fails topic. If your client receives bad data it will have to re-download it, therefore the total downloaded may be larger than the torrent size. Make sure the "kick/ban" option is turned on to minimize the extra downloads.',
                'flag' => 1,
                'categ' => 5,
                'order' => 7,
              ),
              39 => 
              array (
                'id' => 446,
                'link_id' => 46,
                'lang_id' => 6,
                'type' => 'item',
                'question' => 'Why do I get a "Your ratio is too low! You need to wait xx h to start" error?',
                'answer' => 'From the time that each <b>new</b> torrent is uploaded to the tracker, there is a period of time that some users must wait before they can download it.<br />
            This delay in downloading will only affect users with a low ratio and downloaded amount above 10 GB.<br />
            <br />
            <table cellspacing="3" cellpadding="0">
            <tr>
            <td class="embedded" width="100">Ratio below</td>
            <td class="embedded" width="40">0.4</td>
            <td class="embedded" width="10">&nbsp;</td>
            <td class="embedded" width="100">delay of</td>
            <td class="embedded" width="100">24h</td>
            </tr>
            <tr>
            <td class="embedded" width="100">Ratio below</td>
            <td class="embedded" width="40">0.5</td>
            <td class="embedded" width="10">&nbsp;</td>
            <td class="embedded" width="100">delay of</td>
            <td class="embedded" width="100">12h</td>
            </tr>
            <tr>
            <td class="embedded" width="100">Ratio below</td>
            <td class="embedded" width="40">0.6</td>
            <td class="embedded" width="10">&nbsp;</td>
            <td class="embedded" width="100">delay of</td>
            <td class="embedded" width="100">6h</td>
            </tr>
            <tr>
            <td class="embedded" width="100">Ratio below</td>
            <td class="embedded" width="40">0.8</td>
            <td class="embedded" width="10">&nbsp;</td>
            <td class="embedded" width="100">delay of</td>
            <td class="embedded" width="100">3h</td>
            </tr>
            </table>',
                'flag' => 0,
                'categ' => 5,
                'order' => 8,
              ),
              40 => 
              array (
                'id' => 447,
                'link_id' => 47,
                'lang_id' => 6,
                'type' => 'item',
                'question' => 'Why do I get a "Port xxxx is blacklisted" error?',
                'answer' => 'Your client is reporting to the tracker that it uses one of the default bittorrent ports (6881-6889) or any other common p2p port for incoming connections.<br />
            <br />
            We does not allow clients to use ports commonly associated with p2p protocols. The reason for this is that it is a common practice for ISPs to throttle those ports (that is, limit the bandwidth, hence the speed). <br />
            <br />
            The blocked ports list include the following:<br />
            <br />
            <table cellspacing="3" cellpadding="0">
            <tr>
            <td class="embedded" width="100">Direct Connect</td>
            <td class="embedded" width="100">411 - 413</td>
            </tr>
            <tr>
            <td class="embedded" width="100">BitTorrent</td>
            <td class="embedded" width="100">6881 - 6889</td>
            </tr>
            <tr>
            <td class="embedded" width="100">Kazza</td>
            <td class="embedded" width="100">1214</td>
            </tr>
            <tr>
            <td class="embedded" width="100">Gnutella</td>
            <td class="embedded" width="100">6346 - 6347</td>
            </tr>
            <tr>
            <td class="embedded" width="100">Emule</td>
            <td class="embedded" width="100">4662</td>
            </tr>
            <tr>
            <td class="embedded" width="100">WinMX</td>
            <td class="embedded" width="100">6699</td>
            </tr>
            </table>
            <br />
            In order to use our tracker you must configure your client to use any port range that does not contain those ports (a range within the region 49152 through 65535 is preferable,
            cf. <a class="faqlink" href="http://www.iana.org/assignments/port-numbers">IANA</a>). Notice that some clients, like Azureus 2.0.7.0 or higher, use a single port for all torrents, while most others use one port per open torrent. The size of the range you choose should take this into account (typically less than 10 ports wide. There is no benefit whatsoever in choosing a wide range, and there are possible security implications). <br />
            <br />
            These ports are used for connections between peers, not client to tracker. Therefore this change will not interfere with your ability to use other trackers (in fact it should <i>increase</i> your speed with torrents from any tracker, not just ours). Your client will also still be able to connect to peers that are using the standard ports. If your client does not allow custom ports to be used, you will have to switch to one that does.<br />
            <br />
            Do not ask us, or in the forums, which ports you should choose. The more random the choice is the harder it will be for ISPs to catch on to us and start limiting speeds on the ports we use. If we simply define another range ISPs will start throttling that range also. <br />
            <br />
            Finally, remember to forward the chosen ports in your router and/or open them in your
            firewall, should you have them.',
                'flag' => 1,
                'categ' => 5,
                'order' => 9,
              ),
              41 => 
              array (
                'id' => 448,
                'link_id' => 48,
                'lang_id' => 6,
                'type' => 'item',
                'question' => 'What\'s this "IOError - [Errno13] Permission denied" error?',
                'answer' => 'If you just want to fix it reboot your computer, it should solve the problem. Otherwise read on.<br />
            IOError means Input-Output Error, and that is a file system error, not a tracker one. It shows up when your client is for some reason unable to open the partially downloaded torrent files. The most common cause is two instances of the client to be running simultaneously: the last time the client was closed it somehow didn\'t really close but kept running in the background, and is therefore still locking the files, making it impossible for the new instance to open them.<br />
            A more uncommon occurrence is a corrupted FAT. A crash may result in corruption that makes the partially downloaded files unreadable, and the error ensues. Running scandisk should solve the problem. (Note that this may happen only if you\'re running Windows 9x - which only support FAT - or NT/2000/XP with FAT formatted hard drives. NTFS is much more robust and should never permit this problem.)
            ',
                'flag' => 1,
                'categ' => 5,
                'order' => 10,
              ),
              42 => 
              array (
                'id' => 450,
                'link_id' => 50,
                'lang_id' => 6,
                'type' => 'item',
                'question' => 'Do not immediately jump on new torrents',
                'answer' => 'The download speed mostly depends on the seeder-to-leecher ratio (SLR). Poor download speed is mainly a problem with new and very popular torrents where the SLR is low.<br />
            (Note: make sure you remember that you did not enjoy the low speed. Seed so that others will not endure the same.)<br />
            <br />In particular, do not do it if you have a slow connection. The best speeds will be found around the half-life of a torrent, when the SLR will be at its highest. (The downside is that you will not be able to seed so much. It\'s up to you to balance the pros and cons of this.)',
                'flag' => 1,
                'categ' => 6,
                'order' => 1,
              ),
              43 => 
              array (
                'id' => 451,
                'link_id' => 51,
                'lang_id' => 6,
                'type' => 'item',
                'question' => 'Limit your upload speed',
                'answer' => 'The upload speed affects the download speed in essentially two ways:
            <ul>
            <li>Bittorrent peers tend to favour those other peers that upload to them. This means that if A and B are leeching the same torrent and A is sending data to B at high speed then B will try to reciprocate. So due to this effect high upload speeds lead to high download speeds.</li>
            <li>Due to the way TCP works, when A is downloading something from B it has to keep telling B that it received the data sent to him. (These are called acknowledgements - ACKs -, a sort of "got it!" messages). If A fails to do this then B will stop sending data and wait. If A is uploading at full speed there may be no bandwidth left for the ACKs and they will be delayed. So due to this effect excessively high upload speeds lead to low download speeds.</li>
            </ul>
            The full effect is a combination of the two. The upload should be kept as high as possible while allowing the ACKs to get through without delay. <b>A good thumb rule is keeping the upload at about 80% of the theoretical upload speed. </b>You will have to fine tune yours to find out what works best for you. (Remember that keeping the upload high has the additional benefit of helping with your ratio.) <br />
            <br />
            If you are running more than one instance of a client it is the overall upload speed that you must take into account. Some clients limit global upload speed, others do it on a per torrent basis. Know your client. The same applies if you are using your connection for anything else (e.g. browsing or ftp), always think of the overall upload speed.',
                'flag' => 1,
                'categ' => 6,
                'order' => 2,
              ),
              44 => 
              array (
                'id' => 452,
                'link_id' => 52,
                'lang_id' => 6,
                'type' => 'item',
                'question' => 'Limit the number of simultaneous connections',
                'answer' => 'Some operating systems (like Windows 9x) do not deal well with a large number of connections, and may even crash. Also some home routers (particularly when running NAT and/or firewall with stateful inspection services) tend to become slow or crash when having to deal with too many connections. There are no fixed values for this, you may try 60 or 100 and experiment with the value. Note that these numbers are additive, if you have two instances of a client running the numbers add up.',
                'flag' => 1,
                'categ' => 6,
                'order' => 3,
              ),
              45 => 
              array (
                'id' => 453,
                'link_id' => 53,
                'lang_id' => 6,
                'type' => 'item',
                'question' => 'Limit the number of simultaneous uploads',
                'answer' => 'Isn\'t this the same as above? No. Connections limit the number of peers your client is talking to and/or downloading from. Uploads limit the number of peers your client is actually uploading to. The ideal number is typically much lower than the number of connections, and highly dependent on your (physical) connection.',
                'flag' => 1,
                'categ' => 6,
                'order' => 4,
              ),
              46 => 
              array (
                'id' => 454,
                'link_id' => 54,
                'lang_id' => 6,
                'type' => 'item',
                'question' => 'Just give it some time',
                'answer' => 'As explained above peers favour other peers that upload to them. When you start leeching a new torrent you have nothing to offer to other peers and they will tend to ignore you. This makes the starts slow, in particular if, by chance, the peers you are connected to include few or no seeders. The download speed should increase as soon as you have some pieces to share.',
                'flag' => 1,
                'categ' => 6,
                'order' => 5,
              ),
              47 => 
              array (
                'id' => 455,
                'link_id' => 55,
                'lang_id' => 6,
                'type' => 'item',
                'question' => 'Why is my browsing so slow while leeching?',
                'answer' => 'Your download speed is always finite. If you are a peer in a fast torrent it will almost certainly saturate your download bandwidth, and your browsing will suffer. Many clients allows you to limit the download speed and try it.<br />
            <br />
            Browsing was used just as an example, the same would apply to gaming, IMing, etc...',
                'flag' => 1,
                'categ' => 6,
                'order' => 6,
              ),
              48 => 
              array (
                'id' => 456,
                'link_id' => 56,
                'lang_id' => 6,
                'type' => 'item',
                'question' => 'What is a proxy?',
                'answer' => 'Basically a middleman. When you are browsing a site through a proxy your requests are sent to the proxy and the proxy forwards them to the site instead of you connecting directly to the site. There are several classifications (the terminology is far from standard):<br />
            <br />
            <table cellspacing="3" cellpadding="0">
            <tr>
            <td class="embedded" valign="top" width="100">&nbsp;Transparent</td>
            <td class="embedded" width="10">&nbsp;</td>
            <td class="embedded" valign="top">A transparent proxy is one that needs no configuration on the clients. It works by automatically redirecting all port 80 traffic to the proxy. (Sometimes used as synonymous for non-anonymous.)</td>
            </tr>
            <tr>
            <td class="embedded" valign="top">&nbsp;Explicit/Voluntary</td>
            <td class="embedded" width="10">&nbsp;</td>
            <td class="embedded" valign="top">Clients must configure their browsers to use them.</td>
            </tr>
            <tr>
            <td class="embedded" valign="top">&nbsp;Anonymous</td>
            <td class="embedded" width="10">&nbsp;</td>
            <td class="embedded" valign="top">The proxy sends no client identification to the server. (HTTP_X_FORWARDED_FOR header is not sent; the server does not see your IP.)</td>
            </tr>
            <tr>
            <td class="embedded" valign="top">&nbsp;Highly Anonymous</td>
            <td class="embedded" width="10">&nbsp;</td>
            <td class="embedded" valign="top">The proxy sends no client nor proxy identification to the server. (HTTP_X_FORWARDED_FOR, HTTP_VIA and HTTP_PROXY_CONNECTION headers are not sent; the server doesn\'t see your IP and doesn\'t even know you\'re using a proxy.)</td>
            </tr>
            <tr>
            <td class="embedded" valign="top">&nbsp;Public</td>
            <td class="embedded" width="10">&nbsp;</td>
            <td class="embedded" valign="top">(Self explanatory)</td>
            </tr>
            </table>
            <br />
            A transparent proxy may or may not be anonymous, and there are several levels of anonymity.',
                'flag' => 1,
                'categ' => 7,
                'order' => 1,
              ),
              49 => 
              array (
                'id' => 457,
                'link_id' => 57,
                'lang_id' => 6,
                'type' => 'item',
                'question' => 'How do I find out if I\'m behind a (transparent/anonymous) proxy?',
                'answer' => 'Try <a href="http://proxyjudge.org" class="faqlink">ProxyJudge</a>. It lists the HTTP headers that the server where it is running received from you. The relevant ones are HTTP_CLIENT_IP, HTTP_X_FORWARDED_FOR and REMOTE_ADDR.',
                'flag' => 1,
                'categ' => 7,
                'order' => 2,
              ),
              50 => 
              array (
                'id' => 475,
                'link_id' => 75,
                'lang_id' => 6,
                'type' => 'item',
                'question' => 'Why am I listed as not connectable even though I\'m not NAT/Firewalled?',
                'answer' => 'The tracker is quite smart at finding your real IP, but it does need the proxy to send the HTTP header HTTP_X_FORWARDED_FOR. If your ISP\'s proxy does not then what happens is that the tracker will interpret the proxy\'s IP address as the client\'s IP address. So when you login and the tracker tries to connect to your client to see if you are NAT/firewalled it will actually try to connect to the proxy on the port your client reports to be using for incoming connections. Naturally the proxy will not be listening on that port, the connection will fail and the tracker will think you are NAT/firewalled.',
                'flag' => 1,
                'categ' => 7,
                'order' => 3,
              ),
              51 => 
              array (
                'id' => 462,
                'link_id' => 62,
                'lang_id' => 6,
                'type' => 'item',
                'question' => 'Maybe my address is blacklisted?',
                'answer' => 'The site keeps a list of blocked IP addresses for banned users or attackers. This works at Apache/PHP level, and only blocks <i>logins</i> from those addresses. It should not stop you from reaching the site. In particular it does not block lower level protocols, you should be able to ping/traceroute the server even if your address is blacklisted. If you cannot then the reason for the problem lies elsewhere.<br />
            <br />
            If somehow your address is blocked by mistake, contact us about it.',
                'flag' => 1,
                'categ' => 8,
                'order' => 1,
              ),
              52 => 
              array (
                'id' => 463,
                'link_id' => 63,
                'lang_id' => 6,
                'type' => 'item',
                'question' => 'Your ISP blocks the site\'s address',
                'answer' => '(In first place, it\'s unlikely your ISP is doing so. DNS name resolution and/or network problems are the usual culprits.)
            <br />
            There\'s nothing we can do. You should contact your ISP (or get a new one). Note that you can still visit the site via a proxy, follow the instructions in the relevant section. In this case it doesn\'t matter if the proxy is anonymous or not, or which port it listens to.<br />
            <br />
            Notice that you will always be listed as an "unconnectable" client because the tracker will be unable to check that you\'re capable of accepting incoming connections.',
                'flag' => 1,
                'categ' => 8,
                'order' => 2,
              ),
              53 => 
              array (
                'id' => 465,
                'link_id' => 65,
                'lang_id' => 6,
                'type' => 'item',
                'question' => 'You may try this',
                'answer' => 'Post in the <a class="faqlink" href="forums.php">Forums</a>, by all means. You\'ll find they are usually a friendly and helpful place, provided you follow a few basic guidelines: <ul>
            <li>Make sure your problem is not really in this FAQ. There\'s no point in posting just to be sent back here. </li>
            <li>Before posting read the sticky topics (the ones at the top). Many times new information that still hasn\'t been incorporated in the FAQ can be found there.</li>
            <li>Help us in helping you. Do not just say "it doesn\'t work!". Provide details so that we don\'t have to guess or waste time asking. What client do you use? What\'s your OS? What\'s your network setup? What\'s the exact error message you get, if any? What are the torrents you are having problems with? The more you tell the easiest it will be for us, and the more probable your post will get a reply.</li>
            <li>And needless to say: be polite. Demanding help rarely works, asking for it usually does the trick.</li></ul>',
                'flag' => 1,
                'categ' => 9,
                'order' => 1,
              ),
              54 => 
              array (
                'id' => 466,
                'link_id' => 66,
                'lang_id' => 6,
                'type' => 'item',
                'question' => 'Why do I get a "Your slot limit is reached! You may at most download xx torrents at the same time" error?',
                'answer' => 'This is part of the "Slot System". The slot system is being used to limit the concurrent downloads for users that have low ratio and downloaded amount above 10 GB<br /><br />
            Rules: <br />
            <table cellspacing="3" cellpadding="0">
            <tr>
            <td class="embedded" width="100">Ratio below</td>
            <td class="embedded" width="40">0.5</td>
            <td class="embedded" width="10">&nbsp;</td>
            <td class="embedded" width="100">available slots</td>
            <td class="embedded" width="40">1</td>
            </tr>
            <tr>
            <td class="embedded" width="100">Ratio below</td>
            <td class="embedded" width="40">0.65</td>
            <td class="embedded" width="10">&nbsp;</td>
            <td class="embedded" width="100">available slots</td>
            <td class="embedded" width="100">2</td>
            </tr>
            <tr>
            <td class="embedded" width="100">Ratio below</td>
            <td class="embedded" width="40">0.8</td>
            <td class="embedded" width="10">&nbsp;</td>
            <td class="embedded" width="100">available slots</td>
            <td class="embedded" width="100">3</td>
            </tr>
            <tr>
            <td class="embedded" width="100">Ratio below</td>
            <td class="embedded" width="40">0.95</td>
            <td class="embedded" width="10">&nbsp;</td>
            <td class="embedded" width="100">available slots</td>
            <td class="embedded" width="100">4</td>
            </tr>
            <tr>
            <td class="embedded" width="100">Ratio above</td>
            <td class="embedded" width="40">0.95</td>
            <td class="embedded" width="10">&nbsp;</td>
            <td class="embedded" width="100">available slots</td>
            <td class="embedded" width="100">unlimited</td>
            </tr>
            </table>
            <br />
            In all cases the seeding slots are unlimited. However if you have already filled all your available download slots and try to start seeding you will receive the same error. In this case you must free at least one download slot in order to start all your seeds and then start the download. If all your download slots are filled the system will deny any connection before validating if you want to download or seed. So first start your seeds and after that your downloads. <br />
            <br /><br />
            Any time, you can check your available slots in the member bar on top of the page.',
                'flag' => 0,
                'categ' => 5,
                'order' => 12,
              ),
              55 => 
              array (
                'id' => 467,
                'link_id' => 67,
                'lang_id' => 6,
                'type' => 'item',
                'question' => 'What is the passkey System? How does it work?',
                'answer' => 'The passkey system is implemented to verify if you are registered with the tracker. Every user has a personal passkey, a random key generated by the system. When a user tries to download a torrent, his personal passkey is imprinted in the tracker URL of the torrent, allowing to the tracker to identify any source connected on it. In this way, you can seed a torrent for example, at home and at your office simultaneously without any problem with the 2 different IPs.
            ',
                'flag' => 1,
                'categ' => 5,
                'order' => 13,
              ),
              56 => 
              array (
                'id' => 468,
                'link_id' => 68,
                'lang_id' => 6,
                'type' => 'item',
                'question' => 'Why do I get an "Unknown passkey" error? ',
                'answer' => 'You will get this error, firstly if you are not registered on our tracker, or if you haven\'t downloaded the torrent to use from our webpage, when you were logged in. In this case, just register or log in and re-download the torrent.<br />
            There is a chance to get this error also, at the first time you download anything as a new user, or at the first download after you reset your passkey. The reason is simply that the tracker reviews the changes in the passkeys every few minutes and not instantly. For that reason just leave the torrent running for a few minutes, and you will get eventually an OK message from the tracker.',
                'flag' => 1,
                'categ' => 5,
                'order' => 14,
              ),
              57 => 
              array (
                'id' => 469,
                'link_id' => 69,
                'lang_id' => 6,
                'type' => 'item',
                'question' => 'When do I need to reset my passkey?',
                'answer' => '<ul><li>If your passkey has been leaked and other user(s) uses it to download torrents using your account. In this case, you will see torrents stated in your account that you are not leeching or seeding . </li>
            <li>When your clients hangs up or your connection is terminated without pressing the stop button of your client. In this case, in your account you will see that you are still leeching/seeding the torrents even that your client has been closed. Normally these "ghost peers" will be cleaned automatically within 30 minutes, but if you want to resume your downloads and the tracker denied that due to the fact that you "already are downloading the same torrent" then you should reset your passkey and re-download the torrent, then resume it.  </li></ul>',
                'flag' => 1,
                'categ' => 5,
                'order' => 15,
              ),
              58 => 
              array (
                'id' => 470,
                'link_id' => 70,
                'lang_id' => 6,
                'type' => 'item',
                'question' => 'What is DHT? Why must I turn it off and how?',
                'answer' => 'DHT must be disabled in your client. DHT can cause your stats to be recorded incorrectly and could be seen as cheating. Anyone using this will be banned for cheating the system.
            <br />
            Fortunately, this tracker would parse uploaded .torrent files and automatically disable DHT. That\'s why you must re-downloaded the torrent before starting seeding.',
                'flag' => 1,
                'categ' => 5,
                'order' => 16,
              ),
              59 => 
              array (
                'id' => 471,
                'link_id' => 71,
                'lang_id' => 6,
                'type' => 'categ',
                'question' => 'How can I help translate the site language into my native language?',
                'answer' => '',
                'flag' => 1,
                'categ' => 1,
                'order' => 8,
              ),
              60 => 
              array (
                'id' => 472,
                'link_id' => 72,
                'lang_id' => 6,
                'type' => 'item',
                'question' => 'What skills do I need to do the translation?',
                'answer' => 'Translate the site into another language is quite easy. You needn\'t be skilled in PHP or dynamic website design. In fact, all you need is proficient understanding of English (the default site language) and the language you plan to translate into. Maybe some basic knowledge in HTML would help.<br /><br />
            Moreover, we give a detailed tutorial on how to do the translation <a href="#73" class="faqlink"><b>HERE</b></a>. And our coders would be more than pleased to answer the questions you may encounter.<br /><br />
            Translate the whole site into another language would take estimated 10 hours. And extra time is needed to maintain the translation when site code is updated.<br /><br />
            So, if you think you could help, feel free to <a class="faqlink" href="contactstaff.php"><b>CONTACT US</b></a>. Needless to say, you would be rewarded.',
                'flag' => 1,
                'categ' => 71,
                'order' => 1,
              ),
              61 => 
              array (
                'id' => 473,
                'link_id' => 73,
                'lang_id' => 6,
                'type' => 'item',
                'question' => 'The translation tutorial',
                'answer' => '<ul>
            <li>How does multi-language work?<br />
            Currently we use language files to store all the static words that a user can see on web pages. <br />
            Every php code file has a corresponding language file for a certain language. And we named the language file \'lang_\' plus the filename of the php code file. i.e. the language file of the php code file \'details.php\' would be \'lang_details.php\'. <br />
            We has some mechanism in php codes to read the exact language files of user\'s preferred language, and you shouldn\'t worry about that.<br /></li>
            <li>What\'s in language files?<br />
            In a language file is an array of strings. These strings contain all the static words that a user can see on web pages. When we need to say some words on a php code file, we call for a string in the array. And the output of the php code file, that is what users see on the web pages, would show the value of the string.<br />
            Sounds dizzying? Well, you need not care about all these. All you gotta do is translate the values of the strings in the language files into another language. <b>Let\'s see an example</b>:<br /><br />
            The following is the content of the English language file \'lang_users.php\', which works for the php code file \'users.php\'. <br /><br />
            <img src="pic/langfileeng.png" alt="langfileeng" /><br />
            If you wanna translate it into Simplified Chinese, all you need is edit the file into this:<br />
            <img src="pic/langfilechs.png" alt="langfilechs" /><br />
            See, in every line, the left part, that is before <i>=&gt;</i>, is the name of a string, which you shouldn\'t touch. All you need to is translate the right part, after <i>=&gt;</i>, which is the value of the string, into another language.<br />
            Sometimes you need to look at the corresponding web pages to get the context meaning of some words.<br /></li>
            <li>Sounds easy? See what do you need to do.<br />
            If you feel like to help us, <a class="faqlink" href="aboutnexus.php#contact"><b>CONTACT US</b></a> and we will send you a pack of the English language files (or any other available language if you prefer). Received it, you can start translating the value of strings (which is in English), into another language. It should take you several hours to do the whole work. After this, send back the translated language files to us.<br />
            If no bugs or hacking codes are found in testing, we would put the new language into work.<br />
            Sometimes the language files need to be updated, typically adding new strings, when site codes are updated. If you feel like it, you can help maintain the language files.<br /></li>
            <li><font color="red"><b>IMPORTANT</b></font><br />
            The text of language files must be encoded in UTF-8. When saving files, be sure to set the character encoding to UTF-8. Otherwise mojibake may happen.</li></ul>',
                'flag' => 1,
                'categ' => 71,
                'order' => 2,
              ),
              62 => 
              array (
                'id' => 474,
                'link_id' => 74,
                'lang_id' => 6,
                'type' => 'item',
                'question' => 'Why does my client notify me of low disk space even if there is plenty left?',
                'answer' => 'Most possible reason is that the file system of your disk partitions is FAT32, which has a maximum file size limit of 4 GB. If your operation system is Windows, consider converting file system to NTFS. Check <a class="faqlink" href="http://technet.microsoft.com/en-us/library/bb456984.aspx">here</a> for details.
            ',
                'flag' => 1,
                'categ' => 5,
                'order' => 17,
              ),
        ));
        
        
    }
}

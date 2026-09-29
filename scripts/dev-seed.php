<?php

declare(strict_types=1);

/**
 * Dev-stack fixture seeder: 50 lpfanXX users + 30 Linkin Park torrents with
 * descriptions, screenshots and MediaInfo blocks. Idempotent — skips rows
 * that already exist. Run inside the php container:
 *
 *   docker compose exec -T php php scripts/dev-seed.php
 */
$root = dirname(__DIR__);

require $root.'/vendor/autoload.php';
$app = require $root.'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

if (! app()->environment('local', 'development', 'testing')) {
    fwrite(STDERR, 'dev-seed is for local dev stacks only (APP_ENV='.app()->environment().")\n");
    exit(1);
}

use App\Enums\TorrentPromotion;
use App\Enums\TorrentType;
use App\Enums\TorrentVisible;
use App\Models\Torrent;
use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Rhilip\Bencode\Bencode;

// ---- users ----
$owners = User::where('id', 1)->get();
for ($i = 1; $i <= 50; $i++) {
    $name = sprintf('lpfan%02d', $i);
    $u = User::where('username', $name)->first() ?? User::factory()->create([
        'username' => $name,
        'email' => $name.'@lpbits.local',
        'stylesheet' => 5,
    ]);
    $owners->push($u);
}
echo 'users ready: '.User::count()."\n";

// ---- torrents ----
$releases = [
    ['Linkin.Park.-.Hybrid.Theory.20th.Anniversary.Edition.2020.FLAC', 408, 'lossless 24-bit audio'],
    ['Linkin.Park.-.Meteora.20th.Anniversary.Edition.2023.FLAC', 408, 'lossless 24-bit audio'],
    ['Linkin.Park.-.Minutes.to.Midnight.2007.FLAC', 408, 'lossless audio'],
    ['Linkin.Park.-.A.Thousand.Suns.2010.FLAC', 408, 'lossless audio'],
    ['Linkin.Park.-.Living.Things.2012.FLAC', 408, 'lossless audio'],
    ['Linkin.Park.-.The.Hunting.Party.2014.FLAC', 408, 'lossless audio'],
    ['Linkin.Park.-.One.More.Light.2017.FLAC', 408, 'lossless audio'],
    ['Linkin.Park.-.From.Zero.2024.FLAC', 408, 'lossless audio'],
    ['Linkin.Park.-.Reanimation.2002.FLAC', 408, 'remix album, lossless'],
    ['Linkin.Park.-.Collision.Course.with.Jay-Z.2004.FLAC', 408, 'mashup EP, lossless'],
    ['Linkin.Park.-.Papercuts.(Singles.Collection).2024.FLAC', 408, 'singles collection, lossless'],
    ['Linkin.Park.-.Underground.v1-v16.COMPLETE.FLAC', 408, 'LPU fanclub rarities'],
    ['Linkin.Park.-.Live.in.Texas.2003.1080p.BluRay.x264.DTS', 406, 'concert film'],
    ['Linkin.Park.-.Road.to.Revolution.Live.at.Milton.Keynes.2008.1080p.BluRay.x264', 406, 'concert film'],
    ['Linkin.Park.-.Live.from.Madison.Square.Garden.2011.1080p.BluRay.x264', 406, 'concert film'],
    ['Linkin.Park.-.Rock.am.Ring.2004.HDTV.720p.x264', 406, 'festival set'],
    ['Linkin.Park.-.Rock.Werchter.2017.One.More.Light.Tribute.1080p.WEB-DL', 406, 'tribute concert'],
    ['Linkin.Park.-.iTunes.Festival.London.2011.720p.HDTV.x264', 406, 'festival set'],
    ['Linkin.Park.-.Live.from.the.O2.London.2024.From.Zero.World.Tour.1080p.WEB-DL', 406, 'world tour stop'],
    ['Linkin.Park.-.Music.Video.Collection.2000-2024.1080p.Ai-Upscale', 406, 'complete videography, AI upscaled'],
    ['Linkin.Park.-.The.Meeting.of.a.Thousand.Suns.2010.DVDR', 401, 'making-of documentary'],
    ['Linkin.Park.-.Frat.Party.at.the.Pancake.Festival.2001.DVDR', 401, 'band documentary'],
    ['Linkin.Park.-.Breaking.the.Habit.Documentary.2004.1080p.WEB-DL', 404, 'behind the scenes'],
    ['Linkin.Park.-.A.Line.in.the.Sand.Documentary.2016.1080p.WEB-DL', 404, 'tour documentary'],
    ['Linkin.Park.-.Recharged.2013.FLAC', 408, 'remix album with Steve Aoki'],
    ['Linkin.Park.-.MTV.Unplugged.2001.FLAC', 408, 'acoustic session'],
    ['Linkin.Park.-.HT20.Demos.and.Rarities.2020.FLAC', 408, 'previously unreleased demos'],
    ['Linkin.Park.-.Meteora.Deluxe.Demos.2023.FLAC', 408, 'album demos'],
    ['Linkin.Park.-.Xero.Demo.Tape.1997.FLAC', 408, 'pre-Linkin Park demos'],
    ['Linkin.Park.-.Grey.Daze.Amends.2020.FLAC', 408, 'Chester Bennington side project'],
];

$screens1 = '[img]/pic/test-poster.jpg[/img] [img]/pic/test-poster.jpg[/img] [img]/pic/test-poster.jpg[/img]';
$torDir = $root.'/torrents';
$baseUrl = 'http://localhost/announce.php';

if (! is_dir($torDir)) {
    mkdir($torDir, 0775, true);
}

function mediaInfo(string $title, int $sizeBytes): string
{
    $gb = round($sizeBytes / 1073741824, 2);

    return <<<MI
General
Complete name                            : {$title}
Format                                   : Matroska / FLAC
File size                                : {$gb} GiB
Duration                                 : 1 h 45 min
Overall bit rate                         : 12.4 Mb/s

Video
ID                                       : 1
Format                                   : AVC
Codec ID                                 : V_MPEG4/ISO/AVC
Width                                    : 1 920 pixels
Height                                   : 1 080 pixels
Frame rate                               : 25.000 FPS

Audio
ID                                       : 2
Format                                   : DTS / FLAC
Channels                                 : 6 channels / 2 channels
Sampling rate                            : 48.0 kHz
MI;
}

$created = 0;
foreach ($releases as [$name, $cat, $tagline]) {
    if (Torrent::where('name', $name)->exists()) {
        continue;
    }
    $isAudio = $cat === 408;
    $size = $isAudio ? random_int(300, 900) * 1024 * 1024 : random_int(2500, 9000) * 1024 * 1024;
    $pieceLen = $size > 2147483648 ? 4194304 : 1048576;
    $pieces = '';
    for ($off = 0; $off < $size; $off += $pieceLen) {
        $pieces .= sha1((string) $off.$name, true);
    }
    $info = [
        'name' => $name,
        'piece length' => $pieceLen,
        'length' => $size,
        'pieces' => $pieces,
    ];
    $dict = [
        'announce' => $baseUrl,
        'created by' => 'LP-Bits seeder',
        'creation date' => time(),
        'info' => $info,
    ];
    $infoHash = sha1(Bencode::encode($info), true);

    $descr = "[center][size=4]{$name}[/size]\n[size=2]{$tagline}[/size][/center]\n\n"
        ."A fan-archived release for the LP-Bits community. {$tagline}. "
        ."Seeded at full speed; please keep seeding after finish — see H&R rules.\n\n"
        ."[b]Screenshots:[/b]\n{$screens1}\n\n"
        ."[b]MediaInfo:[/b]\n[quote][font=Courier New]".mediaInfo($name, $size)."[/font][/quote]\n\n"
        .'[b]Notes:[/b] sourced from the fan archive; report issues in the comments.';

    $owner = $owners->random();
    $t = Torrent::factory()->create([
        'name' => $name,
        'info_hash' => $infoHash,
        'filename' => sprintf('%s.torrent', Str::uuid()),
        'save_as' => Str::slug($name),
        'category' => $cat,
        'source' => $isAudio ? 6 : 1,
        'medium' => $isAudio ? 0 : 1,
        'codec' => $isAudio ? 0 : 1,
        'standard' => $isAudio ? 0 : 1,
        'audiocodec' => $isAudio ? 1 : 3,
        'size' => $size,
        'type' => TorrentType::SINGLE->value,
        'numfiles' => 1,
        'owner' => $owner->id,
        'visible' => TorrentVisible::YES->value,
        'sp_state' => TorrentPromotion::NORMAL->value,
        'seeders' => random_int(2, 40),
        'leechers' => random_int(0, 12),
        'times_completed' => random_int(5, 300),
        'views' => random_int(50, 2000),
        'hits' => random_int(20, 900),
    ]);
    DB::table('torrent_extras')->insert([
        'torrent_id' => $t->id,
        'descr' => $descr,
        'media_info' => mediaInfo($name, $size),
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    file_put_contents("{$torDir}/{$t->id}.torrent", Bencode::encode($dict));
    $created++;
}
echo 'torrents created: '.$created.', total: '.Torrent::count()."\n";

// a couple of freeleech + one H&R-flagged for coverage
$fl = Torrent::orderByDesc('id')->limit(2)->get();
foreach ($fl as $t) {
    $t->update(['sp_state' => TorrentPromotion::FREE->value]);
}
$hrT = Torrent::orderByDesc('id')->skip(2)->first();
if ($hrT) {
    $hrT->update(['hr' => 1]);
}
echo "done\n";

<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Support\Cache\LegacyRedisCache;
use App\Support\CurrentUser;
use App\Support\Globals;
use App\Support\Http;
use App\Support\LegacyResponse;
use App\Support\Locale;
use App\Support\Path;
use App\Support\SupportContext;
use App\Support\Url;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class BitbucketUploadController extends Controller
{
    public function create(Request $request): View|RedirectResponse
    {
        if (app(LegacyRedisCache::class) === null) {
            return redirect('/bitbucket-upload.php?'.$request->getQueryString());
        }

        $user = Auth::guard('nexus-web')->user();
        if (! $user instanceof User) {
            return redirect('/login.php?returnto='.urlencode($request->fullUrl()));
        }

        $currentUser = app(CurrentUser::class)->get() ?? $user->toLegacyArray();
        app(CurrentUser::class)->set($currentUser);

        $lang = $this->loadLang();

        if ($currentUser['parked'] === 'yes') {
            LegacyResponse::abort($lang['std_sorry'] ?? '', $lang['std_unauthorized_to_upload'] ?? '', false);
        }

        if (app(Globals::class)->get('enablebitbucket_main', 'no') !== 'yes') {
            LegacyResponse::permissionDenied();
        }

        return view('bitbucket.upload', [
            'pageTitle' => $lang['head_avatar_upload'] ?? '',
            'lang' => $lang,
            'maxFileSize' => 256 * 1024,
            'scaleHeight' => 200,
            'scaleWidth' => 150,
        ]);
    }

    public function store(Request $request): View|RedirectResponse
    {
        if (app(LegacyRedisCache::class) === null) {
            return redirect('/bitbucket-upload.php', 307);
        }

        $user = Auth::guard('nexus-web')->user();
        if (! $user instanceof User) {
            return redirect('/login.php?returnto='.urlencode($request->fullUrl()));
        }

        $currentUser = app(CurrentUser::class)->get() ?? $user->toLegacyArray();
        app(CurrentUser::class)->set($currentUser);

        $lang = $this->loadLang();

        if ($currentUser['parked'] === 'yes') {
            LegacyResponse::abort($lang['std_sorry'] ?? '', $lang['std_unauthorized_to_upload'] ?? '', false);
        }

        if (app(Globals::class)->get('enablebitbucket_main', 'no') !== 'yes') {
            LegacyResponse::permissionDenied();
        }

        /** @var UploadedFile|null $file */
        $file = $request->file('file');
        if (! $file instanceof UploadedFile || ! $file->isValid()) {
            LegacyResponse::abort($lang['std_upload_failed'] ?? '', $lang['std_nothing_received'] ?? '', false);
        }
        assert($file instanceof UploadedFile);

        if ($file->getSize() > 256 * 1024) {
            LegacyResponse::abort($lang['std_upload_failed'] ?? '', $lang['std_file_too_large'] ?? '', false);
        }

        $filename = $file->getClientOriginalName();
        $filename = (string) preg_replace('/[\x00-\x1F\x7F]/', '', $filename);
        $pp = pathinfo($filename);
        if ($pp['basename'] !== $filename) {
            LegacyResponse::abort($lang['std_upload_failed'] ?? '', $lang['std_bad_file_name'] ?? '', false);
        }

        $bitbucket = (string) app(Globals::class)->get('bitbucket', 'bitbucket');
        $tgtfile = Path::resolve("{$bitbucket}/{$filename}", \ROOT_PATH);
        if (file_exists($tgtfile)) {
            LegacyResponse::abort(
                $lang['std_upload_failed'] ?? '',
                ($lang['std_file_already_exists'] ?? '').htmlspecialchars($filename).($lang['std_already_exists'] ?? ''),
                false,
            );
        }

        $size = getimagesize($file->getPathname());
        if ($size === false) {
            LegacyResponse::abort($lang['std_error'] ?? '', $lang['std_invalid_image_format'] ?? '', false);
        }
        assert($size !== false);

        $height = (int) $size[1];
        $width = (int) $size[0];
        $it = (int) $size[2];
        $imgtypes = [null, 'gif', 'jpg', 'png'];
        $typeName = $imgtypes[$it] ?? null;

        if ($typeName === null || $typeName !== strtolower($pp['extension'] ?? '')) {
            LegacyResponse::abort($lang['std_error'] ?? '', $lang['std_invalid_image_format'] ?? '', false);
        }

        $scaleh = 200;
        $scalew = 150;
        $hscale = $height / $scaleh;
        $wscale = $width / $scalew;
        $scale = ($hscale < 1 && $wscale < 1) ? 1 : (($hscale > $wscale) ? $hscale : $wscale);
        $newwidth = max(1, (int) floor($width / $scale));
        $newheight = max(1, (int) floor($height / $scale));

        $orig = match ($it) {
            1 => @imagecreatefromgif($file->getPathname()),
            2 => @imagecreatefromjpeg($file->getPathname()),
            default => @imagecreatefrompng($file->getPathname()),
        };

        if (! $orig) {
            LegacyResponse::abort(
                $lang['std_image_processing_failed'] ?? '',
                ($lang['std_sorry_the_uploaded'] ?? '').($typeName ?? '').($lang['std_failed_processing'] ?? ''),
                false,
            );
        }
        assert($orig !== false);

        $thumb = imagecreatetruecolor($newwidth, $newheight);
        if ($thumb === false) {
            LegacyResponse::abort(
                $lang['std_image_processing_failed'] ?? '',
                ($lang['std_sorry_the_uploaded'] ?? '').($typeName ?? '').($lang['std_failed_processing'] ?? ''),
                false,
            );
        }
        assert($thumb !== false);
        imagecopyresampled($thumb, $orig, 0, 0, 0, 0, $newwidth, $newheight, $width, $height);

        match ($it) {
            1 => imagegif($thumb, $tgtfile),
            2 => imagejpeg($thumb, $tgtfile),
            default => imagepng($thumb, $tgtfile),
        };

        $baseUrl = (string) app(Globals::class)->get('BASEURL', '');
        $url = str_replace(' ', '%20', htmlspecialchars(Http::protocolPrefix(Url::isSecure())."{$baseUrl}/bitbucket/{$filename}"));
        $public = $request->input('public') === 'yes' ? '1' : '0';

        DB::table('bitbucket')->insert([
            'owner' => $currentUser['id'],
            'name' => $filename,
            'added' => date('Y-m-d H:i:s'),
            'public' => $public,
        ]);

        User::query()->where('id', $currentUser['id'])->update(['avatar' => $url]);

        return view('bitbucket.result', [
            'url' => $url,
            'filename' => $filename,
            'width' => $width,
            'height' => $height,
            'newwidth' => $newwidth,
            'newheight' => $newheight,
            'lang' => $lang,
        ]);
    }

    /** @return array<string, string> */
    private function loadLang(): array
    {
        if (empty(app(Globals::class)->get('lang_bitbucketupload'))) {
            SupportContext::setServerValue('SCRIPT_NAME', '/bitbucket-upload.php');
            require base_path(Locale::scriptFilePath((string) '', (bool) false, (string) ''));
            app(Globals::class)->set('lang_bitbucketupload', $lang_bitbucketupload ?? []);
        }

        return (array) app(Globals::class)->get('lang_bitbucketupload', []);
    }
}

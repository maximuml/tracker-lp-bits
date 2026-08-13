@php
$__server_PATH_INFO = \App\Support\SupportContext::getServerValue('PATH_INFO');
if (!preg_match(':^/(\d{1,10})/([\w]{32})/(.+)$:', $__server_PATH_INFO, $matches))
	\App\Support\LegacyResponse::notFound();

$id = intval($matches[1] ?? 0);
$md5 = $matches[2];
$email = urldecode($matches[3]);
//print($email);
//die();

if (!$id)
	\App\Support\LegacyResponse::notFound();

$user = \App\Models\User::query()->where('id', $id)->first(['editsecret']);
if (!$user)
	\App\Support\LegacyResponse::notFound();
$row = $user->toArray();

$sec = \App\Support\Strings::padHash($row["editsecret"]);
if (preg_match('/^ *$/s', $sec))
	\App\Support\LegacyResponse::notFound();
if ($md5 != md5($sec . $email . $sec))
	\App\Support\LegacyResponse::notFound();

$affected = \App\Models\User::query()->where('id', $id)->where('editsecret', $row['editsecret'])->update(['editsecret' => '', 'email' => $email]);

if (!$affected)
	\App\Support\LegacyResponse::notFound();

header("Location: " . \App\Support\Http::protocolPrefix(\App\Support\Url::isSecure()) . "$BASEURL/usercp.php?action=security&type=saved");
@endphp

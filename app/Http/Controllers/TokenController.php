<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Exceptions\NexusException;
use App\Http\Requests\TokenDeleteRequest;
use App\Http\Requests\TokenRequest;
use App\Models\User;
use App\Repositories\TokenRepository;
use App\Support\Locale;
use Illuminate\Support\Facades\Auth;

class TokenController extends Controller
{
    /**
     * @return array<string, mixed>
     */
    public function addToken(TokenRequest $request): array
    {
        try {
            $user = Auth::user();
            if (! $user instanceof User) {
                return $this->fail(false, 'Unauthenticated');
            }
            $count = $user->tokens()->count();
            if ($count >= 5) {
                throw new NexusException(Locale::trans('token.maximum_allow_number_reached', [], null));
            }
            $allowed = app(TokenRepository::class)->listUserTokenPermissionAllowed();
            foreach ($request->permissions as $permission) {
                if (! isset($allowed[$permission])) {
                    throw new NexusException(Locale::trans('token.permission_not_allowed', ['permission_text' => Locale::trans("route-permission.{$permission}.text", [], null)], null));
                }
            }
            $newAccessToken = $user->createToken($request->name, $request->permissions);
            $tokenText = $newAccessToken->plainTextToken;
            $msg = Locale::trans('token.create_success_tip', ['token' => $tokenText], null);

            return $this->success(['token' => $tokenText], $msg);
        } catch (\Exception $exception) {
            return $this->fail(false, $exception->getMessage());
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function delToken(TokenDeleteRequest $request): array
    {
        try {
            $user = Auth::user();
            if (! $user instanceof User) {
                return $this->fail(false, 'Unauthenticated');
            }
            $user->tokens()->where('id', $request->id)->delete();

            return $this->success(true);
        } catch (\Exception $exception) {
            return $this->fail(false, $exception->getMessage());
        }
    }
}

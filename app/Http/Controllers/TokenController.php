<?php

namespace App\Http\Controllers;

use App\Exceptions\NexusException;
use App\Repositories\TokenRepository;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class TokenController extends Controller
{
    /**
     * @param  \Illuminate\Http\Request  $request
     * @return  array<string, mixed>
     */
    public function addToken(Request $request)
    {
        try {
            $request->validate([
                'name' => 'required|string',
                'permissions' => 'required|array|min:1',
            ]);
            $user = Auth::user();
            $count = $user->tokens()->count();
            if ($count >= 5) {
                throw new NexusException(\App\Support\Locale::trans("token.maximum_allow_number_reached", [], null));
            }
            $allowed = TokenRepository::listUserTokenPermissionAllowed();
            foreach ($request->permissions as $permission) {
                if (!isset($allowed[$permission])) {
                    throw new NexusException(\App\Support\Locale::trans("token.permission_not_allowed", ['permission_text' => \App\Support\Locale::trans("route-permission.{$permission}.text", [], null)], null));
                }
            }
            $newAccessToken = $user->createToken($request->name, $request->permissions);
            $tokenText = $newAccessToken->plainTextToken;
            $msg = \App\Support\Locale::trans("token.create_success_tip", ['token' => $tokenText], null);
            return $this->success(['token' => $tokenText], $msg);
        } catch (\Exception $exception) {
            return $this->fail(false, $exception->getMessage());
        }
    }

    /**
     * @param  \Illuminate\Http\Request  $request
     * @return  array<string, mixed>
     */
    public function delToken(Request $request)
    {
        try {
            $request->validate([
                'id' => 'required|integer',
            ]);
            $user = Auth::user();
            $user->tokens()->where("id", $request->id)->delete();
            return $this->success(true);
        } catch (\Exception $exception) {
            return $this->fail(false, $exception->getMessage());
        }
    }


}

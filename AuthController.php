<?php
namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller; use App\Models\User; use Illuminate\Http\JsonResponse; use Illuminate\Http\Request; use Illuminate\Support\Facades\Hash; use Illuminate\Support\Str;
class AuthController extends Controller {
 public function login(Request $request): JsonResponse {
  $credentials=$request->validate(['login'=>['required','string'],'password'=>['required','string']]);
  $user=User::where('email',$credentials['login'])->orWhere('identifier',$credentials['login'])->first();
  if(!$user||!Hash::check($credentials['password'],$user->password))return response()->json(['message'=>'NIM/NIP atau kata sandi tidak sesuai.'],422);
  if(!$user->is_active)return response()->json(['message'=>'Akun Anda sedang dinonaktifkan. Hubungi Administrator Akademik.'],403);
  $plainToken=Str::random(64); $user->forceFill(['api_token'=>hash('sha256',$plainToken)])->save();
  return response()->json(['token'=>$plainToken,'user'=>$this->payload($user)]);
 }
 public function me(Request $request): JsonResponse{return response()->json(['user'=>$this->payload($request->user())]);}
 public function logout(Request $request): JsonResponse{$request->user()->forceFill(['api_token'=>null])->save();return response()->json(['message'=>'Berhasil keluar.']);}
 private function payload(User $user): array{return $user->only(['id','name','email','identifier','role','program_studi','is_active','must_change_password']);}
}

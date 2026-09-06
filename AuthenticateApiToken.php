<?php
namespace App\Http\Middleware;
use App\Models\User; use Closure; use Illuminate\Http\Request; use Symfony\Component\HttpFoundation\Response;
class AuthenticateApiToken {
 public function handle(Request $request, Closure $next): Response {
  $token=$request->bearerToken(); $user=$token?User::where('api_token',hash('sha256',$token))->where('is_active',true)->first():null;
  if(!$user)return response()->json(['message'=>'Sesi tidak valid atau telah berakhir.'],401);
  $request->setUserResolver(fn()=>$user); return $next($request);
 }
}

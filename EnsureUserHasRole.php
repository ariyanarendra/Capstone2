<?php
namespace App\Http\Middleware;
use Closure; use Illuminate\Http\Request; use Symfony\Component\HttpFoundation\Response;
class EnsureUserHasRole {
 public function handle(Request $request, Closure $next, string ...$roles): Response { abort_unless(in_array($request->user()?->role,$roles,true),403,'Anda tidak memiliki akses ke layanan ini.'); return $next($request); }
}

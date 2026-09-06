<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePasswordHasBeenChanged
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()?->must_change_password) {
            return response()->json([
                'message' => 'Ganti kata sandi sementara sebelum membuka layanan GateKampus.',
                'code' => 'password_change_required',
            ], 423);
        }

        return $next($request);
    }
}

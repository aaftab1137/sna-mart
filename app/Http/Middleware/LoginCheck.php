<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Auth;
use Illuminate\Http\JsonResponse;

class LoginCheck
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure(\Illuminate\Http\Request): (\Illuminate\Http\Response|\Illuminate\Http\RedirectResponse)  $next
     * @return \Illuminate\Http\Response|\Illuminate\Http\RedirectResponse
     */

    public function handle(Request $request, Closure $next)
    {
        $token = $request->header('Authorization');
         if (!$token) {
            return response()->json([
                'status' => 401,
                'success' => false,
                'message' => 'Unauthenticated!! Please provide a valid token.',
            ], 401);
        }

        try {
            $user = Auth::guard('api')->authenticate($token);

            if (!$user) {
                return response()->json([
                    'status' => 401,
                    'success' => false,
                    'message' => 'Unauthenticated!! Invalid token provided.',
                ], 401);
            }
        } catch (\Exception $e) {

            return response()->json([
                'status' => 401,
                'success' => false,
                'message' => 'Unauthenticated!! Invalid token provided.',
            ], 401);
        }

         return $next($request);
    }
}

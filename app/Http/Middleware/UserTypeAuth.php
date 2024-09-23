<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class UserTypeAuth
{
    /**
     * 
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure(\Illuminate\Http\Request): (\Illuminate\Http\Response|\Illuminate\Http\RedirectResponse)  $next
     * @return \Illuminate\Http\Response|\Illuminate\Http\RedirectResponse
     */
    
     public function handle(Request $request, Closure $next)
     {
         if (Auth::check()) {
             $user = Auth::user();
 
             if (in_array($user->usertype, ['Admin', 'Supervisor', 'Teamleader', 'Controller', 'Dean'])) {
                 return $next($request);
             } else {
                 return response()->json(['message' => 'Unauthorized user type'], 403);
             }
         }
 
         return response()->json(['message' => 'Unauthorized'], 401);
     }
 }



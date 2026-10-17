<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class HasEstablishment
{
    public function handle(Request $request, Closure $next): Response
    {
        $establishments = $request->user()->establishment()->limit(2)->get();
        abort_if($establishments->count() > 1, 409, __('auth.establishment_conflict'));
        if ($establishments->isEmpty()) {
            if ($request->expectsJson()) {
                return response()->json(['message' => __('auth.establishment_required')], 403);
            }

            return redirect()->route('manager.establishment.setup');
        }
        $request->user()->setRelation('establishment', $establishments->first());

        return $next($request);
    }
}

<?php

namespace App\Http\Middleware;

use App\Support\Audit\AuditTrail;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Context;
use Symfony\Component\HttpFoundation\Response;

/**
 * W155 — DDD Reporting: Audit Log "dari IP mana". Context tersembunyi ikut terbawa ke job
 * yang di-dispatch dari request ini, tetapi tidak ikut tercetak di log aplikasi.
 */
final class CaptureAuditIp
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        Context::addHidden(AuditTrail::IP_CONTEXT, $request->ip());

        return $next($request);
    }
}

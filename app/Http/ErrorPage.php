<?php

namespace App\Http;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Spatie\Permission\Exceptions\UnauthorizedException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

/**
 * W163 — error di shell v2: 403/404 selalu, 500/503 hanya saat debug mati,
 * 419 kembali dengan toast. Permintaan JSON tidak disentuh.
 */
final class ErrorPage
{
    private const ALWAYS = [403, 404];

    private const WHEN_NOT_DEBUGGING = [500, 503];

    public static function respond(Response $response, Throwable $e, Request $request): Response
    {
        if ($request->expectsJson()) {
            return $response;
        }

        $status = $response->getStatusCode();

        if ($status === 419) {
            return self::sessionExpired();
        }

        return self::rendersPage($status) ? self::render($response, $e, $request) : $response;
    }

    private static function sessionExpired(): RedirectResponse
    {
        return redirect()->back(303)->with('error', 'Sesi kedaluwarsa. Silakan ulangi.');
    }

    private static function rendersPage(int $status): bool
    {
        if (in_array($status, self::ALWAYS, true)) {
            return true;
        }

        return in_array($status, self::WHEN_NOT_DEBUGGING, true) && !config('app.debug');
    }

    private static function render(Response $response, Throwable $e, Request $request): Response
    {
        $status = $response->getStatusCode();

        try {
            return Inertia::render('Error/ErrorPage', [
                'status' => $status,
                'message' => $status === 403 ? self::humanMessage($e) : null,
            ])->toResponse($request)->setStatusCode($status);
        } catch (Throwable) {
            // Galat yang sama (mis. basis data mati) bisa terulang saat props bersama
            // dihitung; halaman bawaan Laravel lebih baik daripada layar kosong.
            return $response;
        }
    }

    /**
     * Pesan abort/AuthorizationException di repo ini ditulis untuk manusia. Bawaan
     * framework (Policy tanpa pesan, middleware Spatie) berbahasa Inggris, jadi
     * diganti copy halaman. 404 tidak pernah lewat sini: pesannya nama model.
     */
    private static function humanMessage(Throwable $e): ?string
    {
        if (!$e instanceof HttpExceptionInterface || $e instanceof UnauthorizedException) {
            return null;
        }

        $message = $e->getMessage();

        if ($message === '' || $message === (new AuthorizationException)->getMessage()) {
            return null;
        }

        return $message;
    }
}

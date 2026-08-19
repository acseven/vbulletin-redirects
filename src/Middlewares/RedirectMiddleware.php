<?php

namespace Acseven\VBulletinRedirects\Middlewares;

use Acseven\VBulletinRedirects\Redirector;
use Flarum\Http\Exception\RouteNotFoundException;
use Flarum\Settings\SettingsRepositoryInterface;
use Laminas\Diactoros\Response\RedirectResponse;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\MiddlewareInterface as Middleware;
use Psr\Http\Server\RequestHandlerInterface as Handler;

class RedirectMiddleware implements Middleware
{
    public function process(Request $request, Handler $handler): Response
    {
        try {
            return $handler->handle($request);
        } catch (RouteNotFoundException $exception) {
            $to = app(Redirector::class)->redirect($request->getUri());

            if ($to) {
                $settings = app(SettingsRepositoryInterface::class);
                $status = intval($settings->get('acseven-vbulletin-redirects.redirectStatus')) ?: 301;

                if (!in_array($status, [301, 302], true)) {
                    throw new \Exception("Invalid redirect status code $status");
                }

                return new RedirectResponse($to, $status);
            }

            throw $exception;
        }
    }
}

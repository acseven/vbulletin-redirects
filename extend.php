<?php

namespace Acseven\VBulletinRedirects;

use Flarum\Extend;

return [
    // Position matters: ResolveRoute (route_resolver) throws RouteNotFoundException
    // on unknown urls, and HandleErrors (flarum.forum.error_handler, shallower in
    // the stack) catches every exception and renders the 404 page. So we must sit
    // DEEPER than the error handler (->add() at the end would also do that, but
    // lands inside ResolveRoute's frame where the throw never passes) and
    // SHALLOWER than the route resolver — directly before it.
    (new Extend\Middleware('forum'))
        ->insertBefore('flarum.forum.route_resolver', Middlewares\RedirectMiddleware::class),
];

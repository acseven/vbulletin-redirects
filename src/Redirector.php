<?php

namespace Acseven\VBulletinRedirects;

use Flarum\Http\UrlGenerator;
use Psr\Http\Message\UriInterface;

/**
 * Maps legacy SMF urls onto Flarum routes. Ids are preserved by the
 * migration, so topic -> discussion id and msg -> post id.
 *
 * Query style (SMF 2.x) and "queryless" paths (SMF 1.x):
 *   /index.php?topic=123             -> /d/123
 *   /index.php?topic=123.45          -> /d/123/46        (45 = 0-based message offset)
 *   /index.php?topic=123.msg4567     -> /d/{discussion}/  + post 4567's number
 *   /index.php?msg=4567              -> same, via post lookup
 *   /index.php/topic,123.msg4567.html -> same
 *   /index.php/topic,123.45.html     -> /d/123/46
 *   /index.php?board=..., /index.php/board,N.M.html -> /
 *   /index.php?action=profile;u=9    -> /u/{username}
 *   anything else under /index.php   -> /
 */
class Redirector
{
    protected $repository;
    protected $url;

    public function __construct(Repository $repository, UrlGenerator $url)
    {
        $this->repository = $repository;
        $this->url = $url;
    }

    public function redirect(UriInterface $uri): ?string
    {
        $path = rawurldecode($uri->getPath());
        $query = $this->parseQuery($uri->getQuery());

        // SMF 1.x queryless paths
        if (preg_match('~/index\.php/(topic|board),~', $path)) {
            if (preg_match('~/index\.php/board,\d+(?:\.\d+)?(?:\.html)?$~', $path)) {
                return $this->home();
            }

            if (preg_match('~/index\.php/topic,(\d+)\.(msg(\d+)|(\d+))(?:\.html)?$~', $path, $m)) {
                // skipped alternation groups come back as '' (set!), not unset
                if (($m[3] ?? '') !== '') {
                    return $this->redirectPost((int) $m[3]) ?? $this->redirectDiscussion((int) $m[1]);
                }

                return $this->redirectDiscussion((int) $m[1], (int) $m[4]);
            }

            return null;
        }

        if (isset($query['topic'])) {
            // topic=123 | topic=123.45 | topic=123.msg4567; extras like ;topicseen are separate params
            if (preg_match('~^(\d+)(?:\.(msg(\d+)|(\d+)))?(?=[;&]|$)~', $query['topic'], $m)) {
                if (($m[3] ?? '') !== '') {
                    return $this->redirectPost((int) $m[3]) ?? $this->redirectDiscussion((int) $m[1]);
                }

                return $this->redirectDiscussion((int) $m[1], (int) ($m[4] ?? 0));
            }

            return null;
        }

        if (isset($query['msg'])) {
            return preg_match('~^\d+$~', $query['msg'])
                ? $this->redirectPost((int) $query['msg'])
                : null;
        }

        if (isset($query['board'])) {
            return $this->home();
        }

        if (isset($query['action'])) {
            if ($query['action'] === 'profile'
                && isset($query['u'])
                && preg_match('~^\d+$~', $query['u'])) {
                return $this->redirectUser((int) $query['u']) ?? $this->home();
            }

            return $this->home();
        }

        if ($path === '/index.php') {
            return $this->home();
        }

        return null;
    }

    /**
     * SMF separates query params with ';' as often as '&', and old links
     * carry cruft (PHPSESSID, topicseen, prev_next, ...) that must be ignored.
     */
    protected function parseQuery(string $query): array
    {
        // ponytail: decode the WHOLE query before splitting — bots normalize
        // SMF's ';' separators (and '=') to %3B/%3D and those must become
        // split points again. Ceiling: safe only because every value in this
        // grammar is a numeric id or bare action name, never a legal ;, & or =.
        // Generalize this parser and this line corrupts values.
        $query = rawurldecode($query);

        $params = [];

        foreach (preg_split('~[&;]~', $query) ?: [] as $pair) {
            if (strpos($pair, '=') === false) {
                continue;
            }

            [$key, $value] = explode('=', $pair, 2);

            $params[$key] = $value;
        }

        return $params;
    }

    protected function redirectDiscussion(int $id, int $start = 0): ?string
    {
        $discussion = $this->repository->discussion($id);

        if (!$discussion || $discussion->is_private) {
            return null;
        }

        $identifier = $discussion->id . ($discussion->slug ? '-' . $discussion->slug : '');

        // SMF's start offset counts messages before the target (0-based);
        // Flarum's /d/{id}/{near} wants the 1-based post number.
        return $start > 0
            ? $this->path("d/$identifier/" . ($start + 1))
            : $this->path("d/$identifier");
    }

    protected function redirectPost(int $id): ?string
    {
        $post = $this->repository->post($id);

        if (!$post) {
            return null;
        }

        // Same visibility rule as topic links: a private discussion's posts
        // must not be confirmed to exist (301) vs deleted (404)
        $discussion = $this->repository->discussion($post->discussion_id);

        if (!$discussion || $discussion->is_private) {
            return null;
        }

        return $this->path('d/' . $post->discussion_id . '/' . $post->number);
    }

    protected function redirectUser(int $id): ?string
    {
        $user = $this->repository->user($id);

        if (!$user) {
            return null;
        }

        return $this->path('u/' . $user->username);
    }

    protected function home(): string
    {
        // ponytail: boards -> home; hardcode a board=>tag-slug map if board
        // links ever matter (mapping lives in the SMF db, flarum_migrated_boards)
        return $this->path('');
    }

    protected function path(string $path): string
    {
        return $this->url->to('forum')->path($path);
    }
}

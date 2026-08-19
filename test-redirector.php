<?php

/**
 * Standalone check of the SMF -> Flarum url mapping. No Flarum install
 * needed: the Flarum classes Redirector touches are stubbed below.
 *
 * Run: php test-redirector.php
 */

namespace Flarum\Discussion {
    class Discussion
    {
        public $id;
        public $slug;
        public $is_private = false;
    }
}

namespace Flarum\Post {
    class Post
    {
        public $id;
        public $discussion_id;
        public $number;
    }
}

namespace Flarum\User {
    class User
    {
        public $id;
        public $username;
    }
}

namespace Flarum\Http {
    class UrlGenerator
    {
        public function to($frontend)
        {
            return $this;
        }

        public function path($path = '')
        {
            return 'https://forum.example.com' . ($path !== '' ? '/' . $path : '');
        }
    }
}

namespace Psr\Http\Message {
    interface UriInterface
    {
        public function getPath();
        public function getQuery();
    }
}

namespace Acseven\VBulletinRedirects {
    use Psr\Http\Message\UriInterface;

    require __DIR__ . '/src/Repository.php';
    require __DIR__ . '/src/Redirector.php';

    class FakeUri implements UriInterface
    {
        private $path;
        private $query;

        public function __construct(string $path, string $query = '')
        {
            $this->path = $path;
            $this->query = $query;
        }

        public function getPath()
        {
            return $this->path;
        }

        public function getQuery()
        {
            return $this->query;
        }
    }

    class FakeRepository extends Repository
    {
        public $discussions = [];
        public $posts = [];
        public $users = [];

        public function discussion(int $id): ?\Flarum\Discussion\Discussion
        {
            return $this->discussions[$id] ?? null;
        }

        public function post(int $id): ?\Flarum\Post\Post
        {
            return $this->posts[$id] ?? null;
        }

        public function user(int $id): ?\Flarum\User\User
        {
            return $this->users[$id] ?? null;
        }
    }

    function discussion(int $id, ?string $slug = null, bool $private = false)
    {
        $d = new \Flarum\Discussion\Discussion();
        $d->id = $id;
        $d->slug = $slug;
        $d->is_private = $private;
        return $d;
    }

    function post(int $id, int $discussionId, int $number)
    {
        $p = new \Flarum\Post\Post();
        $p->id = $id;
        $p->discussion_id = $discussionId;
        $p->number = $number;
        return $p;
    }

    function user(int $id, string $username)
    {
        $u = new \Flarum\User\User();
        $u->id = $id;
        $u->username = $username;
        return $u;
    }

    $repo = new FakeRepository();
    $repo->discussions = [
        111 => discussion(111, 'some-thread'),
        222 => discussion(222, 'another-thread'),
        333 => discussion(333),
        444 => discussion(444, 'secret', true),
    ];
    $repo->posts = [
        999 => post(999, 333, 26),
        888 => post(888, 222, 1),
    ];
    $repo->users = [5 => user(5, 'someuser')];

    $redirector = new Redirector($repo, new \Flarum\Http\UrlGenerator());
    $base = 'https://forum.example.com';

    $checks = 0;
    $failures = 0;

    function check(Redirector $r, string $path, string $query, ?string $want, string $label)
    {
        global $checks, $failures;
        $checks++;
        $got = $r->redirect(new FakeUri($path, $query));
        if ($got !== $want) {
            $failures++;
            echo "FAIL $label\n  got:  " . var_export($got, true) . "\n  want: " . var_export($want, true) . "\n";
        }
    }

    // SMF 2.x query style
    check($redirector, '/index.php', 'topic=111.0', "$base/d/111-some-thread", 'topic start');
    check($redirector, '/index.php', 'topic=111', "$base/d/111-some-thread", 'topic bare');
    check($redirector, '/index.php', 'topic=111.120', "$base/d/111-some-thread/121", 'topic page offset');
    check($redirector, '/index.php', 'topic=333.msg999', "$base/d/333/26", 'topic msg deep link');
    check($redirector, '/index.php', 'topic=333.msg9999', "$base/d/333", 'topic msg fallback to discussion');
    check($redirector, '/index.php', 'msg=888', "$base/d/222/1", 'bare msg');
    check($redirector, '/index.php', 'msg=99999', null, 'bare msg unknown');
    check($redirector, '/index.php', 'board=14.0', $base, 'board query');
    check($redirector, '/index.php', 'action=profile;u=5', "$base/u/someuser", 'profile');
    check($redirector, '/index.php', 'action=profile;u=777', $base, 'profile unknown user');
    check($redirector, '/index.php', 'action=unread', $base, 'other action');
    check($redirector, '/index.php', 'action=printpage;topic=111.0', "$base/d/111-some-thread", 'printpage with topic');
    check($redirector, '/index.php', 'topic=333.msg999;prev_next=prev', "$base/d/333/26", 'msg with extra params');
    check($redirector, '/index.php', 'PHPSESSID=deadbeef;topic=111.0', "$base/d/111-some-thread", 'session id cruft');
    check($redirector, '/index.php', 'topic=99999', null, 'unknown topic');
    check($redirector, '/index.php', 'topic=444.0', null, 'private discussion');
    check($redirector, '/index.php', 'topic=abc', null, 'garbage topic');
    check($redirector, '/index.php', '', $base, 'bare index.php');

    // SMF 1.x queryless paths
    check($redirector, '/index.php/topic,222.msg888.html', '', "$base/d/222/1", 'queryless msg deep link');
    check($redirector, '/index.php/topic,222.0.html', '', "$base/d/222-another-thread", 'queryless topic start');
    check($redirector, '/index.php/topic,222.15.html', '', "$base/d/222-another-thread/16", 'queryless topic page');
    check($redirector, '/index.php/board,14.0.html', '', $base, 'queryless board');

    // Non-SMF urls: no redirect, let Flarum handle
    check($redirector, '/d/222', '', null, 'real flarum route 404');
    check($redirector, '/some/random/path', '', null, 'random path');

    echo "$checks checks, $failures failures\n";
    exit($failures ? 1 : 0);
}

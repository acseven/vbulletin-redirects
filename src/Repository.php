<?php

namespace Acseven\VBulletinRedirects;

use Flarum\Discussion\Discussion;
use Flarum\Post\Post;
use Flarum\User\User;

/**
 * The migration inserted discussions, posts and users keeping their SMF
 * ids (id_topic / id_msg / id_member), so every lookup is a primary key find.
 */
class Repository
{
    public function discussion(int $id): ?Discussion
    {
        return Discussion::find($id);
    }

    public function post(int $id): ?Post
    {
        return Post::find($id);
    }

    public function user(int $id): ?User
    {
        return User::find($id);
    }
}

# acseven/vbulletin-redirects

Redirects legacy SMF urls to Flarum.
Forked from [migratetoflarum/vbulletin-redirects](https://github.com/migratetoflarum/vbulletin-redirects)
(Clark Winkelmann, MIT), re-targeted for SMF urls and Flarum 1.x.

The SMF → Flarum migration preserved ids (discussion = `id_topic`,
post = `id_msg`, user = `id_member`), so every mapping is a direct id
lookup, no translation table needed.

## Mapping

| Old SMF url | Redirects to |
| --- | --- |
| `?topic=123` / `?topic=123.0` | `/d/123` |
| `?topic=123.45` (message offset) | `/d/123/46` |
| `?topic=123.msg4567` | `/d/{discussion}/{post number}` (post lookup) |
| `?msg=4567` | same post lookup |
| `/index.php/topic,123.msg4567.html` (SMF 1.x) | same |
| `/index.php/topic,123.45.html` | `/d/123/46` |
| `?board=…` / `/index.php/board,…` | `/` |
| `?action=profile;u=9` | `/u/{username}` |
| any other `?action=…` or bare `/index.php` | `/` |

Deep links to a deleted post fall back to the discussion start; links to a
missing/private discussion stay 404.

Caveat: SMF page offsets assume the SMF and Flarum post numbering match
(hidden/unapproved posts could shift a page target by a few posts — the
`/d/{id}/{near}` route is forgiving, it clamps).

## Settings

Redirect status defaults to **301**. Override via the `settings` table with
key `acseven-vbulletin-redirects.redirectStatus` (`301` or `302`; use 302
while testing).

## Tests

```
php test-redirector.php
```

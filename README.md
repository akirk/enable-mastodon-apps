# Enable Mastodon Apps

- Contributors: akirk, pfefferle
- Tags: mastodon, activitypub, friends, fediverse
- Requires at least: 5.0
- Tested up to: 6.2
- Requires PHP: 5.2.4
- License: [GPLv2 or later](http://www.gnu.org/licenses/gpl-2.0.html)
- Stable tag: 0.6.6

Allow accessing your WordPress with Mastodon clients. Just enter your own blog URL as your instance.

## Description

Despite Mastodon implying that you would use this plugin for engaging on Mastodon (when you have enabled it for that, see below), the plugin is useful when installed on a plain WordPress.

When you use a Mastodon app, you'll enter your own blog URL to connect and log in to your blog in the following screens.

You'll then see just the posts on your blog which can already be useful in a multi-author environment (e.g. private blogs). You can also use that Mastodon app to create simple posts with a message + attachment(s) which can be better suited for your usecase than using the Gutenberg-capable WordPress mobile app.

When used in combination with the [ActivityPub](https://wordpress.org/plugins/activitypub/) (for being followed via Mastodon) and [Friends](https://wordpress.org/plugins/friends/) (for following people on Mastodon or via RSS) plugins, the Enable Mastodon Apps plugin will show you your feed of people you follow and you can follow and unfollow people from within the app.

Be aware that an app will have a post format associated (see the settings page). The plugin will check for the existance of the Friends plugin to find a resonable default (status with Friends plugin, standard otherwise). When you create a post with your Mastodon app, the post format that you selected for the app will be used.

The plugin has been tested with quite a number of Mastodon clients, among them are:

- [Elk](https://elk.zone/) (Web)
- [Pinafore](https://pinafore.social/) (Web)
- [Tusky](https://tusky.app/) (Android)
- [Ivory](https://tapbots.com/ivory/) (macOS and iOS)
- [Mona](https://mastodon.social/@MonaApp) (macOS)
- [IceCubes](https://github.com/Dimillian/IceCubesApp) (macOS)

Many more, see the [Third-party apps section on joinmastodon.org/apps](https://joinmastodon.org/apps). Each app might have its quirks, [please report an issue when you have troubles[(https://github.com/akirk/enable-mastodon-apps/issues). There is also a chance that the API has not been implemented yet (see below.)

## Mastodon API Implementation

The plugin implements the [Mastodon API as documented on joinmastodon.org](https://docs.joinmastodon.org/api/): The OAuth API for logging in (you will see your WordPress login screen when logging in to your Mastodon app, it also works with 2FA plugins) and the REST API for accessing your data.

Here is a list of endpoints and their implementation status:

- [x] `GET /oauth/authorize` [Authorize a user](https://docs.joinmastodon.org/methods/oauth/#authorize)
- [x] `POST /oauth/token` [Obtain a token](https://docs.joinmastodon.org/methods/oauth/#authorize)
- [x] `POST /oauth/revoke` [Revoke a token](https://docs.joinmastodon.org/methods/oauth/#revoke)
- [ ] `GET /api/v1/emails/confirmation` [Resend confirmation email](https://docs.joinmastodon.org/methods/emails/#confirmation)
- [ ] `GET /api/v1/accounts` [Register an account](https://docs.joinmastodon.org/methods/accounts/#create)
- [x] `POST /api/v1/apps` [Create an application](https://docs.joinmastodon.org/methods/apps/#create)
- [ ] `GET /api/v1/apps/verify_credentials` [Verify your app works](https://docs.joinmastodon.org/methods/apps/#verify_credentials)
- [x] `GET /api/v1/instance` [View server information](https://docs.joinmastodon.org/methods/instance/) (v1!)
- [x] `GET /api/nodeinfo/2.0.json` (used by Pixelfed)
- [ ] `GET /api/v1/announcements` (implemented as empty) [View all announcements](https://docs.joinmastodon.org/methods/announcements/#get)
- [ ] `POST /api/v1/announcements/:id/dismiss` [Dismiss an announcement](https://docs.joinmastodon.org/methods/announcements/#dismiss)
- [ ] `POST /api/v1/announcements/:id/reactions/:name` [Reactions to an announcement](https://docs.joinmastodon.org/methods/announcements/#put-reactions)
- [ ] `GET /api/v1/filters` (implemented as empty)
- [ ] `GET /api/v1/lists` (implemented as empty)
- [ ] `GET /api/v1/custom_emojis` (implemented as empty) [View all custom emojis](https://docs.joinmastodon.org/methods/custom_emojis/#get)
- [x] `GET /api/v1/accounts/verify_credentials` [Verify account credentials](https://docs.joinmastodon.org/methods/accounts/#verify_credentials)
- [x] `GET /api/v1/accounts/:id` [Get account](https://docs.joinmastodon.org/methods/accounts/#get)
- [x] `GET /api/v1/accounts/:id/statuses` [Get account’s statuses](https://docs.joinmastodon.org/methods/accounts/statuses/#get)
- [x] `GET /api/v1/accounts/:id/followers` [Get account’s followers](https://docs.joinmastodon.org/methods/accounts/#followers)
- [ ] `GET /api/v1/accounts/:id/following` [Get account’s following](https://docs.joinmastodon.org/methods/accounts/#following)
- [ ] `GET /api/v1/accounts/:id/featured_tags` [Get account’s featured tags](https://docs.joinmastodon.org/methods/accounts/#featured_tags)
- [ ] `GET /api/v1/accounts/:id/lists` [Get lists containing this account](https://docs.joinmastodon.org/methods/accounts/#lists)
- [x] `GET /api/v1/accounts/:id/follow` [Follow account](https://docs.joinmastodon.org/methods/accounts/#follow)
- [x] `GET /api/v1/accounts/:id/unfollow` [Unfollow account](https://docs.joinmastodon.org/methods/accounts/#unfollow)
- [x] `GET /api/v1/accounts/relationships` [Check relationships to other accounts](https://docs.joinmastodon.org/methods/accounts/#relationships)
- [x] `POST /api/v2/media` [Upload media as an attachment (async)](https://docs.joinmastodon.org/methods/media/#v2)
- [x] `GET /api/v1/media/:id` [Get media attachment](https://docs.joinmastodon.org/methods/media/#get)
- [x] `POST /api/v1/statuses` [Post a new status](https://docs.joinmastodon.org/methods/statuses/#post)
- [x] `GET /api/v1/statuses/:id/context` [Get parent and child statuses in context](https://docs.joinmastodon.org/methods/statuses/#context)
- [x] `POST /api/v1/statuses/:id/favourite` [Favourite a status](https://docs.joinmastodon.org/methods/statuses/#favourite)
- [x] `POST /api/v1/statuses/:id/unfavourite` [Unfavourite a status](https://docs.joinmastodon.org/methods/statuses/#unfavourite)
- [x] `POST /api/v1/statuses/:id/reblog` [Boost a status](https://docs.joinmastodon.org/methods/statuses/#boost)
- [x] `POST /api/v1/statuses/:id/unreblog` [Undo the boost a status](https://docs.joinmastodon.org/methods/statuses/#unreblog)
- [x] `GET /api/v1/statuses/:id` [View a single status](https://docs.joinmastodon.org/methods/statuses/#get)
- [x] `GET /api/v1/notifications/` (partial, just mentions) [Get all notifications](https://docs.joinmastodon.org/methods/notifications/#get)
- [x] `GET /api/v1/notifications/:id` [Get a single notification](https://docs.joinmastodon.org/methods/notifications/#get-one)
- [x] `POST /api/v1/notifications/clear` [Dismiss all notification](https://docs.joinmastodon.org/methods/notifications/#clear)
- [x] `POST /api/v1/notifications/:id/dismiss` [Dismiss a single notification](https://docs.joinmastodon.org/methods/notifications/#dismiss)
- [x] `GET /api/v1/timelines/home` [View home timeline](https://docs.joinmastodon.org/methods/timelines/#home)
- [x] `GET /api/v1/timelines/public` [View public timeline](https://docs.joinmastodon.org/methods/timelines/#public)
- [ ] `GET /api/v1/markers` (implemented as empty) [Get saved timeline positions](https://docs.joinmastodon.org/methods/markers/#get)
- [ ] `POST /api/v1/markers` [Save your position in a timeline](https://docs.joinmastodon.org/methods/markers/#create)
- [x] `GET /api/v2/search` (partial, accounts (local and exact match for remote) and statuses in the local db) [Perform a search](https://docs.joinmastodon.org/methods/search/#v2)

Unmentioned endpoints are not implemented. Contributions welcome!

Endpoints around interacting with non-local users require the [ActivityPub plugin](https://wordpress.org/plugins/activitypub). Following users requires the [Friends plugin](https://wordpress.org/plugins/friends). Lists-related endpoints require the [Friends Roles plugin](https://github.com/akirk/friends-roles).

## Screenshots

1. You authorize Mastodon Apps through your own WordPress's login UI.
2. The Mastodon Apps settings page.

## Changelog

### 0.6.6
- Implement Autoloader ([#73])
- Add scope adherence ([#76])

### 0.6.5
- Fix missing image attachments for WordPress posts, props @thatguygriff ([#72])

### 0.6.4
- Address an incompatibility with the IndieAuth plugin ([#65])

### 0.6.3
- Thanks @toolstack for lots of PRs with small fixes and enhancements!
- Fixed compatibility with version 2.0.0 of the ActivityPub plugin ([#60]) thanks @toolstack!
- Strip the protocol from the home_url ([#52]) props @toolstack
- Add additional warning about changing the default post format ([#53]) props @toolstack
- Make sure to decode html_entities for blog_info() ([#51]) props @toolstack
- Enable local/public timeline ([#62]) props @toolstack
- Check to make sure the current user has edit_post posting ([#61]) props @toolstack
- Fix duplicate line generation in whitespace code ([#55]) props @toolstack

### 0.6.2
- Add a setting to implicitly re-register the next unknown client ([#48])
- Add Instance-Endpoint Filters ([#45]) props @pfefferle

### 0.6.1
- Communicate the current settings inside Mastodon using an Announcement ([#44])
- Bring back library adaptations which hopefully solves the "No client id supplied" problem

### 0.6.0
- Use a Custom Post Type to for mapping post ids ([#42])
- Improve response time after transients expired ([#41])

### 0.5.0
- Major fixes for Ivory, now we're fullfilling (most of?) their assumptions ([#37])

### 0.4.2
-  Fix media upload in Ice Cubes and potentially other clients ([#35])

### 0.4.1
-  Fix boost attribution ([#33])

### 0.4.0
- Improve notification pagination ([#29])
- Compatibility fixes for Friends 2.6.0 ([#31])

### 0.3.6
- Improve debug logging.

### 0.3.5
- Fix little inconsistencies with min_id and max_id.
- Add a debug mode ([#23]).

### 0.3.4
- Implement min_id to avoid double postings in the IceCubes app.

### 0.3.3
- Fixes for Mastodon for iOS and Mammoth.
- Fix deleting of toots.

### 0.3.2
- Ivory should work now.
- Posting: wrap mentions and links in HTML tags
- Attachments: try harder to deduplicate them, identify attachment types
- Admin: More tools for cleaning out stale apps and tokens
- Search: You can now search for URLs so that you can reply to them

### 0.3.1
- Implemented: Attachment descriptions (and updates for it)
- Use the first line of a post as the post title if we're using a standard post format

### 0.3.0
- Implemented: search for a specific remote account to follow it
- Implemented: Notifications for mentions
- Added: Option whether replies should be posted as comments on the messages or as new posts
- Fixed: Ivory should now be able to connect

### 0.2.1
- Improve compatibility of Swift based apps
- Fix fatal error on admin page

### 0.2.0
- Post replies as comments ([#3])
- Fix a fatal when saving the default post format

[#73]: https://github.com/akirk/enable-mastodon-apps/pull/73
[#76]: https://github.com/akirk/enable-mastodon-apps/pull/76
[#72]: https://github.com/akirk/enable-mastodon-apps/pull/72
[#65]: https://github.com/akirk/enable-mastodon-apps/pull/65
[#60]: https://github.com/akirk/enable-mastodon-apps/pull/60
[#52]: https://github.com/akirk/enable-mastodon-apps/pull/52
[#53]: https://github.com/akirk/enable-mastodon-apps/pull/53
[#51]: https://github.com/akirk/enable-mastodon-apps/pull/51
[#62]: https://github.com/akirk/enable-mastodon-apps/pull/62
[#61]: https://github.com/akirk/enable-mastodon-apps/pull/61
[#55]: https://github.com/akirk/enable-mastodon-apps/pull/55
[#48]: https://github.com/akirk/enable-mastodon-apps/pull/48
[#45]: https://github.com/akirk/enable-mastodon-apps/pull/45
[#44]: https://github.com/akirk/enable-mastodon-apps/pull/44
[#42]: https://github.com/akirk/enable-mastodon-apps/pull/42
[#41]: https://github.com/akirk/enable-mastodon-apps/pull/41
[#37]: https://github.com/akirk/enable-mastodon-apps/pull/37
[#35]: https://github.com/akirk/enable-mastodon-apps/pull/35
[#33]: https://github.com/akirk/enable-mastodon-apps/pull/33
[#31]: https://github.com/akirk/enable-mastodon-apps/pull/31
[#29]: https://github.com/akirk/enable-mastodon-apps/pull/29
[#23]: https://github.com/akirk/enable-mastodon-apps/pull/23
[#3]: https://github.com/akirk/enable-mastodon-apps/pull/3




# Enable Mastodon Apps

- Contributors: akirk, pfefferle, drivingralle, kittmedia, obenland
- Tags: mastodon, activitypub, friends, fediverse
- Requires at least: 5.0
- Tested up to: 6.6
- Requires PHP: 7.4
- License: [GPLv2 or later](http://www.gnu.org/licenses/gpl-2.0.html)
- Stable tag: 0.9.7

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
- [Mammoth](https://getmammoth.app/) (macOS and iOS)
- [Phanpy](https://phanpy.social) (Web)
- [Mona](https://mastodon.social/@MonaApp) (macOS and iOS)

Many more, see the [Third-party apps section on joinmastodon.org/apps](https://joinmastodon.org/apps). Each app might have its quirks, [please report an issue when you have troubles](https://github.com/akirk/enable-mastodon-apps/issues). There is also a chance that the API has not been implemented yet (see below.)

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
- [x] `PATCH /api/v1/accounts/update_credentials` [Update account credentials](https://docs.joinmastodon.org/methods/accounts/#update_credentials)
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

### 0.9.7
- Fixed bug where the create post type was ignored ([#175])
- Automatically convert post content to blocks, can be disabled ([#174])

### 0.9.6
- Adds steaming_api to instance_v1, props @mediaformat ([#171])
- PATCH routes: support field_attributes, props @mattwiebe ([#167])
- Make token storage taxonomies private, props @mattwiebe ([#165])
- Updated tester.html from upstream
- Introduce a Never Used label to the registered apps screen.

### 0.9.5
- Add a details link to the apps page ([#163])
- Show all comments by others as notifications ([#164])
- Update NodeInfo endpoint by @pfefferle ([#162])
- Multisite: ensure that user_ids only work for users of this site by @mattwiebe ([#158])
- Increase phpcs rules and fix them by @mattwiebe ([#160], [#155])
- Add `api/v1/accounts/update_credentials` route by @mattwiebe ([#157])

### 0.9.4
- Added a dedicated page per app in the settings. There you can set which post types should be shown in the app. Also which post type should be created for new posts. ([#154])
- Fixed authenticating Jetpack so that you can connect WordPress.com to this plugin ([#152])

### 0.9.3
- Bring back the upgrade code.

### 0.9.2
- Quick fix to disable the upgrade script to avoid errors.

### 0.9.1
- Allow an empty search type, to search in all categories ([#150]) props @pfefferle
- Don't reactivate the Link Manager ([#148])
- Avoid errors when dividing strings ([#147]) props @mattwiebe
- Don't include spam comments in the feed ([#149])
- Ensure no spaces in URLs ([#144])
- Fix some typos on the Welcome Screen ([#143])

### 0.9.0
- Complete Rewrite, started at the Cloudfest Hackathon! Props @pfefferle, @drivingralle, @kittmedia, @obenland
- Thus: all ActivityPub related tasks are handled by the ActivityPub plugin, all following-related tasks by the Friends plugin. Please make sure you have the latest version of those plugins if you want to use such features
- Reorganized settings, added a way to tester the local api ([#138], [#140])
- Allow Editing a submitted status ([#137])
- Improves to Attachments ([#132], [#136])
- Fix OAuth rewrite path ([#130])

[#174]: https://github.com/akirk/enable-mastodon-apps/pull/174
[#173]: https://github.com/akirk/enable-mastodon-apps/pull/173
[#171]: https://github.com/akirk/enable-mastodon-apps/pull/171
[#167]: https://github.com/akirk/enable-mastodon-apps/pull/167
[#165]: https://github.com/akirk/enable-mastodon-apps/pull/165
[#163]: https://github.com/akirk/enable-mastodon-apps/pull/163
[#164]: https://github.com/akirk/enable-mastodon-apps/pull/164
[#162]: https://github.com/akirk/enable-mastodon-apps/pull/162
[#160]: https://github.com/akirk/enable-mastodon-apps/pull/160
[#158]: https://github.com/akirk/enable-mastodon-apps/pull/158
[#157]: https://github.com/akirk/enable-mastodon-apps/pull/157
[#155]: https://github.com/akirk/enable-mastodon-apps/pull/155
[#154]: https://github.com/akirk/enable-mastodon-apps/pull/154
[#152]: https://github.com/akirk/enable-mastodon-apps/pull/152
[#150]: https://github.com/akirk/enable-mastodon-apps/pull/150
[#148]: https://github.com/akirk/enable-mastodon-apps/pull/148
[#147]: https://github.com/akirk/enable-mastodon-apps/pull/147
[#149]: https://github.com/akirk/enable-mastodon-apps/pull/149
[#144]: https://github.com/akirk/enable-mastodon-apps/pull/144
[#143]: https://github.com/akirk/enable-mastodon-apps/pull/143
[#140]: https://github.com/akirk/enable-mastodon-apps/pull/140
[#138]: https://github.com/akirk/enable-mastodon-apps/pull/138
[#137]: https://github.com/akirk/enable-mastodon-apps/pull/137
[#136]: https://github.com/akirk/enable-mastodon-apps/pull/136
[#132]: https://github.com/akirk/enable-mastodon-apps/pull/132
[#130]: https://github.com/akirk/enable-mastodon-apps/pull/130

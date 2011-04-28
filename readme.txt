=== Category Subscriptions ===
Contributors: djcp
Donate link: 
Tags: email, notification, notify, category, subscription, subscriptions
Requires at least: 3.0.3
Tested up to: 2.1
Stable tag: 1.0

Allow registered users to subscribe to categories giving them control over delivery times (e.g. daily or weekly digests) and format (html or text).

== Description ==

This plugin allows users of subscriber- or greater access to subscribe to categories of posts on your wordpress blog. It is probably most appropriate for small to medium sites.

Users can:

* Choose whether they prefer HTML or text-only email,
* Control on a category-by-category basis whether or not they get messages immediately or in a daily or weekly digest.
* Control which categories they subscribe to.

Site administrators can:

* modify reply-to, from, and BCC addresses.
* modify user subscriptions on a users profile page.
* set basic parameters for delivery - how many messages to send at once to avoid usage complaints on shared hosting, for instance.
* exercise complete control over text and HTML templates.

Other notes:

* Scheduled posts are only sent out after they are actually published.
* Admins can set a "send delay" parameter to allow last-minute edits before sending out a message.
* If a message is unpublished after being published, message sending will be aborted as long as none have gone out already.  This ensures that old posts needing minor edits don't get sent out again as updates.
* Only messages published after the installation are sent out.
* Category hierarchy has no effect on subscriptions. If you subscribe to a parent category, you ARE NOT automatically subscribed to its children. This may be integrated into future releases.

== Installation ==

Upload and unzip the file into your plugins/ folder. This works fine under wordpress multisite.

== Frequently Asked Questions ==

= I don't allow random people to register for my blog, you insensitive clod! =

Sorry to hear it. You can add them manually, obviously, and collect their addresses in some other fashion. You can also use [this](http://wordpress.org/extend/plugins/simple-import-users/) plugin to bulk import users.

== Changelog ==

= 1.0 =
* Initial release


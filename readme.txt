=== Category Subscriptions ===

Contributors: djcp
Donate link: 
Tags: email, notification, notify, category, subscription, subscriptions
Requires at least: 3.0.3
Tested up to: 3.1.3
Stable tag: 1.1

Allow registered users to subscribe to categories giving them control over delivery times (e.g. daily or weekly digests) and format (html or text).

== Description ==

This plugin allows users of subscriber- or greater access to subscribe to categories of posts on your wordpress blog. It is probably most appropriate for small to medium sites.

Users can:

* Choose whether they prefer HTML or text-only email,
* Control on a category-by-category basis whether or not they get messages immediately or in a daily or weekly digest.
* Control which categories they subscribe to.

Site administrators can:

* Modify reply-to, from, and BCC addresses.
* Modify user subscriptions on a users profile page.
* Set basic parameters for delivery - how many messages to send at once to avoid usage complaints on shared hosting, for instance.
* Bulk edit category subscriptions from the users list.
* Exercise complete control over text and HTML templates.

Other notes:

* Scheduled posts are only sent out after the wordpress backend actually publishes them. Put more simply, they work as expected. Be sure wp-cron is running correctly.
* Admins can set a "send delay" parameter to allow last-minute edits before sending out a message.
* If a message is unpublished after being published, message sending will be aborted. Messages sent already are - well - sent already. We can't travel back in time (though that'd be a great 2.0 feature). This ensures that old posts needing minor edits don't get sent out again as updates.
* Only messages published after the date of installation are sent out.
* Category hierarchy has no effect on subscriptions. If you subscribe to a parent category, you ARE NOT automatically subscribed to its children. This may be integrated into future releases. We may also allow for the subscription to tags or custom taxonomies in future releases.
* Bounces ARE NOT handled by this plugin. You can, however, set the "reply-to" and "from" addresses to allow you to collect them in a logical place.

Speaking of cron, if you have a low-traffic site (say, for an intranet), you may find that your messages aren't delivered in the time frame you're expecting. This is because wordpress scheduled tasks are fired off by visitors to your website - so if you don't get a lot of traffic, your scheduled tasks won't run frequently. You can manually create a cron job to hit your wp-cron.php file, something like:

 */15 * * * * wget -q --post-data="foo" http://www.example.com/yoursite -O - > /dev/null

Remove the "-q" switch above when you test this manually to ensure the request returns a "200 OK" response.

TODO:

* More template tags.
* Better bulk editing features.
* Queue management / statistics.
* A better template editing interface.
* Better debugging - e.g. the ability to send example messages.
* More flexible task scheduling.

== Installation ==

Upload and unzip the file into your plugins/ folder. Multisite is A-OK.

== Frequently Asked Questions ==

= I don't allow random people to register for my blog, you insensitive clod! =

Sorry to hear it. You can add them manually, obviously, and collect their addresses in some other fashion. You can also use [this](http://wordpress.org/extend/plugins/simple-import-users/) plugin to bulk import users.

= I would like to subscribe the entire population of Canada to my site. Will this plugin work for me? =

Nope. 

1. We hate spam. Don't do it.
1. The delivery / templatting system is made to be "good enough" for small- to medium- sites, it's not engineered to serve as your own personal 'botnet.  We also don't do bounce handling, clickthrough tracking or bulk / envelope sending.

= Why no bounce processing? =

Because it's really complicated. Happy to entertain submissions.

== More Documentation ==

See the "Category Subscriptions" options page under your Settings menu. Template tag documentation lives there.

== Changelog ==

= 1.0 =

* Initial release

== Other ==

This plugin is licensed under the same terms as Wordpress itself.

Copyright 2011, The President and Fellows of Harvard College


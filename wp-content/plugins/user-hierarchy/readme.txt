=== User Hierarchy ===
Contributors: jesper800
Donate link: http://www.jepps.nl
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html
Tags: user,hierarchy,roles,users,role,hierarchies,capabilities,create,edit,promote,user
Requires at least: 3.1
Tested up to: 3.5.1
Stable tag: 0.1.2

Control user management on a per-role basis. Allow users of a certain role to only add, edit or delete users from specific other roles.

== Description ==

Ever since the WordPress roles and capabilities system implemented in WordPress verion 2.0 back in 2005, there has been no easy way to implement a user hierarchy.

With the User Hierarchy plugin, you can control which user roles can edit which other user roles. For example, you can add a new user role called "Manager" that can view all users, but only create and edit users with the "Author" user role. This plugin gives you full control over your user management, even when you are using a complex role hierarchy.

== Frequently Asked Questions ==

What were you expecting to find here? This plugin has just been released, no questions have been asked whatsoever about this plugin, let alone **frequently**.

== Installation ==

For automatic installation, just click "Install", activate the plugin and you're ready to go!

1. Upload the folder `user-hierarchy` to your plugins folder
1. Go the the "Plugins" page in your admin panel and activate the plugin
1. Done! Did we even need a list for that...

== Screenshots ==

1. Manage user creation, editing and removal permissions on a per-role basis

== Changelog ==

= 0.1.2 =

* Removed functionality for listing users due to a slow query that can currently not be properly fixed

= 0.1.1 =

* Fixing minor issue for when the plugin is installed and plugin actions are performed without the plugin options being set

= 0.1 =

* Initial release
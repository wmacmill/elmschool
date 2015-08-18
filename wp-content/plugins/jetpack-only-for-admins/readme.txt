=== Jetpack Only for Admins ===
Contributors: jehevre, andrija
Tags: jetpack, admin, hide
Requires at least: 3.0.1
Tested up to: 3.5
Stable tag: 1.1
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Hides Jetpack for all non admin users.

== Description ==

Hides Jetpack for all non admin users.
Hides the menu page as well as the icon in the admin bar.


Please Notice (!)
No one but admins will see that you use Jetpack.
No one will know.
If you want only the page to be hidden but not the icon, contact me or
just remove everything from line 37 till the end in jp-rm-jpmenu.php

Simple and short, originally made by http://profiles.wordpress.org/jeherve

== Installation ==

1. Upload `jp-rm-jpmenu.php` to the `/wp-content/plugins/jp-rm-jpmenu/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. That's it, you're done!

== Frequently Asked Questions ==

= How it works ? =

If Jetpack is activated and the user is not admin, all Jetpack traces are hidden.

= Do you plan on upgrades ? =

Yes, if interest is shown.
New version could be made which adds options under Settings in admin dashboard, where you could select which role
can't see Jetpack and is the page turned off, the icon, or both.

== Screenshots ==

1. This screen shot description corresponds to screenshot-1.(png|jpg|jpeg|gif). Note that the screenshot is taken from
the /assets directory or the directory that contains the stable readme.txt (tags or trunk). Screenshots in the /assets 
directory take precedence. For example, `/assets/screenshot-1.png` would win over `/tags/4.3/screenshot-1.png` 
(or jpg, jpeg, gif).
2. This is the second screen shot

== Changelog ==

= 1.1 =
* Updated to hide the small icon in the admin bar too

= 1.0 =
* First version by jehevre

`<?php code(); // goes in backticks ?>`

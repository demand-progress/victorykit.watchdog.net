____ WP Stagecoach ____
Contributors: Jonathan Kay, Morgan Kay
Tags: staging site
Requires at least: 3.0.1
Tested up to: 4.6.1
URL: https://wpstagecoach.com

WordPress staging sites made easy.

== Description ==

WP Stagecoach lets you create a staging copy of your WordPress site and merge your changes back to your live site.
Never develop on a live site again!

Key features:

* Create a staging copy of your live site with one click.
* Copy changes from your staging site back to your live site.
* Choose which changes to import. You can import some or all of your file changes, and/or your database changes.
* Password-protect your staging site.
* SFTP access to your staging site.
* Staging site runs on our server – no need to set up hosting for your staging site.
* Revert file changes if importing from your staging site doesn’t go as expected.
* SSL enabled on all staging sites.

== Installation ==

Please see https://wpstagecoach.com/support/instructions/

== Changelog ==

= v1.4.0 =
* new method for importing changes
* better support for creating staging sites when cURL is not available
* new theme to match new website
* dedicated to Penelope Higglebottom for keeping us sane by forcing us to walk every day

= v1.3.6 =
* small changes and optimizations
* tooltips for checkboxes
* better https support
* better non-apache web server support

= v1.3.5 =
* better handling of sites that use a different dir for WP install
* better handling of sites with slow database servers
* includes empty directories

= v1.3.4 =
* better support for must-use plugins
* added checks for sqlite for future improvements

= v1.3.3 =
* changed staging site URL to be lowercase as some themes/plugins had redirect problems with uppercase URLs
* changed the API key field to hide the API after it is entered
* lots of small optimizations and fixes

= v1.3.2 =
* changed import procedure to better handle multiple checks before importing.
* made changes to database creation to better support binary data
* skipped tar file check on hosts using HHVM because of extreme slowness
* new PEAR and Tar Archive versions

= v1.3.1 =
* changed Slow Server Optimization to only scan wp-content dir
* added option to send debug files to support

= v1.3 =
* added more flexibility with database file creation
* more advanced debugging options
* added abiltiy to skip database tables and directories
* better support for non-standard WP installs
* uses get_home_path() everywhere instead of other methods for determining site's directory path
* many other bug fixes

= v1.2 =
* support for additional plans
* support for more platforms
* of course, bug fixes! :-)

= v1.1 =
* support for additional plans
* option to tar all files at once
* support for sites which symlink wp-includes and wp-admin directories
* better presentation of information from staging servers

= v1.0 =
* Initial release version!
* Dedicated to our dog, Archibald, who was a never-ending source of unbridled optimism while WP Stagecoach was in development.


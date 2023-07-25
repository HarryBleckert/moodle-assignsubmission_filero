FILERO archiving of Moodle assignments
==========
[![Build Status](https://travis-ci.org/cwarwicker/moodle-block_search_user.svg?branch=master)](https://travis-ci.org/cwarwicker/moodle-block_search_user)
[![Open Issues](https://img.shields.io/github/issues/cwarwicker/moodle-block_search_user)](https://github.com/cwarwicker/moodle-block_search_user/issues)
[![License](https://img.shields.io/badge/License-GPLv3-blue.svg)](https://www.gnu.org/licenses/gpl-3.0)

The search_user block allows you to search quickly for users, without having to go through the Moodle user interface.

* As site admin or user with block/block_search_user:searchall capability - Search all users in the site (if the block
  is on the site home or "my dashboard")
* As a user with block/block_search_user:search capability - Search all users on the course


Requirements
------------
Moodle 4.0, 4.1, 4.2

Screenshots
-----------
These screenshots were taken on a plain Moodle installation with no fancy theme installed. 
Appearances may vary slightly depending on your theme.


The global settings:

![settings](pix/screenshots/settings.jpg)

The summary of FILERO plugin:

![summary](pix/screenshots/summary.jpg)

Search results expanded to see extra links:

![Details_of_archived_files](pix/screenshots/details.jpg)


Installation
------------
**From gitHub:**
1. Download the latest version of the plugin from the [Releases](https://github.com/HarryBleckert/moodle-filero/releases) page.
2. Extract the directory from the zip file and rename it to 'filero' if it is not already named as such.
3. Place the 'filero' folder into your Moodle site's */mod/assign/submission/* directory.
4. Run the Moodle upgrade process either through the web interface or command line.
5. Open Website Administration -> Plugins -> Submission Plugins and enter the credentials required to connect with a FILERO account.
6. Configure other settings as fitting.
7. Use FILERO by marking "FILERO archiving" as submission type.

License
-------
https://www.gnu.org/licenses/gpl-3.0

Support
-------
If you need any help using this plugin, or wish to report a bug or feature request, please use the issue tracking system: 
https://github.com/HarryBleckert/moodle-filero/issues

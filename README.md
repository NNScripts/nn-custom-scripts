nn-custom-scripts
=================

Custom Newznab+ scripts

Requirements:
* Newznab+
* PHP 5.3.10 or greater

Read the following guide carefully before using this scripts.

Disclamer
------
**Use these scripts at your own risk!<br />
I'm not responsible for loss of data or destroyed databases!**

Although these scripts are tested against an updated NewzNab+ release, it may happen that a script runs into an unexpected error.<br />
You can help by reporting problems/errors or by forking these scripts and creating a pull-request.

Before reporting an error, please run the script with the "display" setting enabled.<br />
Provide the output and possible php/sql error's as well (make sure your php installation is configured to display errors).<br />

Installation
-------
These scripts need to be places in a subdirectory within the misc directory of your newznab installation.
The most easy way is to clone this repository from within the misc directory.

first enter the misc directory (from the root of your newznab installation):

    cd misc

then from within the misc directory run the following command:

    git clone git://github.com/NNScripts/nn-custom-scripts.git nnscripts

After cloning a directory "nnscripts" is created containing the scripts.<br />

Configuration
-------
Scripts can be configured using the "settings.ini" file.<br />
This file contains a "global" section and a section for each script for specific settings (where available)<br />
<br />
The "global" section contains the defaults used by all script.<br />
For example if you do not want the scripts to output information to the screen, you can change the global "display" setting:

    [global]
    display = false

Each script can overwrite the default settings in it's own section:

    [remove_blacklist_releases]
    display = true
    limit = 48
    remove = false

Limit usage
-------
The limit (which will be used to remove data older the x hours) can be set in the settings.ini file.<br />
If no limit is set, the limit will be read from the newznab configuration. ("Header Retention" under "Admin" -> "Site edit").<br />
On a default installation this is 1.5 day (36 hours).<br />

Usage
-------
All scripts are ment te run from the commandline (or by update scripts)<br />
Most scripts have a build in help which can be shown using the "-h" parameter

Example:

    php remove_blacklist_releases.php -h
    
Will produce the following help

    Remove black or whitelisted releases - version 0.6

    Usage: php remove_blacklist_releases.php [options] [operands]
    Options:
      -f, --full              Check all releases (full database)
      -g, --group <arg>       Remove releases only from one group

      -d, --display           Enable output (settings default)
      -q, --quiet             Disable output

      -l, --limit <arg>       The limit in hours (default limit is 36 hours)
      -n, --nolimit           Disable the limit

      -r, --remove            Enable the removal of releases
      -k, --keep              Disable the removal of releases (settings default)

      --debug                 Enable debug mode

      -h, --help              Shows this help

In most cases the defaults can be set in the settings.ini file (examples are included in the ini file).
Providing parameters to the script will overwrite the defaults.

Example (default is to not remove data)

    php remove_blacklist_releases.php --remove
    
Available scripts
-------
<table style="wdith: 100%;">
    <tr>
        <th style="text-align:left;">Script</th>
        <th style="text-align:left;">Description</th>
    </tr>
    <tr>
        <td style="vertical-align: top; font-weight: bold;">available_groups</td>
        <td style="vertical-align: top;">
            This script will show all the available groups your usp provides.<br />
            You can search for a group using the -s/--search option. The search option accepts wildcards ("*")<br />
            A cache file will be created for faster searching (available_groups.cache).</td>
    </tr>
    <tr>
        <td style="vertical-align: top; font-weight: bold;">check_database</td>
        <td style="vertical-align: top;">
            This script will check all your database tables (only the available MyISAM tables) for errors.<br />
            If found, a repair action is started.
        </td>
    </tr>
    <tr>
        <td style="vertical-align: top; font-weight: bold;">fix_android_releases</td>
        <td style="vertical-align: top;">
            This script will try to fix the names of android releases like "v1 9 10 2-AnDrOiD".
        </td>
    </tr>
    <tr>
        <td style="vertical-align: top; font-weight: bold;">group_stats</td>
        <td style="vertical-align: top;">
            This script will display group statistics like the "Browse groups" page, but commandline and with the oldest post information.
        </td>
    </tr>
    <tr>
        <td style="vertical-align: top; font-weight: bold;">remove_blacklist_releases</td>
        <td style="vertical-align: top;">
            It may happen that scripts like "update_parsing.php" rename releases after they have been added.<br />
            Renaming a release will not trigger the black & whitelists again.<br />
            With this script you can remove releases based on your black & whitelists.<br />
            See the help (-h/--help) for more information.
        </td>
    </tr>
    <tr>
        <td style="vertical-align: top; font-weight: bold;">remove_category_releases</td>
        <td style="vertical-align: top;">
            This script will remove releases from non active categories.
        </td>
    </tr>
    <tr>
        <td style="vertical-align: top; font-weight: bold;">remove_parts_without_releases</td>
        <td style="vertical-align: top;">
            This script will remove records from the parts table which are not linked to a release.<br />
            It will also remove releases which have no parts linked to it.
        </td>
    </tr>
    <tr>
        <td style="vertical-align: top; font-weight: bold;">remove_unwanted_releases</td>
        <td style="vertical-align: top;">
            This scripts removes releases based on custom created queries.<br />
            <strong><i>READ THE INSTRUCTIONS IN THIS FILE! WRONGLY CREATED CUSTOM QUERIES CAN REMOVE THE WRONG RELEASES. ONCE REMOVED THERE IS NO WAY TO GET THE RELEASES BACK (UNLESS YOU MADE A BACKUP BEFORE)</i></strong>
        </td>
    </tr>
    <tr>
        <td style="vertical-align: top; font-weight: bold;">test_blacklist</td>
        <td style="vertical-align: top;">
            This script can be used to test a string against available black/white lists.<br />
            If matched (or not matched in case of a whitelist) the result is shown, including regex, id and if availabe what part matched.<br />
            See the help (-h/--help) for more information.
        </td>
    </tr>
    <tr>
        <td style="vertical-align: top; font-weight: bold;">update_missing_movie_info</td>
        <td style="vertical-align: top;">
            This script will update missing movie information (based on releases with missing movie info).
        </td>
    </tr>
</table>

Contributing
------------
You can always create an [issue](https://github.com/NNScripts/nn-custom-scripts/issues).<br />
<br />
Or better:<br >
1. Fork it.
2. Create and commit your changes
3. Open a [Pull Request]
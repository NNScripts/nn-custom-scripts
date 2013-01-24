nn-custom-scripts
=================

Custom NewzNab+ scripts

Disclamer
------
**Use these scripts at your own risk!<br />
I'm not responsible for loss of data or destroyed databases!**

Although these scripts are tested against a updated NewzNab+ release, it may happen that a script runs into an unexpected error.
You can help by reporting problems/errors or by forking these scripts and creating a pull-request.

Before reporting an error, please run the script with "debug" setting enabled. Provide the output and possible php/sql error's as well (make sure your php installation is configured to display errors).

Scripts
-------
**check_database.php**<br />
This script will check all your database tables for errors. If found, a repair action is started.

**group_stats.php**<br />
This script will display group statistics like the "Brose groups" page, but commandline and with the oldest post information.

**remove\_blacklist\_releases.php**<br />
This script will remove releases based on your black & whitelists.<br />
It may happen that scripts like "update\_parsing.php" rename releases after they have been added.<br />
Renaming a release will not trigger the black & whitelists again.<br />
_By default the script does not remove any releases. If you want to remove the releases, read the file documentation in the header!_

**remove\_category\_releases.php**<br />
This script will remove releases from non active categories.<br />
_By default the script does not remove any releases. If you want to remove the releases, read the file documentation in the header!_

**remove\_parts\_without\_releases.php**</br />
This script will remove parts wich are not linked to a release.
_By default the script does not remove any releases. If you want to remove the releases, read the file documentation in the header!_
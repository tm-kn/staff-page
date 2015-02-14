# Staff Page
It is a MyBB plugin, which adds a manageable page with list of staff. You can create a list of staff and group them. The plugin allows you to add description of groups and members on the page. It may be used to describe role of each member of the staff. Groups shown on the page are independent from the forum's users groups.

*By default this page replaces "Forum Team" (showteam.php) page, but the redirection can be switched off in the board settings if you wish to use both pages.*

## Installation
1. Unpack the plugin archive.
2. Upload content of "Upload" folder to your forum's root directory.
3. Go to plugins page in the ACP and install the plugin.

Page is available under *memberlist.php?action=staff* URL.

## Configuration
1. Firstly you must set administrator permissions to gain access to the plugin configuration. The permission can be found in the configuration section.
2. Now you can access plugin's configuration page. To do so, go to "Configuration" tab of ACP and choose "Staff Page" from the sidebar on the left. There you manage your staff page.

### Hiding the staff page from users
You can disallow users from accessing the staff page by setting it in the groups permissions in ACP.

### Other settings
In the board settings the plugin gives administrators possibility to set avatar size displayed on the staff page and the redirection from showteam.php can be turned off.

## Author
* [mrnu](http://github.com/mrnu) <<mrnuu@icloud.com>>

## Information
Project is in an alpha version. It hasn't been tested in production environment. Project is under the GPL v3 license.

![Screenshot from the front](Screenshots/Front 01.png)
![Screenshot from the ACP configuration page](Screenshots/ACP 02.png)

## Reporting issues
All issues can be reported on the [issues page](https://github.com/mrnu/staff-page/issues) of this repository. Feel free to do so.

# Mattermost activity for Moodle #

This plugin allows teachers to keep synchronized users enrolled in a Moodle course into a dedicated Mattermost private channel.

## Main feature
Adding this activity to a Moodle course will create a private channel in Mattermost and push Moodle users associated to this activity as members of this newly created channel. The list of members will then be kept up to date.

It will be possible to access to this Mattermost channel directly from Moodle or through any autonomous Mattermost client.

It will also create separate private channels for the groups inside the courses. The list of members inside these channels will also be kept up to date. 

## System requirements
Php 7.1 or higher

## Mattermost settings requirements
### Authentication
* Users are created in Mattermost by the Moodle plugin with their sign-in method configured as LDAP or SAML.

### Mattermost plugin setup
* Install the plugin [Mattermost plugin moodle sync](https://github.com/Brightscout/x-mattermost-plugin-moodle-sync) in Mattermost.
* Enable the plugin and configure it. The webhook secret generated in this plugin's configuration is used in Moodle to send requests.

## Installation

### Mattermost moodle sync plugin
You can follow [these](https://github.com/Brightscout/x-mattermost-plugin-moodle-sync#installation) steps to install the Mattermost moodle sync plugin in Mattermost.
### Moodle plugin
There are two ways to install the plugin - 

#### From the ZIP file
1. Go to Site Administration -> Plugins -> Install plugins.

2. You can just upload the zip file and click on `Install plugin from the ZIP file`.

#### Copying the plugin directory in the Moodle root directory
1. Copy the Mattermost plugin to the `mod` directory of your Moodle instance:

```bash
git clone https://github.com/Brightscout/mod_mattermost MOODLE_ROOT_DIRECTORY/mod/mattermost
```
2. Visit the notifications page to complete the installation process
## Settings
### Authentication settings
![image](https://user-images.githubusercontent.com/77336594/131695544-c2a446e6-29b3-4497-a8f3-2562ffc221a7.png)

* Mattermost webhook secret is the secret generated in the plugin settings of "Moodle course sync plugin" in Mattermost.
* Mattermost team slug name is the slug name for the team in which all the synchronization will take place.

![image](https://user-images.githubusercontent.com/77336594/131698797-c4b57e3e-9493-48c2-a02f-d6fa0c5e6d50.png)

* Mattermost auth service is the setting to specify the sign-in method for the new users which will be created in Mattermost.
* Mattermost auth data is the Moodle user field which will be mapped to the Mattermost user and used for authentication on Mattemrost with the respective auth service.

### Recycle bin patch
* This patch must be applied to enable features of archiving and unarchiving Mattermost channels corresponding to a Moodle course or course's groups.

#### Applying patch
* Check if this patch is already applied to core moodle file admin/tool/recyclebin/classes/course_bin.php
* Patches are available in patch subdirectory
* You can apply them with following patch command
```bash
patch -p1 /your_moodle_path/admin/tool/recyclebin/classes/course_bin.php < /your_moodle_path/mod/mattermost/patch/admin_tool_recyclebin_classes_course_bin.patch
patch -p1 /your_moodle_path/admin/tool/recyclebin/classes/category_bin.php < /your_moodle_path/mod/mattermost/patch/admin_tool_recyclebin_classes_category_bin.patch
patch -p1 /your_moodle_path/user/classes/output/user_roles_editable.php  < /your_moodle_path/mod/mattermost/patch/user_classes_output_user_roles_editable.patch

```
* Once these patches are applied check "Is recyclebin moodle core patch installed" in Mattermost plugin settings to enable this feature.

## Specials capabilities
the following capabilities define if a role is able to perform some setting in the module instance : 
* mod/mattermost:candefineroles : enable a user to change defaults roles mapping while editing the module instance

## Unit tests
### Settings
* In Mattermost, you may have to change the config to increase the maximum number of channels allowed per team and maximum number of users allowed per team if these values are very low. These are defined by variables `MaxChannelsPerTeam` and `MaxUsersPerTeam` in the Mattermost config file.
* To run unit tests, just create a config-test.php file in the module mattermost root directory.
* Fill in with following parameters. You can take a reference from `config-test_example.php` if you want.

```php
<?php
set_config('instanceurl','https://mattermost-server_url','mod_mattermost');
set_config('secret', 'secret_from_the_mattermost_plugin', 'mod_mattermost');
set_config('teamslugname', 'your_team_on_mattermost', 'mod_mattermost');
set_config('authservice', '0', 'mod_mattermost');
set_config('authdata', '0', 'mod_mattermost');
// fake config test to avoidd email domain troubles
set_config('domainmail','your_domain_mail_if_necessary','mod_mattermost'); // Optional argument.line.
```
* The `0` in `authservice` setting stands for "LDAP". Put `1` for "SAML".
* Similarly, `0` in `authdata` setting stands for "Email", Put `1` for "Username".
* There are two kinds of unit tests - 
    * Those which require a remote Mattermost server (which are located in tests/integration directory)
    * Those which do not require a remote Mattermost server (which are located in tests directory). These tests also need a config-test.php file to run but they don't affect the remote server at all. To run these tests, you can even put dummy values in config-test.php.
### Requirements
* See [moodle documentation](https://docs.moodle.org/dev/PHPUnit).

### Provided tests
* Don't forget to init phpunit moodle tests environment (which is explained in the Requirements).
* Below is an example of how to run a unit test.
```bash
cd /moodle_path
/moodle_path/vendor/bin/phpunit --filter "mod_mattermost_background_enrolments_testcase"
```
* You can also use other methods to run the tests. All the methods are explained in the Requirements section.

## License ##

2021 Brightscout (https://www.brightscout.com)

This program is free software: you can redistribute it and/or modify it under
the terms of the GNU General Public License as published by the Free Software
Foundation, either version 3 of the License, or (at your option) any later
version.

This program is distributed in the hope that it will be useful, but WITHOUT ANY
WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A
PARTICULAR PURPOSE.  See the GNU General Public License for more details.

You should have received a copy of the GNU General Public License along with
this program.  If not, see <http://www.gnu.org/licenses/>.


If you need some help, you can contact us via this email address : hello@brightscout.com

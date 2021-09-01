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
* Install the plugin "Mattermost plugin moodle sync" in Mattermost.
* Enable the plugin and configure it. The webhook secret generated in this plugin's configuration is used in Moodle to send requests.

## Installation
### Moodle plugin
1. Copy the Mattermost plugin to the `mod` directory of your Moodle instance:

```bash
git clone https://github.com/Brightscout/mod_mattermost MOODLE_ROOT_DIRECTORY/mod/mattermost
```
2. Visit the notifications page to complete the installation process
## Settings
### Authentication settings
* Mattermost webhook secret is the secret generated in the plugin settings of "Moodle course sync plugin" in Mattermost.
* Mattermost team slug name is the slug name for the team in which all the synchronization will take place.
* Mattermost auth service is the setting to specify the sign-in method for the new users which will be created in Mattermost.
* Mattermost auth data is the Moodle user field which will be mapped to the Mattermost user and used for authentication on Mattemrost with the respective auth service.
## Specials capabilities
the following capabilities define if a role is able to perform some setting in the module instance : 
* mod/mattermost:candefineroles : enable a user to change defaults roles mapping while editing the module instance
## License ##

2020 Brightscout (https://www.brightscout.com)

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

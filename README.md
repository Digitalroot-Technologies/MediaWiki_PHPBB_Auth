MediaWiki_PHPBB_Auth
====================

This extension links MediaWiki to phpBB's user table for authentication, and disallows the creation of new accounts in MediaWiki. Users must then log in to the wiki with their phpBB account.

MediaWiki Page: https://www.mediawiki.org/wiki/Extension:PHPBB_Auth

REQUIREMENTS
=================

* PHP 7.3 or later
* MySQL 5 or later
* MediaWiki 1.35 LTS or later (tested on 1.35)
* phpBB 3.3 (tested on 3.3.3 and 3.3.7)
* [PluggableAuth extension](https://www.mediawiki.org/wiki/Extension:PluggableAuth) 6.1 or later

INSTALL
=================

Install the PluggableAuth MediaWiki extension.

Extract the package contents into an `/extensions/Auth_phpBB` directory.

Open `LocalSettings.php`. Put this at the bottom of the file and edit as needed.
The values in the `$wgAuth_Config` array below represent the defaults, except
for `UseCanonicalCase` which was `false` prior to June 2016, so you only need to
use and set values that differ for your system.

```php
// phpBB User Database Plugin. (Requires MySQL Database)

$wgAuth_Config = [
    //=======================================================================
    // Required settings

    'PathToPHPBB'  => '../phpbb3/',        // Path from this file to your phpBB install
    'UserTB'       => 'phpbb3_users',      // Name of your phpBB user table
    'GroupsTB'     => 'phpbb3_groups',     // Name of your phpBB groups table
    'User_GroupTB' => 'phpbb3_user_group', // Name of your phpBB user_group table

    // Make MediaWiki usernames match the case of the phpBB usernames (except
    // with the first letter set to uppercase). Setting this to false causes
    // usernames to be all lowercase except for the first character.
    // NOTE: Before June 2016 this setting was always false, changing it to
    // true on an install where it previously was false will cause users with
    // uppercase characters to appear as separate users from their previous
    // all-lowercase account!
    'UseCanonicalCase' => true,


    //=======================================================================
    // Optional settings

    // --------------------------------------
    // Wiki Group settings

    // By default, any valid phpBB user can log in. To require the user to be
    // a member of one or more phpBB groups, set this to true.
    'UseWikiGroup'  => false,

    // phpBB group(s) the plugin checks for membership in when using
    // UseWikiGroup = true. Additional groups can be specified by adding
    // to the array: ['Wiki', 'SecondGroup']. To log in, the user must be
    // a member of at least one of them.
    'WikiGroupName' => ['Wiki'],


    // --------------------------------------
    // External database settings

    // Auth_phpBB assumes the phpBB tables are in the same database as the
    // MediaWiki tables. If phpBB is installed in a different MySQL database,
    // whether on the same or different host, set these parameters to have
    // the plugin connect to that database instead. See the config.php file
    // in your phpBB installation for the values.
    'UseExtDatabase' => false,
    'MySQL_Host'     => 'localhost',
    'MySQL_Port'     => '',
    'MySQL_Database' => '',
    'MySQL_Username' => '',
    'MySQL_Password' => '',


    // --------------------------------------
    // Alternative username mappings

    // Use a custom username profile field in phpBB to create the username for
    // the wiki. This is most helpful for phpBB users whose usernames are
    // incompatible with MediaWiki username restrictions.
    // See the Auth_phpBB README.md for more information on configuring this.
    'UseWikiProfile'   => false,

    // Name of your phpBB profile data table.
    'ProfileDataTB'    => 'phpbb3_profile_fields_data',

    // Name of your phpBB custom profile field.
    // phpBB prefixes 'pf_' to the custom field name you choose in the UI.
    // e.g., "wikiusername" becomes "pf_wikiusername"
    'ProfileFieldName' => 'pf_wikiusername',


    // --------------------------------------
    // Error messages

    // Error message to display to users on a failed login attempt.
    // Message text is formatted using wiki markup. An example with a link:
    // 'Please register on the [https://some.domain.com/phpbb forums] to login.'
    'LoginMessage' => 'Please register on the forums to login.',

    // Error message when a user is not a member of the required phpBB group
    'NoWikiError'  => 'You must be a member of the required forum group.',
];

// load the authentication extensions
wfLoadExtension( 'PluggableAuth' );
wfLoadExtension( 'Auth_phpBB' );
```

Optional Features
-----------------

### Require phpBB group membership

To restrict wiki login to certain phpBB users, create a group in phpBB, for
instance "Wiki", and assign users to it. Then update the following two
configuration settings:

```php
$wgAuth_Config['UseWikiGroup'] = true;      // Require group membership to login
$wgAuth_Config['WikiGroupName'] = ['Wiki']; // Name of your phpBB group
```

### Custom phpBB-to-MediaWiki username translation

Auth_phpBB will use the phpBB username to create the MediaWiki username.
If `UseCanonicalCase` is `true`, the MediaWiki username will match the
case of the phpBB username with the first letter capitalized.
```
someUserName => SomeUserName
```

If `UseCanonicalCase` is `false`, the MediaWiki username will be set to
the lowercased phpBB username with the first letter capitalized.
```
someUserName => Someusername
```

This is unlikely to work for all phpBB users as MediaWiki is more
restrictive on the characters within a username, such as underscores and other
special characters.

To address this, Auth_phpBB can use the value of a custom profile field in
phpBB for the MediaWiki username. Forum administrators can set the value of
the custom profile field to a form that is valid in MediaWiki. Users can use
either their phpBB or the custom profile field value to log in to the wiki.

If this feature is enabled with `UseWikiProfile`, Auth_phpBB will use the
custom profile field for the MediaWiki username if it is set, otherwise it will
fall back to using the phpBB username as described above.

To create this field, use phpBB ACP to create a custom profile field:

1. Log into the ACP
2. Select `Users and Groups`
3. Select `Custom profile fields`
4. Create a `Single text field` custom profile field
   1. Name it "wikiusername"
   2. Set "Publicly display profile field" = no
   3. Uncheck all visibility options, except check "Hide profile field"
   4. Set "Field name/title presented to the user" = "Wiki Username"
   5. Set "Field description" = "Forum username translated for wiki username restrictions"
   6. Set "Length of input box" = 20
   7. Set "Maximum number of characters" = 255
   8. Set "Field validation" to "Any character"
   9. Set Language definitions fields to same as field name/title/description above.

_Warning: The custom profile field **must be hidden** to all but the admins because users
could otherwise hijack wiki accounts by entering any username they wish._

Update `LocalSettings.php` and set the following values:
```php
$wgAuth_Config['UseWikiProfile'] = true;
// Name of your phpBB profile data table.
$wgAuth_Config['ProfileDataTB'] = 'phpbb3_profile_fields_data';
// Name of your phpBB custom profile field.
// phpBB prefixes 'pf_' to the custom field name you choose in the UI.
// e.g., "wikiusername" becomes "pf_wikiusername"
$wgAuth_Config['ProfileFieldName'] = 'pf_wikiusername';
```

Forum admins can now populate the custom profile field for a user to set their
wikiusername. For example, enter "Under Score" for a user with the name
"Under_Score" because underscores are not allowed in MediaWiki usernames.
Users with phpBB usernames which are also valid MediaWiki usernames do not
need this field set.

## Troubleshooting

To debug configuration and authentication issues, enable the debug log group
for the `Auth_phpBB` component by adding this to your `LocalSettings.php`:

```php
$wgDebugLogGroups = [
    "Auth_phpBB" => "/some/path/mw-debug-Auth_phpBB.log",
];
```

Any plugin configuration issues that result in an Exception should output an
error message to the file.

When users login the log will contain the progress of the authentication.
Here's an example of a successful login using `UseWikiProfile` and `UseWikiGroup`:

```
2022-01-27 17:09:04: authenticate: looking up phpBB account & WikiProfile for 'TestUser'
2022-01-27 17:09:04: lookupPhpBBUser: no phpBB username matched 'TestUser'
2022-01-27 17:09:04: lookupWikiProfile: found WikiProfile 'TestUser' with user_id 862
2022-01-27 17:09:04: getWikiProfileName: user_id 862 has a WikiProfile of 'TestUser'
2022-01-27 17:09:04: authenticate: attempting login for 'TestUser' as wiki user 'TestUser'
2022-01-27 17:09:05: isMemberOfWikiGroup: user_id 862 is a member of 'Wiki'
2022-01-27 17:09:05: authenticate: user 'TestUser' logged in as wiki user 'TestUser'
```

Be sure to disable `$wgDebugLogGroups` after you are done debugging!

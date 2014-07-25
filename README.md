MediaWiki_PHPBB_Auth
====================

This extension links MediaWiki to phpBB's user table for authentication, and disallows the creation of new accounts in MediaWiki. Users must then log in to the wiki with their phpBB account.

MediaWiki Page: http://www.mediawiki.org/wiki/Extension:PHPBB/Users_Integration

REQUIREMENTS
=================
This version is for phpBB3.

The extension requires PHP5, MySQL 5, MediaWiki 1.11+ and phpBB3.

INSTALL:
=================

Create a group in PHPBB for your wiki users. I named mine "Wiki". 
You will need to put the name you choose in the code below. 

NOTE: In order for a user to be able to use the wiki they will need to 
be a member of the group you made in the step above.

Put Auth_phpbb.php in /extensions/  
Put iAuthPlugin.php in /extensions/  
Put PasswordHash.php in /extensions/


Open LocalSettings.php. Put this at the bottom of the file. Edit as needed.

        /*-----------------[ Everything below this line. ]-----------------*/
        
        // PHPBB User Database Plugin. (Requires MySQL Database)
        require_once './extensions/Auth_phpbb.php';
        
        $wgAuth_Config = array(); // Clean.
        
        $wgAuth_Config['WikiGroupName'] = 'Wiki';       // Name of your PHPBB group
                                                        // users need to be a member
                                                        // of to use the wiki. (i.e. wiki)
                                                        // This can also be set to an array 
                                                        // of group names to use more then 
                                                        // one. (ie. 
                                                        // $wgAuth_Config['WikiGroupName'][] = 'Wiki';
                                                        // $wgAuth_Config['WikiGroupName'][] = 'Wiki2';
                                                        // or
                                                        // $wgAuth_Config['WikiGroupName'] = array('Wiki', 'Wiki2');
                                                        // )
        
        
        $wgAuth_Config['UseWikiGroup'] = true;          // This tells the Plugin to require
                                                        // a user to be a member of the above
                                                        // phpBB group. (ie. wiki) Setting
                                                        // this to false will let any phpBB
                                                        // user edit the wiki.
        
        $wgAuth_Config['UseExtDatabase'] = false;       // This tells the plugin that the phpBB tables
                                                        // are in a different database then the wiki.
                                                        // The default settings is false.
        
        //$wgAuth_Config['MySQL_Host']        = 'localhost';      // phpBB MySQL Host Name.
        //$wgAuth_Config['MySQL_Username']    = 'username';       // phpBB MySQL Username.
        //$wgAuth_Config['MySQL_Password']    = 'password';       // phpBB MySQL Password.
        //$wgAuth_Config['MySQL_Database']    = 'database';       // phpBB MySQL Database Name.
        
        $wgAuth_Config['UserTB']         = 'phpbb3_users';       // Name of your PHPBB user table. (i.e. phpbb_users)
        $wgAuth_Config['GroupsTB']       = 'phpbb3_groups';      // Name of your PHPBB groups table. (i.e. phpbb_groups)
        $wgAuth_Config['User_GroupTB']   = 'phpbb3_user_group';  // Name of your PHPBB user_group table. (i.e. phpbb_user_group)
        $wgAuth_Config['PathToPHPBB']    = '../phpbb3/';         // Path from this file to your phpBB install.
        
        // Local
        $wgAuth_Config['LoginMessage']   = '<b>You need a phpBB account to login.</b><br /><a href="' . $wgAuth_Config['PathToPHPBB'] .
                                           'ucp.php?mode=register">Click here to create an account.</a>'; // Localize this message.
        $wgAuth_Config['NoWikiError']    = 'You are not a member of the required phpBB group.'; // Localize this message.
        
        $wgAuth = new Auth_phpBB($wgAuth_Config);     // Auth_phpBB Plugin.

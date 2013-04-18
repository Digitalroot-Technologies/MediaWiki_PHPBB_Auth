MediaWiki_PHPBB_Auth
====================

This extension links MediaWiki to phpBB's user table for authentication, and disallows the creation of new accounts in MediaWiki. Users must then log in to the wiki with their phpBB account.

REQUIREMENTS
=================
This version is for phpBB2.

INSTALL:
=================

Create a group in PHPBB for your wiki users. I named mine "wiki". 
You will need to put the name you choose in the code below. 

NOTE: In order for a user to be able to use the wiki they will need to 
be a member of the group you made in the step above.

Put Auth_phpbb.php in /extensions/

Open LocalSettings.php. Put this at the bottom of the file. Edit as needed.

        /*-----------------[ Everything below this line. ]-----------------*/
        
        // This requires a user be logged into the wiki to make changes.
        $wgGroupPermissions['*']['edit'] = false; 
        
        // Specify who may create new accounts.
        $wgGroupPermissions['*']['createaccount'] = false; 
        
        // PHPBB User Database Plugin. (Requires MySQL Database)
        require_once './extensions/Auth_phpbb.php';
        
        $wgPHPBB_WikiGroupName  = 'wiki';               // Name of your PHPBB group
                                                        // users need to be a member
                                                        // of to use the wiki. (i.e. wiki)
        
        $wgPHPBB_UseWikiGroup   = true;                 // This tells the Plugin to require
                                                        // a user to be a member of the above
                                                        // phpBB group. (ie. wiki) Setting
                                                        // this to false will let any phpBB
                                                        // user edit the wiki.
        
        $wgPHPBB_UseExtDatabase = false;                // This tells the plugin that the phpBB tables
                                                        // are in a different database then the wiki.
                                                        // The default settings is false.
        
        /*-[NOTE: You only need the next four settings if you set $wgPHPBB_UseExtDatabase to true.]-*/
        //$wgPHPBB_MySQL_Host     = 'host';               // phpBB MySQL Host Name.
        //$wgPHPBB_MySQL_Username = 'username';           // phpBB MySQL Username.
        //$wgPHPBB_MySQL_Password = 'password';           // phpBB MySQL Password.
        //$wgPHPBB_MySQL_Database = 'database_name';      // phpBB MySQL Database Name.
        
        $wgPHPBB_UserTB         = 'phpbb_users';        // Name of your PHPBB user table. (i.e. phpbb_users)
        $wgPHPBB_GroupsTB       = 'phpbb_groups';       // Name of your PHPBB groups table. (i.e. phpbb_groups)
        $wgPHPBB_User_GroupTB   = 'phpbb_user_group';   // Name of your PHPBB user_group table. (i.e. phpbb_user_group)
        $wgAuth                 = new Auth_PHPBB();     // Auth_PHPBB Plugin.

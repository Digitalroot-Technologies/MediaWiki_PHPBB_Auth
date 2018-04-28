<?php

    /**
     * This file makes MediaWiki use a phpbb user database to
     * authenticate with. This forces users to have a PHPBB account
     * in order to log into the wiki. This can also force the user to
     * be in a group called Wiki.
     *
     * With 3.0.x release this code was rewritten to make better use of
     * objects and php5. Requires MediaWiki 1.11.x, PHPBB3 and PHP5.
     *
     * This program is free software; you can redistribute it and/or modify
     * it under the terms of the GNU General Public License as published by
     * the Free Software Foundation; either version 2 of the License, or
     * (at your option) any later version.
     *
     * This program is distributed in the hope that it will be useful,
     * but WITHOUT ANY WARRANTY; without even the implied warranty of
     * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
     * GNU General Public License for more details.
     *
     * You should have received a copy of the GNU General Public License along
     * with this program; if not, write to the Free Software Foundation, Inc.,
     * 59 Temple Place - Suite 330, Boston, MA 02111-1307, USA.
     * http://www.gnu.org/copyleft/gpl.html
     *
     * @package MediaWiki
     * @subpackage Auth_phpBB
     * @author Nicholas Dunnaway
     * @copyright 2004-2016 Digitalroot Technologies
     * @license http://www.gnu.org/copyleft/gpl.html
     * @link https://github.com/Digitalroot/MediaWiki_PHPBB_Auth
     * @link http://digitalroot.net/
     *
     */

// error_reporting(E_ALL); // Debug

// First check if class and interface has already been defined.
if (!class_exists('AuthPlugin') || !interface_exists('iAuthPlugin'))
{
    /**
     * Auth Plug-in
     *
     */
    require_once './includes/AuthPlugin.php';

    /**
     * Auth Plug-in Interface
     *
     */
    require_once './extensions/Auth_phpBB/iAuthPlugin.php';

}

// First check if the PasswordHash class has already been defined.
if (!class_exists('PasswordHash'))
{
    /**
     * PasswordHash Class
     *
     * Portable PHP password hashing framework.
     *
     * Written by Solar Designer <solar at openwall.com> in 2004-2006
     * and placed in the public domain.
     *
     * The homepage URL for this framework is:
     *      http://www.openwall.com/phpass/
     *
     */
    require_once './extensions/Auth_phpBB/PasswordHash.php';
}

/**
 * Handles the Authentication with the PHPBB database.
 *
 */
class Auth_phpBB extends AuthPlugin implements iAuthPlugin
{

    /**
     * Database Collation (Only change this if your know what to change it to)
     *
     * @var string
     */
    private $_DB_Collation;

    /**
     * This turns on and off printing of debug information to the screen.
     *
     * @var bool
     */
    private $_debug = false;

    /**
     * Name of your PHPBB groups table. (i.e. phpbb_groups)
     *
     * @var string
     */
    private $_GroupsTB;

    /**
     * Message user sees when logging in.
     *
     * @var string
     */
    private $_LoginMessage;

    /**
     * phpBB MySQL Database Name.
     *
     * @var string
     */
    private $_MySQL_Database;

    /**
     * phpBB MySQL Host Name.
     *
     * @var string
     */
    private $_MySQL_Host;

    /**
     * phpBB MySQL Port Number.
     *
     * @var string
     */
    private $_MySQL_Port;

    /**
     * phpBB MySQL Password.
     *
     * @var string
     */
    private $_MySQL_Password;

    /**
     * phpBB MySQL Username.
     *
     * @var string
     */
    private $_MySQL_Username;

    /**
     * Version of MySQL Database.
     *
     * @var string
     */
    private $_MySQL_Version;

    /**
     * Text user sees when they login and are not a member of the wiki group.
     *
     * @var string
     */
    private $_NoWikiError;

    /**
     * Path to the phpBB install.
     *
     * @var string
     */
    private $_PathToPHPBB;

    /**
     * URL to the phpBB install. Needed if the forums are hosted on a different sub-domain
     *
     * @var string
     */
    private $_URLToPHPBB;

    /**
     * Name of the phpBB session table for single session sign-on.
     *
     * @var string
     */
    private $_SessionTB;

    /**
     * This tells the plugin that the phpBB tables
     * are in a different database then the wiki.
     * The default settings is false.
     *
     * @var bool
     */
    private $_UseExtDatabase;

    /**
     * Name of your PHPBB groups table. (i.e. phpbb_groups)
     *
     * @var string
     */
    private $_User_GroupTB;

    /**
     * UserID of our current user.
     *
     * @var int
     */
    private $_UserID;

    /**
     * Name of your PHPBB user table. (i.e. phpbb_users)
     *
     * @var string
     */
    private $_UserTB;

    /**
     * This tells the Plugin to require
     * a user to be a member of the above
     * phpBB group. (ie. wiki) Setting
     * this to false will let any phpBB
     * user edit the wiki.
     *
     * @var bool
     */
    private $_UseWikiGroup;

    /**
     * Name of your PHPBB group
     * users need to be a member
     * of to use the wiki. (i.e. wiki)
     *
     * @var mixed
     */
    private $_WikiGroupName;

    /**
     * Whether to set usernames from the
     * username_clean (false) colum or username
     * (true) column in the phpbb users tables.
     * Should be true in most cases, is added for
     * legacy support due to previous versions
     * setting usernames to lowercase.
     *
     * @var bool
     */
    private $_UseCanonicalCase;

    /**
     *
     * Begin class instance members used For Custom Profile Field feature
     * (JWPlatt@OpenUru.org)
     *
     */

    /**
     * This tells the Plugin to use a phpBB
     * profile entry for the wiki user
     * name lookup if login fails.  This
     * allows for phpBB/wiki incompatible
     * usernames to be resolved.
     *
     * @var bool
     */
    private $_UseWikiProfile;

    /**
     * Name of your PHPBB profile data table
     *
     * @var string
     */
    private $_ProfileDataTB;

    /**
     * Name of profile field in the
     * profile data table to use for
     * wikified username lookup
     *
     * @var string
     */
    private $_ProfileFieldName;

    /**
     * Class member used to cache canonical name lookup
     *
     * @var string
     */
    private $_wikiUserName;

    /**
     * Class member used to cache canonical name lookup
     *
     * @var string
     */
    private $_phpBBUserName;

    /**
     * Constructor
     *
     * @param array $aConfig
     */

    function __construct($aConfig)
    {
        // Set some values phpBB needs.
        define('IN_PHPBB', true); // We are secure.

        // Read config
        $this->_GroupsTB        = $aConfig['GroupsTB'];
        $this->_NoWikiError     = $aConfig['NoWikiError'];
        $this->_PathToPHPBB     = $aConfig['PathToPHPBB'];
        $this->_SessionTB       = @$aConfig['SessionTB'];
        $this->_UseExtDatabase  = $aConfig['UseExtDatabase'];
        $this->_User_GroupTB    = $aConfig['User_GroupTB'];
        $this->_UserTB          = $aConfig['UserTB'];
        $this->_UseWikiGroup    = $aConfig['UseWikiGroup'];
        $this->_WikiGroupName   = $aConfig['WikiGroupName'];
        $this->_LoginMessage    = $aConfig['LoginMessage'];

        // If undefined (i.e. user is using an old config) set to false
        if (isset($aConfig['UseCanonicalCase'])) {
            $this->_UseCanonicalCase = $aConfig['UseCanonicalCase'];
        } else {
            $this->_UseCanonicalCase = false;
        }

        // If undefined (i.e. user is using an old config) set to false
        if (isset($aConfig['UseWikiProfile'])) {
            $this->_UseWikiProfile   = $aConfig['UseWikiProfile']; // Allow phpBB-to-wiki username translation
            $this->_ProfileDataTB    = $aConfig['ProfileDataTB']; // phpBB profile field data table
            $this->_ProfileFieldName = $aConfig['ProfileFieldName']; // phpBB custom profile field name
        } else {
            $this->_UseWikiProfile = false;
        }

        // Only assign the database values if an external database is used.
        if ($this->_UseExtDatabase == true)
        {
            $this->_MySQL_Database  = $aConfig['MySQL_Database'];
            $this->_MySQL_Host      = $aConfig['MySQL_Host'];
            $this->_MySQL_Password  = $aConfig['MySQL_Password'];
            $this->_MySQL_Username  = $aConfig['MySQL_Username'];
        }

        // If undefined (i.e. user is using an old config) set to empty
        if (isset($aConfig['MySQL_Port'])) {
            $this->_MySQL_Port = $aConfig['MySQL_Port']; // Facilitate easy port declaration
        } else {
            $this->_MySQL_Port = '';
        }

        // Set some MediaWiki Values
        // This requires a user be logged into the wiki to make changes.
        $GLOBALS['wgGroupPermissions']['*']['edit'] = false;

        // Specify who may create new accounts:
        $GLOBALS['wgGroupPermissions']['*']['createaccount'] = false;
        $GLOBALS['wgGroupPermissions']['*']['autocreateaccount'] = true;

        // Load Hooks
        $GLOBALS['wgHooks']['UserLoginForm'][]      = array($this, 'onUserLoginForm', false);
        $GLOBALS['wgHooks']['UserLoginComplete'][]  = $this;
        $GLOBALS['wgHooks']['UserLogout'][]         = $this;
    }


    /**
     * Allows the printing of the object.
     *
     */
    public function __toString()
    {
        echo '<pre>';
        print_r($this);
        echo '</pre>';
    }


    /**
     * Add a user to the external authentication database.
     * Return true if successful.
     *
     * NOTE: We are not allowed to add users to phpBB from the
     * wiki so this always returns false.
     *
     * @param User $user - only the name should be assumed valid at this point
     * @param string $password
     * @param string $email
     * @param string $realname
     * @return bool
     * @access public
     */
    public function addUser( $user, $password, $email='', $realname='' )
    {
        return false;
    }


    /**
     * Can users change their passwords?
     *
     * @return bool
     */
    public function allowPasswordChange()
    {
        return true;
    }


    /**
     * Check if a username+password pair is a valid login.
     * The name will be normalized to MediaWiki's requirements, so
     * you might need to munge it (for instance, for lowercase initial
     * letters).
     *
     * @param string $username
     * @param string $password
     * @return bool
     * @access public
     * @todo Check if the password is being changed when it contains a slash or an escape char.
     */
    public function authenticate($username, $password)
    {
        // Connect to the database.
        $fresMySQLConnection = $this->connect();

        $username = $this->_phpBBUserName; // Override

        // Check Database for username and password.
        $fstrMySQLQuery = sprintf("SELECT `user_id`, `username_clean`, `user_password`
                FROM `%s`
                WHERE `username_clean` = ? AND `user_type` != 1
                LIMIT 1", $this->_UserTB);

        // Query Database.
        $fresStatement = $fresMySQLConnection->prepare($fstrMySQLQuery);
        $fresStatement->bind_param('s', $username);
        $fresStatement->execute();

        // Bind results
        $fresStatement->bind_result($resultUserID, $resultUsernameClean, $resultUserPassword);

        while($fresStatement->fetch())
        {
            // Use new phpass class
            $PasswordHasher = new PasswordHash(8, TRUE);

            // Print the hash of the password entered by the user and the
            // password hash from the database to the screen.
            // While this will display its not effective anymore.
            if ($this->_debug)
            {
                //print md5($password) . ':' . $faryMySQLResult['user_password'] . '<br />'; // Debug
                print $PasswordHasher->HashPassword($password) . ':' . $resultUserPassword . '<br />'; // Debug
            }

            /**
             * Check if password submited matches the PHPBB password.
             * Also check if user is a member of the phpbb group 'wiki'.
             */
            if ($PasswordHasher->CheckPassword($password, $resultUserPassword) && $this->isMemberOfWikiGroup($username))
            {
                $this->_UserID = $resultUserID;
                return true;
            }
        }
        return false;
    }


    /**
     * Return true if the wiki should create a new local account automatically
     * when asked to login a user who doesn't exist locally but does in the
     * external auth database.
     *
     * If you don't automatically create accounts, you must still create
     * accounts in some way. It's not possible to authenticate without
     * a local account.
     *
     * This is just a question, and shouldn't perform any actions.
     *
     * NOTE: I have set this to true to allow the wiki to create accounts.
     *       Without an accout in the wiki database a user will never be
     *       able to login and use the wiki. I think the password does not
     *       matter as long as authenticate() returns true.
     *
     * @return bool
     * @access public
     */
    public function autoCreate()
    {
        return true;
    }


    /**
     * Check to see if external accounts can be created.
     * Return true if external accounts can be created.
     *
     * NOTE: We are not allowed to add users to phpBB from the
     * wiki so this always returns false.
     *
     * @return bool
     * @access public
     */
    public function canCreateAccounts()
    {
        return false;
    }


    /**
     * Connect to the database. All of these settings are from the
     * LocalSettings.php file. This assumes that the PHPBB uses the same
     * database/server as the wiki.
     *
     * {@source }
     * @return resource
     */
    private function connect()
    {
        // Check if the phpBB tables are in a different database then the Wiki.
        if ($this->_UseExtDatabase == true)
        {
            // Use specified port if one given
            $dbHostAddr = ($this->_MySQL_Port == '' ? $this->_MySQL_Host : $this->_MySQL_Host . ':' . $this->_MySQL_Port);

            // Connect to database. I supress the error here.
            $fresMySQLConnection = new mysqli($dbHostAddr, $this->_MySQL_Username,
                $this->_MySQL_Password, $this->_MySQL_Database);

            // Check if we are connected to the database.
            if ($fresMySQLConnection->connect_errno > 0)
            {
                $this->mySQLError('There was a problem when connecting to the phpBB database.<br />' .
                    'Check your Host, Username, and Password settings.<br />');
            }
        }
        else
        {
            // Connect to database.
            $fresMySQLConnection = new mysqli($GLOBALS['wgDBserver'], $GLOBALS['wgDBuser'],
                $GLOBALS['wgDBpassword'], $GLOBALS['wgDBname']);

            // Check if we are connected to the database.
            if ($fresMySQLConnection->connect_errno > 0)
            {
                $this->mySQLError('There was a problem when connecting to the phpBB database.<br />' .
                    'Check your Host, Username, and Password settings.<br />');
            }
        }

        $this->_MySQL_Version = substr($fresMySQLConnection->server_info, 0, 3); // Get the mysql version.

        // This is so utf8 usernames work. Needed for MySQL 4.1
        $fresStatement = $fresMySQLConnection->prepare("SET NAMES 'utf8'");
        $fresStatement->execute();

        return $fresMySQLConnection;
    }


    /**
     * This turns on debugging
     *
     */
    public function EnableDebug()
    {
        $this->_debug = true;
        return;
    }


    /**
     * If you want to munge the case of an account name before the final
     * check, now is your chance.
     *
     * @return string
     */
    public function getCanonicalName( $username )
    {
        if (filter_var($username, FILTER_VALIDATE_IP))
        {
          return ''; // Discard IP address (anonymouse users)
        }

        // Connect to the database.
        $fresMySQLConnection = $this->connect();

        $this->_wikiUserName = $username; // Preserve and cache user's wikified username
        $this->_phpBBUserName = $this->utf8($username); // Convert to UTF8 and cache for later use

        // Check Database for username. We will return the correct casing of the name.
        $fstrMySQLQuery = sprintf("SELECT `%s`
                FROM `%s`
                WHERE `username_clean` = ?
                LIMIT 1", ($this->_UseCanonicalCase ? "username" : "username_clean"), $this->_UserTB);

        // Query Database.
        $fresStatement = $fresMySQLConnection->prepare($fstrMySQLQuery);
        $fresStatement->bind_param('s', $this->_phpBBUserName); // bind_param escapes the string
        $fresStatement->execute();

        // Bind result
        $fresStatement->bind_result($resultWikiUsername);

        while($fresStatement->fetch())
        {
            $this->_wikiUserName = ucfirst($resultWikiUsername); // Preserve capped phpBB username when wikified version is valid
            return $this->_wikiUserName;
        }

        // If here, username is invalid or is incompatible with wiki username.
        // Maybe check phpBB custom profile for translated username.

        // Check whether to use a phpBB custom profile field for a valid wiki username
        if (isset($this->_UseWikiProfile) && $this->_UseWikiProfile === false)
        {
            return $username; // Just return invalid username
        }

        // Check Database for wikiusername. We will return the wikified version of username.
        $fstrMySQLQuery = sprintf("SELECT `username_clean`, `%3\$s`
                FROM `%2\$s`, `%1\$s`
                WHERE lcase(`%3\$s`) = lcase(?)
                AND `%2\$s`.`user_id` = `%1\$s`.`user_id`
                LIMIT 1",
                $this->_UserTB,
                $this->_ProfileDataTB,
                $this->_ProfileFieldName);

        // Query Database.
        $fresStatement = $fresMySQLConnection->prepare($fstrMySQLQuery);
        $fresStatement->bind_param('s', $this->_wikiUserName);
        $fresStatement->execute();

        // Bind result
        $fresStatement->bind_result($resultphpBBUsername, $resultWikiUsername);

        while($fresStatement->fetch())
        {
         $this->_phpBBUserName = $resultphpBBUsername;
         $this->_wikiUserName = ucfirst($resultWikiUsername); // Save wikified username (cap first letter)
         return $this->_wikiUserName;
        }

        // At this point the username is invalid and should return just as it was passed.
        return $username;
    }


    /**
     * When creating a user account, optionally fill in preferences and such.
     * For instance, you might pull the email address or real name from the
     * external user database.
     *
     * The User object is passed by reference so it can be modified; don't
     * forget the & on your function declaration.
     *
     * NOTE: This gets the email address from PHPBB for the wiki account.
     *
     * @param User $user
     * @param $autocreate bool True if user is being autocreated on login
     * @access public
     */
    public function initUser( &$user, $autocreate=false )
    {
        // Connect to the database.
        $fresMySQLConnection = $this->connect();

        $username = $this->_phpBBUserName; // Override

        // Check Database for username and email address.
        $fstrMySQLQuery = sprintf("SELECT `user_email`
                FROM `%s`
                WHERE `username_clean` = ?
                LIMIT 1", $this->_UserTB);

        // Query Database.
        $fresStatement = $fresMySQLConnection->prepare($fstrMySQLQuery);
        $fresStatement->bind_param('s', $username); // bind_param escapes the string
        $fresStatement->execute();

        // Bind result
        $fresStatement->bind_result($resultUserEmail);

        while($fresStatement->fetch())
        {
            $user->mEmail       = $resultUserEmail; // Set Email Address.
            $user->mRealName    = 'I need to Update My Profile';  // Set Real Name.
        }
    }


    /**
     * Checks if the user is a member of the PHPBB group called wiki.
     *
     * @param string $username
     * @access public
     * @return bool
     * @todo Remove 2nd connection to database. For function isMemberOfWikiGroup()
     *
     */
    private function isMemberOfWikiGroup($username)
    {
        // In LocalSettings.php you can control if being a member of a wiki
        // is required or not.
        if (isset($this->_UseWikiGroup) && $this->_UseWikiGroup === false)
        {
            return true;
        }

        // Connect to the database.
        $fresMySQLConnection = $this->connect();
        $username = $this->_phpBBUserName; // Override

        // If not an array make this an array.
        if (!is_array($this->_WikiGroupName))
        {
            $this->_WikiGroupName = array($this->_WikiGroupName);
        }

        foreach ($this->_WikiGroupName as $WikiGrpName)
        {
            /**
             *  This is a great query. It takes the username and gets the userid. Then
             *  it gets the group_id number of the the Wiki group. Last it checks if the
             *  userid and groupid are matched up. (The user is in the wiki group.)
             *
             *  Last it returns TRUE or FALSE on if the user is in the wiki group.
             */

            // Get UserId
            $fstrMySQLQuery = sprintf("SELECT `user_id` FROM `%s`
                    WHERE `username_clean` = ?",
                $this->_UserTB);
            $fresStatement = $fresMySQLConnection->prepare($fstrMySQLQuery);
            $fresStatement->bind_param('s', $username); // bind_param escapes the string
            $fresStatement->execute();
            $fresStatement->bind_result($resultUserID);
            $user_id = -1;
            while ($fresStatement->fetch())
            {
                $user_id = $resultUserID;
            }

            // Get WikiId
            $fstrMySQLQuery = sprintf('SELECT `group_id` FROM `%s`
                    WHERE `group_name` = \'%s\'',
                $this->_GroupsTB, $WikiGrpName);
            $fresStatement = $fresMySQLConnection->prepare($fstrMySQLQuery);
            $fresStatement->execute();
            $fresStatement->bind_result($resultGroupID);

            $group_id = -1;
            while ($fresStatement->fetch())
            {
                $group_id = $resultGroupID;
            }

            // Check UserId and WikiId
            $fstrMySQLQuery = sprintf("SELECT COUNT( * ) FROM `%s`
                    WHERE `user_id` = ? AND `group_id` = ? and `user_pending` = 0",
                $this->_User_GroupTB);

            $fresStatement = $fresMySQLConnection->prepare($fstrMySQLQuery);
            $fresStatement->bind_param('ii', $user_id, $group_id);
            $fresStatement->execute();

            // Bind result
            $fresStatement->bind_result($result);

            // Check for a true or false response.
            while($fresStatement->fetch())
            {
                if ($result == '1')
                {
                    return true; // User is in Wiki group.
                }
            }
        }
        // Hook error message.
        $GLOBALS['wgHooks']['UserLoginForm'][] = array($this, 'onUserLoginForm', $this->_NoWikiError);
        return false; // User is not in Wiki group.
    }


    /**
     * This loads the phpBB files that are needed.
     *
     */
    private function loadPHPFiles($FileSet)
    {
        $GLOBALS['phpbb_root_path'] = rtrim($this->_PathToPHPBB, '/') . '/'; // Path to phpBB
        $GLOBALS['phpEx']           = substr(strrchr(__FILE__, '.'), 1); // File Ext.

        // Check that path is valid.
        if (!is_dir($this->_PathToPHPBB))
        {
            throw new Exception('Unable to find phpBB installed at (' . $this->_PathToPHPBB . ').');
        }

        switch ($FileSet)
        {
            case 'UTF8':
                // Check for UTF file.
                $utfToolsPath = rtrim($this->_PathToPHPBB, '/') . '/includes/utf/utf_tools.php';
                $autoloadPath = rtrim($this->_PathToPHPBB, '/') . '/vendor/autoload.php';

                if (!is_file($utfToolsPath))
                {
                    throw new Exception('Unable to find phpbb\'s utf_tools.php file at (' . $utfToolsPath . '). Please check that phpBB is installed.');
                }

                // We need the composer autoloader because phpBB 3.2+ uses patchwork/utf.
                if (!is_file($autoloadPath))
                {
                    throw new Exception('Unable to find phpbb\'s autoload.php file at (' . $autoloadPath . '). Please check that phpBB is installed.');
                }

                // Load the phpBB file.
                require_once $autoloadPath;
                require_once $utfToolsPath;
                break;

            case 'phpBBLogin':
                break;
            case 'phpBBLogout':
                break;
        }
    }


    /**
     * Modify options in the login template.
     *
     * NOTE: Turned off some Template stuff here. Anyone who knows where
     * to find all the template options please let me know. I was only able
     * to find a few.
     *
     * @param UserLoginTemplate $template
     * @param $type String:  'signup' or 'login' (added in 1.16).
     * @access public
     */
    public function modifyUITemplate( &$template, &$type )
    {
        if ($type == 'login')
        {
            $template->set('usedomain',   false); // We do not want a domain name.
            $template->set('create',      false); // Remove option to create new accounts from the wiki.
            $template->set('useemail',    false); // Disable the mail new password box.
        }
    }


    /**
     * This prints an error when a MySQL error is found.
     *
     * @param string $message
     * @access public
     */
    private function mySQLError( $message )
    {
        throw new Exception('MySQL error: ' . $message . '<br /><br />');
    }


    /**
     * This is the hook that runs when a user logs in. This is where the
     * code to auto log-in a user to phpBB should go.
     *
     * Note: Right now it does nothing,
     *
     * @param object $user
     * @return bool
     */
    public function onUserLoginComplete(&$user)
    {
        // @ToDo: Add code here to auto log into the forum.
        return true;
    }


    /**
     * Here we add some text to the login screen telling the user
     * they need a phpBB account to login to the wiki.
     *
     * Note: This is a hook.
     *
     * @param string $errorMessage
     * @param object $template
     * @return bool
     */
    public function onUserLoginForm($errorMessage = false, $template)
    {
        $template->data['link'] = $this->_LoginMessage;

        // If there is an error message display it.
        if ($errorMessage)
        {
            $template->data['message'] = $errorMessage;
            $template->data['messagetype'] = 'error';
        }
        return true;
    }


    /**
     * This is the Hook that gets called when a user logs out.
     *
     * @param object $user
     */
    public function onUserLogout(&$user)
    {
        // User logs out of the wiki we want to log them out of the form too.
        if (!isset($this->_SessionTB))
        {
            return true; // If the value is not set just return true and move on.
        }
        return true;
        // @todo: Add code here to delete the session.
    }


    /**
     * Set the domain this plugin is supposed to use when authenticating.
     *
     * NOTE: We do not use this.
     *
     * @param string $domain
     * @access public
     */
    public function setDomain( $domain )
    {
        $this->domain = $domain;
    }


    /**
     * Set the given password in the authentication database.
     * As a special case, the password may be set to null to request
     * locking the password to an unusable value, with the expectation
     * that it will be set later through a mail reset or other method.
     *
     * Return true if successful.
     *
     * NOTE: We only allow the user to change their password via phpBB.
     *
     * @param $user User object.
     * @param $password String: password.
     * @return bool
     * @access public
     */
    public function setPassword( $user, $password )
    {
        return true;
    }


    /**
     * Return true to prevent logins that don't authenticate here from being
     * checked against the local database's password fields.
     *
     * This is just a question, and shouldn't perform any actions.
     *
     * Note: This forces a user to pass Authentication with the above
     *       function authenticate(). So if a user changes their PHPBB
     *       password, their old one will not work to log into the wiki.
     *       Wiki does not have a way to update it's password when PHPBB
     *       does. This however does not matter.
     *
     * @return bool
     * @access public
     */
    public function strict()
    {
        return true;
    }


    /**
     * Update user information in the external authentication database.
     * Return true if successful.
     *
     * @param $user User object.
     * @return bool
     * @access public
     */
    public function updateExternalDB( $user )
    {
        return true;
    }


    /**
     * When a user logs in, optionally fill in preferences and such.
     * For instance, you might pull the email address or real name from the
     * external user database.
     *
     * The User object is passed by reference so it can be modified; don't
     * forget the & on your function declaration.
     *
     * NOTE: Not useing right now.
     *
     * @param User $user
     * @access public
     * @return bool
     */
    public function updateUser( &$user )
    {
        return true;
    }


    /**
     * Check whether there exists a user account with the given name.
     * The name will be normalized to MediaWiki's requirements, so
     * you might need to munge it (for instance, for lowercase initial
     * letters).
     *
     * NOTE: MediaWiki checks its database for the username. If it has
     *       no record of the username it then asks. "Is this really a
     *       valid username?" If not then MediaWiki fails Authentication.
     *
     * @param string $username
     * @return bool
     * @access public
     */
    public function userExists($username)
    {

        // Connect to the database.
        $fresMySQLConnection = $this->connect();

        // If debug is on print the username entered by the user and the one from the datebase to the screen.
        if ($this->_debug)
        {
            print $username . ' : ' . $this->utf8($username); // Debug
        }

        $username = $this->_phpBBUserName; // Override

        // Check Database for username.
        $fstrMySQLQuery = sprintf("SELECT `username_clean`
                FROM `%s`
                WHERE `username_clean` = ?
                LIMIT 1", $this->_UserTB);

        // Query Database.
        $fresStatement = $fresMySQLConnection->prepare($fstrMySQLQuery);
        $fresStatement->bind_param('s', $username);
        $fresStatement->execute();

        // Bind result
        $fresStatement->bind_result($resultUsernameClean);

        while($fresStatement->fetch())
        {

            // If debug is on print the username entered by the user and the one from the datebase to the screen.
            if ($this->_debug)
            {
                print $username . ' : ' . $resultUsernameClean; // Debug
            }

            // Double check match.
            if ($username == $resultUsernameClean)
            {
                return true; // Pass
            }
        }
        return false; // Fail
    }


    /**
     * Cleans a username using PHPBB functions
     *
     * @param string $username
     * @return string
     */
    private function utf8($username)
    {
        $this->loadPHPFiles('UTF8'); // Load files needed to clean username.
        error_reporting(E_ALL ^ E_NOTICE ^ E_STRICT); // remove notices because phpBB does not use include once, strict to address PHP 5.4 issue.
        $username = utf8_clean_string($username);
        error_reporting(E_ALL);
        return $username;
    }


    /**
     * Check to see if the specific domain is a valid domain.
     *
     * @param string $domain
     * @return bool
     * @access public
     */
    public function validDomain( $domain )
    {
        return true;
    }
}

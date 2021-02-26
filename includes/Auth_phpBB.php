<?php

/**
 * This file makes MediaWiki use a phpbb user database to
 * authenticate with. This forces users to have a PHPBB account
 * in order to log into the wiki. This can also force the user to
 * be in a group called Wiki.
 *
 * With 4.0.x release this code was rewritten to make better use of
 * objects and php7. Works with MediaWiki 1.31.x (LTS), PHPBB3.3.x and PHP7.4.
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
 * @author Steve Gilvarry
 * @author Jonathan W. Platt
 * @author C4K3
 * @author Joel Haasnoot
 * @author Casey Peel 
 * @copyright 2004-2021 Digitalroot Technologies
 * @license http://www.gnu.org/copyleft/gpl.html
 * @link https://github.com/Digitalroot/MediaWiki_PHPBB_Auth
 * @link http://digitalroot.net/
 *
 */

use MediaWiki\Auth\AuthManager;
use MediaWiki\MediaWikiServices;

/**
 * Class Auth_phpBB
 * @author  Nicholas Dunnaway, Steve Gilvarry, Jonathan W. Platt, C4K3
 *          Joel Haasnoot, Casey Peel
 * @package MediaWiki
 * @subpackage Auth_PHPBB
 */
class Auth_phpBB extends PluggableAuth {

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
     * Class member used to store login error message for login form hook
     *
     * @var string
     */
    private $_loginErrorMessage = '';

    /**
     * Initialize object configuration
     *
     */
    function initialize_config()
    {
        $aConfig = $GLOBALS['wgAuth_Config'];

        // Set some values phpBB needs.
        define('IN_PHPBB', true); // We are secure.

        // Read config
        $this->_GroupsTB = $aConfig['GroupsTB'];
        $this->_NoWikiError = $aConfig['NoWikiError'];
        $this->_PathToPHPBB = $aConfig['PathToPHPBB'];
        $this->_SessionTB = @$aConfig['SessionTB'];
        $this->_UseExtDatabase = $aConfig['UseExtDatabase'];
        $this->_User_GroupTB = $aConfig['User_GroupTB'];
        $this->_UserTB = $aConfig['UserTB'];
        $this->_UseWikiGroup = $aConfig['UseWikiGroup'];
        $this->_WikiGroupName = $aConfig['WikiGroupName'];
        $this->_LoginMessage = $aConfig['LoginMessage'];

        // If undefined (i.e. user is using an old config) set to false
        if (isset($aConfig['UseCanonicalCase'])) {
            $this->_UseCanonicalCase = $aConfig['UseCanonicalCase'];
        } else {
            $this->_UseCanonicalCase = false;
        }

        // If undefined (i.e. user is using an old config) set to false
        if (isset($aConfig['UseWikiProfile'])) {
            $this->_UseWikiProfile = $aConfig['UseWikiProfile']; // Allow phpBB-to-wiki username translation
            $this->_ProfileDataTB = $aConfig['ProfileDataTB']; // phpBB profile field data table
            $this->_ProfileFieldName = $aConfig['ProfileFieldName']; // phpBB custom profile field name
        } else {
            $this->_UseWikiProfile = false;
        }

        // Only assign the database values if an external database is used.
        if ($this->_UseExtDatabase == true) {
            $this->_MySQL_Database = $aConfig['MySQL_Database'];
            $this->_MySQL_Host = $aConfig['MySQL_Host'];
            $this->_MySQL_Password = $aConfig['MySQL_Password'];
            $this->_MySQL_Username = $aConfig['MySQL_Username'];
        }

        // If undefined (i.e. user is using an old config) set to empty
        if (isset($aConfig['MySQL_Port'])) {
            $this->_MySQL_Port = $aConfig['MySQL_Port']; // Facilitate easy port declaration
        } else {
            $this->_MySQL_Port = '';
        }
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
     * Authenticate user
     * [required by PluggableAuth]
     *
     * @param int &$id
     * @param string &$username
     * @param string &$realname
     * @param string &$email
     * @param string &$errorMessage
     * @return bool true if user is authenticated, false otherwise
     */
    public function authenticate( &$id, &$username, &$realname, &$email, &$errorMessage )
    {
        $this->initialize_config();

        $authManager = $this->getAuthManager();
        $extraLoginFields = $authManager->getAuthenticationSessionData(
            PluggableAuthLogin::EXTRALOGINFIELDS_SESSION_KEY
        );

        $username = $extraLoginFields[ExtraLoginFields::USERNAME];
        $password = $extraLoginFields[ExtraLoginFields::PASSWORD];

        $phpBBUserName = $this->phpbb_clean_username($username);

        // Connect to the database.
        $fresMySQLConnection = $this->connect();

        // Check Database for username and password.
        $fstrMySQLQuery = sprintf("SELECT `user_id`, `username_clean`, `user_password`, `user_email`
                FROM `%s`
                WHERE `username_clean` = ? AND `user_type` != 1
                LIMIT 1", $this->_UserTB);

        // Query Database.
        $fresStatement = $fresMySQLConnection->prepare($fstrMySQLQuery);
        $fresStatement->bind_param('s', $phpBBUserName);
        $fresStatement->execute();

        // Bind results
        $fresStatement->bind_result($resultUserID, $resultUsernameClean, $resultUserPassword, $resultUserEmail);

        while ($fresStatement->fetch()) {
            $this->loadPHPFiles('Password');

            $passwords_manager = $this->buildPhpBBPasswordmanager();

            /**
             * Check if password submited matches the PHPBB password.
             * Also check if user is a member of the phpbb group 'wiki'.
             */
            if ($passwords_manager->check($password, $resultUserPassword) && $this->isMemberOfWikiGroup($phpBBUserName)) {
                $username = $this->getCanonicalName($phpBBUserName);
                $realname = 'I need to Update My Profile';
                $email = $resultUserEmail;
                $id = $resultUserID;
                return true;
            }
        }
        $errorMessage = $this->_LoginMessage;
        return false;
    }


    /**
     * Save extra attributes after a user has successfully logged in.
     * [required by PluggableAuth but unused by us]
     *
     * @param int $id user id
     */
    public function saveExtraAttributes( $id )
    {
        // nothing to do
    }


    /**
     * Called upon user logout.
     * [required by PluggableAuth but unused by us]
     *
     * @param User &$user
     */
    public function deauthenticate( User &$user )
    {
        // nothing to do
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
        if ($this->_UseExtDatabase == true) {
            // Use specified port if one given
            $dbHostAddr = ($this->_MySQL_Port == '' ? $this->_MySQL_Host : $this->_MySQL_Host . ':' . $this->_MySQL_Port);

            // Connect to database. I supress the error here.
            $fresMySQLConnection = new mysqli($dbHostAddr, $this->_MySQL_Username,
                $this->_MySQL_Password, $this->_MySQL_Database);

            // Check if we are connected to the database.
            if ($fresMySQLConnection->connect_errno > 0) {
                $this->mySQLError('There was a problem when connecting to the phpBB database.<br />' .
                    'Check your Host, Username, and Password settings.<br />');
            }
        } else {
            // Connect to database.
            $fresMySQLConnection = new mysqli($GLOBALS['wgDBserver'], $GLOBALS['wgDBuser'],
                $GLOBALS['wgDBpassword'], $GLOBALS['wgDBname']);

            // Check if we are connected to the database.
            if ($fresMySQLConnection->connect_errno > 0) {
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
     * Return a MediaWiki username from a phpBB username
     *
     * @param string $username phpBB username
     * @return string
     */
    public function getCanonicalName($username)
    {
        // Connect to the database.
        $fresMySQLConnection = $this->connect();

        $phpBBUserName = $this->phpbb_clean_username($username);

        // Check Database for username. We will return the correct casing of the name.
        $fstrMySQLQuery = sprintf("SELECT `%s`
                FROM `%s`
                WHERE `username_clean` = ?
                LIMIT 1", ($this->_UseCanonicalCase ? "username" : "username_clean"), $this->_UserTB);

        // Query Database.
        $fresStatement = $fresMySQLConnection->prepare($fstrMySQLQuery);
        $fresStatement->bind_param('s', $phpBBUserName); // bind_param escapes the string
        $fresStatement->execute();

        // Bind result
        $fresStatement->bind_result($resultWikiUsername);

        while ($fresStatement->fetch()) {
            return ucfirst($resultWikiUsername); // Preserve capped phpBB username when wikified version is valid
        }

        // If here, username is invalid or is incompatible with wiki username.
        // Maybe check phpBB custom profile for translated username.

        // Check whether to use a phpBB custom profile field for a valid wiki username
        if (isset($this->_UseWikiProfile) && $this->_UseWikiProfile === false) {
            return $username; // Just return invalid username
        }

        // Check Database for wikiusername. We will return the wikified version of username.
        $fstrMySQLQuery = sprintf("SELECT `%3\$s`
                FROM `%2\$s`, `%1\$s`
                WHERE lcase(`%3\$s`) = lcase(?)
                AND `%2\$s`.`user_id` = `%1\$s`.`user_id`
                LIMIT 1",
            $this->_UserTB,
            $this->_ProfileDataTB,
            $this->_ProfileFieldName);

        // Query Database.
        $fresStatement = $fresMySQLConnection->prepare($fstrMySQLQuery);
        $fresStatement->bind_param('s', $wikiUsername);
        $fresStatement->execute();

        // Bind result
        $fresStatement->bind_result($resultWikiUsername);

        while ($fresStatement->fetch()) {
            return ucfirst($resultWikiUsername);
        }

        // At this point the username is invalid and should return just as it was passed.        
        return $username;
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
        if (isset($this->_UseWikiGroup) && $this->_UseWikiGroup === false) {
            return true;
        }

        // Connect to the database.
        $fresMySQLConnection = $this->connect();

        // If not an array make this an array.
        if (!is_array($this->_WikiGroupName)) {
            $this->_WikiGroupName = array($this->_WikiGroupName);
        }

        foreach ($this->_WikiGroupName as $WikiGrpName) {
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
            while ($fresStatement->fetch()) {
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
            while ($fresStatement->fetch()) {
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
            while ($fresStatement->fetch()) {
                if ($result == '1') {
                    return true; // User is in Wiki group.
                }
            }
        }
        // Hook error message.
        $this->_loginErrorMessage = $this->_NoWikiError;
        return false; // User is not in Wiki group.
    }


    /**
     * This loads the phpBB files that are needed.
     *
     */
    private function loadPHPFiles($FileSet)
    {
        $GLOBALS['phpbb_root_path'] = rtrim($this->_PathToPHPBB, '/') . '/'; // Path to phpBB
        $GLOBALS['phpEx'] = substr(strrchr(__FILE__, '.'), 1); // File Ext.

        // Check that path is valid.
        if (!is_dir($this->_PathToPHPBB)) {
            throw new Exception('Unable to find phpBB installed at (' . $this->_PathToPHPBB . ').');
        }

        switch ($FileSet) {
            case 'UTF8':
                // Check for UTF file.
                $utfToolsPath = rtrim($this->_PathToPHPBB, '/') . '/includes/utf/utf_tools.php';
                $autoloadPath = rtrim($this->_PathToPHPBB, '/') . '/vendor/autoload.php';

                if (!is_file($utfToolsPath)) {
                    throw new Exception('Unable to find phpbb\'s utf_tools.php file at (' . $utfToolsPath . '). Please check that phpBB is installed.');
                }

                // We need the composer autoloader because phpBB 3.2+ uses patchwork/utf.
                if (!is_file($autoloadPath)) {
                    throw new Exception('Unable to find phpbb\'s autoload.php file at (' . $autoloadPath . '). Please check that phpBB is installed.');
                }

                // Load the phpBB file.
                require_once $autoloadPath;
                require_once $utfToolsPath;
                break;
            case 'Password':
                $phpbb = rtrim($this->_PathToPHPBB, '/') . '/phpbb/';

                require_once $phpbb . 'config/config.php';
                require_once $phpbb . 'passwords/helper.php';
                require_once $phpbb . 'passwords/driver/helper.php';

                require_once $phpbb . 'passwords/driver/driver_interface.php';
                require_once $phpbb . 'passwords/driver/rehashable_driver_interface.php';
                require_once $phpbb . 'passwords/driver/base.php';
                require_once $phpbb . 'passwords/driver/base_native.php';

                require_once $phpbb . 'passwords/driver/argon2i.php';
                require_once $phpbb . 'passwords/driver/argon2id.php';
                require_once $phpbb . 'passwords/driver/bcrypt.php';
                require_once $phpbb . 'passwords/driver/bcrypt_2y.php';
                require_once $phpbb . 'passwords/driver/salted_md5.php';
                require_once $phpbb . 'passwords/driver/phpass.php';
                require_once $phpbb . 'passwords/driver/convert_password.php';
                require_once $phpbb . 'passwords/driver/sha1_smf.php';
                require_once $phpbb . 'passwords/driver/sha1.php';
                require_once $phpbb . 'passwords/driver/sha1_wcf1.php';
                require_once $phpbb . 'passwords/driver/md5_mybb.php';
                require_once $phpbb . 'passwords/driver/md5_vb.php';
                require_once $phpbb . 'passwords/driver/md5_phpbb2.php';
                require_once $phpbb . 'passwords/driver/sha_xf1.php';
                require_once $phpbb . 'passwords/manager.php';

                break;
            case 'phpBBLogout':
            case 'phpBBLogin':
                break;
        }
    }


    /**
     * This prints an error when a MySQL error is found.
     *
     * @param string $message
     * @access public
     */
    private function mySQLError($message)
    {
        throw new Exception('MySQL error: ' . $message . '<br /><br />');
    }


    /**
     * Cleans a username using PHPBB functions
     *
     * @param string $username
     * @return string
     */
    private function phpbb_clean_username($username)
    {
        $this->loadPHPFiles('UTF8'); // Load files needed to clean username.
        $saved_level = error_reporting(E_ALL ^ E_NOTICE ^ E_STRICT); // remove notices because phpBB does not use include once, strict to address PHP 5.4 issue.
        $username = utf8_clean_string($username);
        error_reporting($saved_level);
        return $username;
    }

    /**
     * @return \phpbb\passwords\manager
     */
    public function buildPhpBBPasswordmanager()
    {
        $config = new \phpbb\config\config(array());
        $passwords_helper = new \phpbb\passwords\helper($config);
        $passwords_driver_helper = new \phpbb\passwords\driver\helper($config);
        $passwords_drivers = array(
            'passwords.driver.argon2i' => new \phpbb\passwords\driver\argon2i($config, $passwords_driver_helper),
            'passwords.driver.argon2id' => new \phpbb\passwords\driver\argon2id($config, $passwords_driver_helper),
            'passwords.driver.bcrypt' => new \phpbb\passwords\driver\bcrypt($config, $passwords_driver_helper, 10),
            'passwords.driver.salted_md5' => new \phpbb\passwords\driver\salted_md5($config, $passwords_driver_helper),
            'passwords.driver.phpass' => new \phpbb\passwords\driver\phpass($config, $passwords_driver_helper),
            'passwords.driver.convert_password' => new \phpbb\passwords\driver\convert_password($config, $passwords_driver_helper),
            'passwords.driver.sha1_smf' => new \phpbb\passwords\driver\sha1_smf($config, $passwords_driver_helper),
            'passwords.driver.sha1' => new \phpbb\passwords\driver\sha1($config, $passwords_driver_helper),
            'passwords.driver.sha1_wcf1' => new \phpbb\passwords\driver\sha1_wcf1($config, $passwords_driver_helper),
            'passwords.driver.md5_mybb' => new \phpbb\passwords\driver\md5_mybb($config, $passwords_driver_helper),
            'passwords.driver.md5_vb' => new \phpbb\passwords\driver\md5_vb($config, $passwords_driver_helper),
            'passwords.driver.sha_xf1' => new \phpbb\passwords\driver\sha_xf1($config, $passwords_driver_helper),
        );

        $passwords_manager = new \phpbb\passwords\manager($config, $passwords_drivers, $passwords_helper, array_keys($passwords_drivers));
        return $passwords_manager;
    }

    /**
     * Provide a getter for the AuthManager to abstract out version checking.
     * Copied from LDAPAuthentication2 extension
     *
     * @return AuthManager
     */
    protected function getAuthManager() {
        if ( method_exists( MediaWikiServices::class, 'getAuthManager' ) ) {
            // MediaWiki 1.35+
            $authManager = MediaWikiServices::getInstance()->getAuthManager();
        } else {
            $authManager = AuthManager::singleton();
        }
        return $authManager;
    }
}

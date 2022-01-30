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
     * Class member used to cache wikified phpBB username
     *
     * @var string
     */
    private $_wikiPhpBBUserName = null;

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

        // Get the phpBB user_id of the username passed to us.
        // Check whether to use a phpBB custom profile field
        if ($this->_UseWikiProfile === true) {
            $this->debug("authenticate: looking up phpBB account & WikiProfile for '$username'");

            // For security reasons, first check the phpBB username before
            // falling back to the WikiProfile.
            $phpBBUserID = $this->lookupPhpBBUser($username) ?? $this->lookupWikiProfile($username);

            if ($phpBBUserID) {
                // Regardless of how we found them, if a WikiProfile entry was
                // found, prefer that value over the phpBB username
                $wikiUsername = $this->getWikiProfileName($phpBBUserID) ?? $this->_wikiPhpBBUserName;
            }
        } else {
            $this->debug("authenticate: looking up phpBB account for '$username'");
            $phpBBUserID = $this->lookupPhpBBUser($username);
            $wikiUsername = $this->_wikiPhpBBUserName;
        }

        if ($phpBBUserID === null) {
            // no such user found
            if ($this->_LoginMessage) {
                $errorMessage = $this->_LoginMessage;
            }
            $this->debug("authenticate: no user record found for username '$username'");
            return false;
        }

        $this->debug("authenticate: attempting login for '$username' as wiki user '$wikiUsername'");

        // Connect to the database.
        $fresMySQLConnection = $this->connect();

        // Load the password and email for the phpBB user_id
        $fstrMySQLQuery = sprintf("SELECT `user_password`, `user_email`
                FROM `%s`
                WHERE `user_id` = ? AND `user_type` != 1
                LIMIT 1", $this->_UserTB);

        // Query Database.
        $fresStatement = $fresMySQLConnection->prepare($fstrMySQLQuery) or
            $this->debug_and_throw("DB error querying against table ({$this->_UserTB}). Check the UserTB setting.");
        $fresStatement->bind_param('i', $phpBBUserID);
        $fresStatement->execute();

        // Bind results
        $fresStatement->bind_result($resultUserPassword, $resultUserEmail);

        if ($fresStatement->fetch()) {
            $this->loadPHPFiles('Password');

            $passwords_manager = $this->buildPhpBBPasswordmanager();

            /**
             * Check if password submitted matches the PHPBB password.
             * Also check if user is a member of the phpbb group 'wiki'.
             */
            if ($passwords_manager->check($password, $resultUserPassword)) {
                if ($this->isMemberOfWikiGroup($phpBBUserID)) {
                    $this->debug("authenticate: user '$username' logged in as wiki user '$wikiUsername'");
                    $username = $wikiUsername;
                    $realname = 'I need to Update My Profile';
                    $email = $resultUserEmail;
                    $id = $phpBBUserID;
                    return true;
                } else {
                    $this->debug("authenticate: '$username' not a member of required group(s)");
                }
            } else {
                $this->debug("authenticate: invalid password presented for '$username'");
            }
        }

        if ($this->_LoginMessage) {
            $errorMessage = $this->_LoginMessage;
        }
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

        } else {
            // Connect to database.
            $fresMySQLConnection = new mysqli($GLOBALS['wgDBserver'], $GLOBALS['wgDBuser'],
                $GLOBALS['wgDBpassword'], $GLOBALS['wgDBname']);
        }

        // Check if we are connected to the database.
        if ($fresMySQLConnection->connect_errno > 0) {
            $this->debug_and_throw("There was a problem when connecting to the phpBB database. Check your connection settings.");
        }

        $this->_MySQL_Version = substr($fresMySQLConnection->server_info, 0, 3); // Get the mysql version.

        // This is so utf8 usernames work. Needed for MySQL 4.1
        $fresStatement = $fresMySQLConnection->prepare("SET NAMES 'utf8'");
        $fresStatement->execute();

        return $fresMySQLConnection;
    }


    /**
     * Look up and return a phpBB user_id via the phpBB username.
     * If found, $this->_wikiPhpBBUserName is set to the wikified username.
     *
     * @param string $username phpBB username
     * @access private
     * @return string | null
     */
    private function lookupPhpBBUser($username)
    {
        // Connect to the database.
        $fresMySQLConnection = $this->connect();

        $phpBBUserName = $this->phpbb_clean_username($username);

        // Check Database for username.
        $fstrMySQLQuery = sprintf("SELECT `user_id`, `%s`
                FROM `%s`
                WHERE `username_clean` = ?
                LIMIT 1", ($this->_UseCanonicalCase ? "username" : "username_clean"), $this->_UserTB);

        // Query Database.
        $fresStatement = $fresMySQLConnection->prepare($fstrMySQLQuery) or
            $this->debug_and_throw("DB error querying against table ($this->_UserTB). Check the UserTB setting.");
        $fresStatement->bind_param('s', $phpBBUserName); // bind_param escapes the string
        $fresStatement->execute();

        // Bind result
        $fresStatement->bind_result($resultUserID, $resultWikiUsername);

        if ($fresStatement->fetch()) {
            // Preserve capped phpBB username when wikified version is valid
            $this->_wikiPhpBBUserName = ucfirst($resultWikiUsername);
            $this->debug("lookupPhpBBUser: found phpBB user '$username' with user_id $resultUserID");
            return $resultUserID;
        } else {
            $this->debug("lookupPhpBBUser: no phpBB username matched '$username'");
        }

        // No user with that username was found, return null.
        return null;
    }


    /**
     * Look up and return a phpBB user_id via the custom WikiProfile field.
     *
     * @param string $username WikiProfile username
     * @access private
     * @return string | null
     */
    private function lookupWikiProfile($username)
    {
        // Connect to the database.
        $fresMySQLConnection = $this->connect();

        // Check Database for WikiProfile username.
        $fstrMySQLQuery = sprintf("SELECT `user_id`
                FROM `%1\$s`
                WHERE lcase(`%2\$s`) = lcase(?)",
            $this->_ProfileDataTB,
            $this->_ProfileFieldName);

        // Query Database.
        $fresStatement = $fresMySQLConnection->prepare($fstrMySQLQuery) or
            $this->debug_and_throw("DB error querying against table ({$this->_ProfileDataTB}). Check the ProfileDataTB & ProfileFieldName settings.");
        $fresStatement->bind_param('s', $username);
        $fresStatement->execute();

        // Bind result
        $fresStatement->bind_result($resultUserID);

        $user_ids = [];
        while ($fresStatement->fetch()) {
            $user_ids[] = $resultUserID;
        }

        if (count($user_ids) == 1) {
            $this->debug("lookupWikiProfile: found WikiProfile '$username' with user_id {$user_ids[0]}");
            return $user_ids[0];
        } elseif (count($user_ids) == 0) {
            // No user with that username was found, return null.
            $this->debug("lookupPhpBBUser: no WikiProfile username matched '$username'");
            return null;
        } else {
            // If more than one entry was found we don't know which user we
            // should authenticate against so fail. We log an error here to
            // the php_error log but we don't pass this up to the user to
            // prevent leaking information.
            $this->debug("lookupPhpBBUser: duplicate WikiProfile usernames found for '$username'");
            error_log("Auth_phpBB: ERROR: duplicate WikiProfile found with value '$username'");
            return null;
        }

    }


    /**
     * Look up and return the custom WikiProfile field given a phpBB user_id.
     *
     * @param string $username user_id
     * @access private
     * @return string | null
     */
    private function getWikiProfileName($user_id)
    {
        // Connect to the database.
        $fresMySQLConnection = $this->connect();

        // Load WikiProfile username from the database
        $fstrMySQLQuery = sprintf("SELECT `%2\$s`
                FROM `%1\$s`
                WHERE `user_id` = ?
                LIMIT 1",
            $this->_ProfileDataTB,
            $this->_ProfileFieldName);

        // Query Database.
        $fresStatement = $fresMySQLConnection->prepare($fstrMySQLQuery) or
            $this->debug_and_throw("DB error querying against table ({$this->_ProfileDataTB}). Check the ProfileDataTB & ProfileFieldName settings.");
        $fresStatement->bind_param('i', $user_id);
        $fresStatement->execute();

        // Bind result
        $fresStatement->bind_result($resultWikiUsername);

        while ($fresStatement->fetch()) {
            // if the field is blank, return null
            if ($resultWikiUsername) {
                $wikiUsername = ucfirst($resultWikiUsername);
                $this->debug("getWikiProfileName: user_id $user_id has a WikiProfile of '$wikiUsername'");
                return $wikiUsername;
            } else {
                return null;
            }
        }

        // No user with that user_id was found, return null.
        $this->debug("getWikiProfileName: no WikiProfile found for user_id $user_id");
        return null;
    }


    /**
     * Checks if the user is a member of the PHPBB group called wiki.
     *
     * @param int $user_id
     * @access private
     * @return bool
     *
     */
    private function isMemberOfWikiGroup($user_id)
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

            // Get WikiId
            $fstrMySQLQuery = sprintf('SELECT `group_id` FROM `%s`
                    WHERE `group_name` = ?',
                $this->_GroupsTB);
            $fresStatement = $fresMySQLConnection->prepare($fstrMySQLQuery) or
                $this->debug_and_throw("DB error querying against table ({$this->_GroupsTB}). Check the GroupsTB setting.");
            $fresStatement->bind_param('s', $WikiGrpName); // bind_param escapes the string
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

            $fresStatement = $fresMySQLConnection->prepare($fstrMySQLQuery) or
                $this->debug_and_throw("DB error querying against table ({$this->_User_GroupTB}). Check the User_GroupTB setting.");
            $fresStatement->bind_param('ii', $user_id, $group_id);
            $fresStatement->execute();

            // Bind result
            $fresStatement->bind_result($result);

            // Check for a true or false response.
            while ($fresStatement->fetch()) {
                if ($result == '1') {
                    $this->debug("isMemberOfWikiGroup: user_id $user_id is a member of '$WikiGrpName'");
                    return true; // User is in Wiki group.
                }
            }
        }

        $this->debug("isMemberOfWikiGroup: user_id $user_id is not a member of " . json_encode($this->_WikiGroupName, true));

        // Hook error message.
        $this->_LoginMessage = $this->_NoWikiError;
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
            $this->debug_and_throw("Unable to find phpBB installed at ({$this->_PathToPHPBB}).");
        }

        switch ($FileSet) {
            case 'UTF8':
                // Check for UTF file.
                $utfToolsPath = rtrim($this->_PathToPHPBB, '/') . '/includes/utf/utf_tools.php';
                $autoloadPath = rtrim($this->_PathToPHPBB, '/') . '/vendor/autoload.php';

                if (!is_file($utfToolsPath)) {
                    $this->debug_and_throw("Unable to find phpbb's utf_tools.php file at ($utfToolsPath). Please check that phpBB is installed.");
                }

                // We need the composer autoloader because phpBB 3.2+ uses patchwork/utf.
                if (!is_file($autoloadPath)) {
                    $this->debug_and_throw("Unable to find phpbb's autoload.php file at ($autoloadPath). Please check that phpBB is installed.");
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
     * Access to the global logger.
     *
     * @param string $message
     * @access private
     */
    private function debug($message)
    {
        wfDebugLog("Auth_phpBB", $message);
    }


    /**
     * Log debug message and throw an exception.
     *
     * @param string $message
     * @access private
     */
    private function debug_and_throw($message)
    {
        $this->debug("ERROR: $message");
        throw Exception($message);
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

<?php

class Auth_phpBBHooks {

    /**
     * Extension registration callback
     */
    public static function onRegistration()
    {
        $GLOBALS['wgAuth'] = new Auth_phpBB($GLOBALS['wgAuth_Config']);

        // This requires a user be logged into the wiki to make changes.
        $GLOBALS['wgGroupPermissions']['*']['edit'] = false;

        // Specify who may create new accounts:
        $GLOBALS['wgGroupPermissions']['*']['createaccount'] = false;
        $GLOBALS['wgGroupPermissions']['*']['autocreateaccount'] = true;
    }

    /**
     * Hook to handle UserLoginForm
     */
    public static function onUserLoginForm(&$template)
    {
        return $GLOBALS['wgAuth']->onUserLoginForm($template);
    }

    /**
     * Hook to handle UserLoginComplete
     */
    public static function onUserLoginComplete(&$user, &$injectHtml, $direct)
    {
        return $GLOBALS['wgAuth']->onUserLoginComplete($user);
    }

    /**
     * Hook to handle UserLogout
     */
    public static function onUserLogout(&$user)
    {
        return $GLOBALS['wgAuth']->onUserLogout($user);
    }
}

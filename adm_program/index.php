<?php
/******************************************************************************
 * List of all modules and administration pages of Admidio
 *
 * Copyright    : (c) 2004 - 2015 The Admidio Team
 * Homepage     : http://www.admidio.org
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 *****************************************************************************/

// if config file doesn't exists, than show installation dialog
if(!file_exists('../adm_my_files/config.php'))
{
    header('Location: installation/index.php');
    exit();
}

require_once('system/common.php');

$headline = 'Admidio '.$gL10n->get('SYS_OVERVIEW');

// Navigation of the module starts here
$gNavigation->addStartUrl(CURRENT_URL, $headline);

// create html page object
$page = new HtmlPage($headline);

// main menu of the page
$mainMenu = $page->getMenu();

if($gValidLogin)
{
    // show link to own profile
    $mainMenu->addItem('adm_menu_item_my_profile', $g_root_path.'/adm_program/modules/profile/profile.php',
                       $gL10n->get('PRO_MY_PROFILE'), 'user');
    // show logout link
    $mainMenu->addItem('adm_menu_item_logout', $g_root_path.'/adm_program/system/logout.php',
                       $gL10n->get('SYS_LOGOUT'), 'sign-out');
}
else
{
    // show login link
    $mainMenu->addItem('adm_menu_item_login', $g_root_path.'/adm_program/system/login.php',
                       $gL10n->get('SYS_LOGIN'), 'sign-in');

    if($gPreferences['registration_mode'] > 0)
    {
        // show registration link
        $mainMenu->addItem('adm_menu_item_registration',
                           $g_root_path.'/adm_program/modules/registration/registration.php',
                           $gL10n->get('SYS_REGISTRATION'), 'user-plus');
    }
}

// menu with links to all modules of Admidio
$moduleMenu = new Menu('index_modules', $gL10n->get('SYS_MODULES'));

if($gPreferences['enable_announcements_module'] == 1
|| ($gPreferences['enable_announcements_module'] == 2 && $gValidLogin))
{
    $moduleMenu->addItem('announcements', '/adm_program/modules/announcements/announcements.php',
                         $gL10n->get('ANN_ANNOUNCEMENTS'), 'newspaper-o', $gL10n->get('ANN_ANNOUNCEMENTS_DESC'));
}
if($gPreferences['enable_download_module'] == 1)
{
    $moduleMenu->addItem('download', '/adm_program/modules/downloads/downloads.php',
                         $gL10n->get('DOW_DOWNLOADS'), 'download', $gL10n->get('DOW_DOWNLOADS_DESC'));
}
if($gPreferences['enable_mail_module'] == 1 && !$gValidLogin)
{
    $moduleMenu->addItem('email', '/adm_program/modules/messages/messages_write.php',
                         $gL10n->get('SYS_EMAIL'), 'envelope', $gL10n->get('MAI_EMAIL_DESC'));
}
if(($gPreferences['enable_pm_module'] == 1 || $gPreferences['enable_mail_module'] == 1) && $gValidLogin)
{
    $unreadBadge = '';

    // get number of unread messages for user
    $message = new TableMessage($gDb);
    $unread = $message->countUnreadMessageRecords($gCurrentUser->getValue('usr_id'));

    if($unread > 0)
    {
        $unreadBadge = '<span class="badge">' . $unread . '</span>';
    }

    $moduleMenu->addItem('private message', '/adm_program/modules/messages/messages.php',
                         $gL10n->get('SYS_MESSAGES') . $unreadBadge, 'comments', $gL10n->get('MAI_EMAIL_DESC'));
}
if($gPreferences['enable_photo_module'] == 1
|| ($gPreferences['enable_photo_module'] == 2 && $gValidLogin))
{
    $moduleMenu->addItem('photo', '/adm_program/modules/photos/photos.php',
                         $gL10n->get('PHO_PHOTOS'), 'photo', $gL10n->get('PHO_PHOTOS_DESC'));
}
if($gPreferences['enable_guestbook_module'] == 1
|| ($gPreferences['enable_guestbook_module'] == 2 && $gValidLogin))
{
    $moduleMenu->addItem('guestbk', '/adm_program/modules/guestbook/guestbook.php',
                         $gL10n->get('GBO_GUESTBOOK'), 'book', $gL10n->get('GBO_GUESTBOOK_DESC'));
}
$moduleMenu->addItem('lists', '/adm_program/modules/lists/lists.php',
                     $gL10n->get('LST_LISTS'), 'list-alt', $gL10n->get('LST_LISTS_DESC'));
if($gValidLogin)
{
    $moduleMenu->addSubItem('lists', 'mylist', '/adm_program/modules/lists/mylist.php',
                            $gL10n->get('LST_MY_LIST'));

    $moduleMenu->addSubItem('lists', 'rolinac', '/adm_program/modules/lists/lists.php?active_role=0',
                            $gL10n->get('ROL_INACTIV_ROLE'));
}
if($gPreferences['enable_dates_module'] == 1
|| ($gPreferences['enable_dates_module'] == 2 && $gValidLogin))
{
    $moduleMenu->addItem('dates', $g_root_path.'/adm_program/modules/dates/dates.php',
                         $gL10n->get('DAT_DATES'), 'calendar', $gL10n->get('DAT_DATES_DESC'));

    $moduleMenu->addSubItem('dates', 'olddates', '/adm_program/modules/dates/dates.php?mode=old',
                            $gL10n->get('DAT_PREVIOUS_DATES', $gL10n->get('DAT_DATES')));
}
if($gPreferences['enable_weblinks_module'] == 1
|| ($gPreferences['enable_weblinks_module'] == 2 && $gValidLogin))
{
    $moduleMenu->addItem('links', $g_root_path.'/adm_program/modules/links/links.php',
                         $gL10n->get('LNK_WEBLINKS'), 'globe', $gL10n->get('LNK_WEBLINKS_DESC'));
}

$page->addHtml($moduleMenu->show(true));

// menu with links to all administration pages of Admidio if the user has the right to administrate
if($gCurrentUser->isWebmaster() || $gCurrentUser->manageRoles()
|| $gCurrentUser->approveUsers() || $gCurrentUser->editUsers())
{
    $adminMenu = new Menu('index_administration', $gL10n->get('SYS_ADMINISTRATION'));

    if($gCurrentUser->approveUsers() && $gPreferences['registration_mode'] > 0)
    {
        $adminMenu->addItem('newreg', '/adm_program/modules/registration/registration.php',
                            $gL10n->get('NWU_NEW_REGISTRATIONS'), 'user-plus',
                            $gL10n->get('NWU_MANAGE_NEW_REGISTRATIONS_DESC'));
    }

    if($gCurrentUser->editUsers())
    {
        $adminMenu->addItem('usrmgt', '/adm_program/modules/members/members.php',
                            $gL10n->get('MEM_USER_MANAGEMENT'), 'users',
                            $gL10n->get('MEM_USER_MANAGEMENT_DESC'));
    }

    if($gCurrentUser->manageRoles())
    {
        $adminMenu->addItem('roladm', '/adm_program/modules/roles/roles.php',
                            $gL10n->get('ROL_ROLE_ADMINISTRATION'), 'sitemap',
                            $gL10n->get('ROL_ROLE_ADMINISTRATION_DESC'));
    }

    if($gCurrentUser->isWebmaster())
    {
        $adminMenu->addItem('dbback', '/adm_program/modules/backup/backup.php',
                            $gL10n->get('BAC_DATABASE_BACKUP'), 'cloud-download',
                            $gL10n->get('BAC_DATABASE_BACKUP_DESC'));

        $adminMenu->addItem('orgprop', '/adm_program/modules/preferences/preferences.php',
                            $gL10n->get('SYS_SETTINGS'), 'cog',
                            $gL10n->get('ORG_ORGANIZATION_PROPERTIES_DESC'));
    }

    $page->addHtml($adminMenu->show(true));
}

$page->show();

?>

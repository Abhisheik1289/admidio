<?php
declare(strict_types=1);
/**
 ***********************************************************************************************
 * Create and edit categories
 *
 * @copyright 2004-2017 The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */

/******************************************************************************
 * Parameters:
 *
 * cat_id: Id of the category that should be edited
 * type  : Type of categories that could be maintained
 *         ROL = Categories for roles
 *         LNK = Categories for weblinks
 *         ANN = Categories for announcements
 *         USF = Categories for profile fields
 *         DAT = Calendars for events
 * title : Parameter for the synonym of the categorie
 *
 ****************************************************************************/

require_once(__DIR__ . '/../../system/common.php');
require(__DIR__ . '/../../system/login_valid.php');

// Initialize and check the parameters
$getCatId = admFuncVariableIsValid($_GET, 'cat_id', 'int');
$getType  = admFuncVariableIsValid($_GET, 'type',   'string', array('requireValue' => true, 'validValues' => array('ROL', 'LNK', 'ANN', 'USF', 'DAT', 'INF', 'AWA')));
$getTitle = admFuncVariableIsValid($_GET, 'title',  'string');

// Modus und Rechte pruefen
if($getType === 'ROL' && !$gCurrentUser->manageRoles())
{
    $gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
    // => EXIT
}
elseif($getType === 'LNK' && !$gCurrentUser->editWeblinksRight())
{
    $gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
    // => EXIT
}
elseif($getType === 'ANN' && !$gCurrentUser->editAnnouncements())
{
    $gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
    // => EXIT
}
elseif($getType === 'USF' && !$gCurrentUser->editUsers())
{
    $gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
    // => EXIT
}
elseif($getType === 'DAT' && !$gCurrentUser->editDates())
{
    $gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
    // => EXIT
}
elseif($getType === 'AWA' && !$gCurrentUser->editUsers())
{
    $gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
    // => EXIT
}

// set module headline and other strings
if($getTitle === '')
{
    if($getType === 'ROL')
    {
        $headline = $gL10n->get('SYS_CATEGORY_VAR', array($gL10n->get('SYS_ROLES')));
    }
    elseif($getType === 'LNK')
    {
        $headline = $gL10n->get('SYS_CATEGORY_VAR', array($gL10n->get('LNK_WEBLINKS')));
    }
    elseif($getType === 'ANN')
    {
        $headline = $gL10n->get('SYS_CATEGORY_VAR', array($gL10n->get('ANN_ANNOUNCEMENTS')));
    }
    elseif($getType === 'USF')
    {
        $headline = $gL10n->get('SYS_CATEGORY_VAR', array($gL10n->get('ORG_PROFILE_FIELDS')));
    }
    else
    {
        $headline = $gL10n->get('SYS_CATEGORY');
    }

    $addButtonText = $gL10n->get('SYS_CATEGORY');
}
else
{
    $headline = $getTitle;
    $addButtonText = $getTitle;
}

// set headline of the script
if($getCatId > 0)
{
    $headline = $gL10n->get('SYS_EDIT_VAR', array($headline));
}
else
{
    $headline = $gL10n->get('SYS_CREATE_VAR', array($headline));
}

$gNavigation->addUrl(CURRENT_URL, $headline);

// create category object
$category = new TableCategory($gDb);

if(isset($_SESSION['categories_request']))
{
    // By wrong input, the user returned to this form now write the previously entered contents into the object

    $category->setArray($_SESSION['categories_request']);

    // get the selected roles for visibility
    $roleViewSet = $_SESSION['categories_request']['adm_categories_view_right'];

    if(!isset($_SESSION['categories_request']['show_in_several_organizations']))
    {
        $category->setValue('cat_org_id', $gCurrentOrganization->getValue('org_id'));
    }
    unset($_SESSION['categories_request']);
}
else
{
    if($getCatId > 0)
    {
        $category->readDataById($getCatId);

        // get assigned roles of this category
        $categoryViewRolesObject = new RolesRights($gDb, 'category_view', (int) $category->getValue('cat_id'));
        $roleViewSet = $categoryViewRolesObject->getRolesIds();

        // Pruefung, ob die Kategorie zur aktuellen Organisation gehoert bzw. allen verfuegbar ist
        if($category->getValue('cat_org_id') > 0
        && (int) $category->getValue('cat_org_id') !== (int) $gCurrentOrganization->getValue('org_id'))
        {
            $gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
            // => EXIT
        }
    }
    else
    {
        // a new category will be visible for all users per default
        $roleViewSet = array(0);

        // profile fields should be organization independent all other categories should be organization dependent as default
        if($getType !== 'USF')
        {
            $category->setValue('cat_org_id', $gCurrentOrganization->getValue('org_id'));
        }
    }
}

// create html page object
$page = new HtmlPage($headline);

$roleViewDescription = '';
if($getType === 'USF')
{
    $roleViewDescription = 'CAT_PROFILE_FIELDS_VISIBILITY';
}

if($getType !== 'ROL' && $gCurrentOrganization->countAllRecords() > 1)
{
    $page->addJavascript('
        $("#show_in_several_organizations").click(function() {
            if ($("#show_in_several_organizations").is(":checked")) {
                $("#adm_categories_view_right_group").hide();
            } else {
                $("#adm_categories_view_right_group").show("slow");
            }
        });
        $("#show_in_several_organizations").trigger("click");',
        true
    );
}

// add back link to module menu
$categoryCreateMenu = $page->getMenu();
$categoryCreateMenu->addItem('menu_item_back', $gNavigation->getPreviousUrl(), $gL10n->get('SYS_BACK'), 'back.png');

// show form
$form = new HtmlForm('categories_edit_form', ADMIDIO_URL.FOLDER_MODULES.'/categories/categories_function.php?cat_id='.$getCatId.'&amp;type='. $getType. '&amp;mode=1', $page);

// systemcategories should not be renamed
$fieldPropertyCatName = HtmlForm::FIELD_REQUIRED;
if($category->getValue('cat_system') == 1)
{
    $fieldPropertyCatName = HtmlForm::FIELD_DISABLED;
}

$form->addInput(
    'cat_name', $gL10n->get('SYS_NAME'), $category->getValue('cat_name', 'database'),
    array('maxLength' => 100, 'property' => $fieldPropertyCatName)
);

// Roles have their own preferences for visibility, so only allow this for other types.
// Until now we do not support visibility for categories that belong to several organizations,
// roles could be assigned if only 1 organization exists.
if($getType !== 'ROL' && ((bool) $category->getValue('cat_system') === false || $gCurrentOrganization->countAllRecords() === 1))
{
    // read all roles of the current organization
    $sqlViewRoles = 'SELECT rol_id, rol_name, cat_name
                       FROM '.TBL_ROLES.'
                 INNER JOIN '.TBL_CATEGORIES.'
                         ON cat_id = rol_cat_id
                      WHERE rol_valid  = 1
                        AND rol_system = 0
                        AND cat_name_intern <> \'EVENTS\'
                        AND cat_org_id = ? -- $gCurrentOrganization->getValue(\'org_id\')
                   ORDER BY cat_sequence, rol_name';
    $sqlDataView = array(
        'query'  => $sqlViewRoles,
        'params' => array($gCurrentOrganization->getValue('org_id'))
    );

    // if no roles are assigned then set "all users" as default
    if(count($roleViewSet) === 0)
    {
        $roleViewSet[] = 0;
    }

    // show selectbox with all assigned roles
    $form->addSelectBoxFromSql(
        'adm_categories_view_right', $gL10n->get('SYS_VISIBLE_FOR'), $gDb, $sqlDataView,
        array(
            'property'     => HtmlForm::FIELD_REQUIRED,
            'defaultValue' => $roleViewSet,
            'multiselect'  => true,
            'firstEntry'   => array('0', $gL10n->get('SYS_ALL').' ('.$gL10n->get('SYS_ALSO_VISITORS').')', null),
            'helpTextIdInline' => $roleViewDescription
        )
    );
}

// if current organization has a parent organization or is child organizations then show option to set this category to global
if($getType !== 'ROL' && $category->getValue('cat_system') == 0 && $gCurrentOrganization->countAllRecords() > 1)
{
    if($gCurrentOrganization->isChildOrganization())
    {
        $fieldProperty   = HtmlForm::FIELD_DISABLED;
        $helpTextIdLabel = 'CAT_ONLY_SET_BY_MOTHER_ORGANIZATION';
    }
    else
    {
        // show all organizations where this organization is mother or child organization
        $organizations = implode(', ', $gCurrentOrganization->getOrganizationsInRelationship(true, true, true));

        $fieldProperty = HtmlForm::FIELD_DEFAULT;
        if($getType === 'USF')
        {
            $helpTextIdLabel = array('CAT_CATEGORY_GLOBAL', $organizations);
        }
        else
        {
            $helpTextIdLabel = array('SYS_DATA_CATEGORY_GLOBAL', $organizations);
        }
    }

    $checked = false;
    if((int) $category->getValue('cat_org_id') === 0)
    {
        $checked = true;
    }

    $form->addCheckbox(
        'show_in_several_organizations', $gL10n->get('SYS_DATA_MULTI_ORGA'), $checked,
        array('property' => $fieldProperty, 'helpTextIdLabel' => $helpTextIdLabel)
    );
}

$form->addCheckbox(
    'cat_default', $gL10n->get('CAT_DEFAULT_VAR', array($addButtonText)), (bool) $category->getValue('cat_default'),
    array('icon' => 'star.png')
);
$form->addSubmitButton('btn_save', $gL10n->get('SYS_SAVE'), array('icon' => THEME_URL.'/icons/disk.png'));
$form->addHtml(admFuncShowCreateChangeInfoById(
    (int) $category->getValue('cat_usr_id_create'), $category->getValue('cat_timestamp_create'),
    (int) $category->getValue('cat_usr_id_change'), $category->getValue('cat_timestamp_change')
));

// add form to html page and show page
$page->addHtml($form->show(false));
$page->show();

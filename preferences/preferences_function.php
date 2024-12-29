<?php
/**
 ***********************************************************************************************
 * Script to manage preferences in the InventoryManager plugin
 *
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 * 
 * Parameters:
 * 
 * mode : 1 - write preferences to database
 *        2 - show dialog for deinstallation
 *        3 - deinstallation
 * form : The name of the form preferences that were submitted.
 ***********************************************************************************************
 */

require_once(__DIR__ . '/../../../adm_program/system/common.php');
require_once(__DIR__ . '/../common_function.php');
require_once(__DIR__ . '/../classes/configtable.php');

// Access only with valid login
require_once(__DIR__ . '/../../../adm_program/system/login_valid.php');

$pPreferences = new CConfigTablePIM();
$pPreferences->read();

// only authorized user are allowed to start this module
if (!isUserAuthorizedForPreferences()) {
	$gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
}

// Initialize and check the parameters
$getMode = admFuncVariableIsValid($_GET, 'mode', 'numeric', ['defaultValue' => 1]);
$getForm = admFuncVariableIsValid($_GET, 'form', 'string');

// in ajax mode only return simple text on error
if ($getMode === 1) {
	$gMessage->showHtmlTextOnly(true);
}

switch ($getMode) {
	case 1:
		try {
			handleFormSubmission($getForm, $pPreferences);
		} catch (AdmException $e) {
			$e->showText();
		}

		$pPreferences->write();
		echo 'success';
		break;

	case 2:
		showDeinstallationDialog();
		break;

	case 3:
		performDeinstallation($pPreferences);
		break;
}

/**
 * Handles the form submission and updates the preferences accordingly.
 *
 * @param string $form        The name of the form that was submitted.
 * @param object $preferences The preferences object to be updated.
 * @return void
 */
function handleFormSubmission($form, $preferences) {
	switch ($form) {
		case 'interface_pff':
			$preferences->config['Optionen']['interface_pff'] = $_POST['interface_pff'];
			break;

		case 'profile_addin':
			$preferences->config['Optionen']['profile_addin'] = $_POST['profile_addin'];
			break;

		case 'export':
			$preferences->config['Optionen']['file_name'] = $_POST['file_name'];
			$preferences->config['Optionen']['add_date'] = isset($_POST['add_date']) ? 1 : 0;
			break;

		case 'access_preferences':
			$preferences->config['access']['preferences'] = isset($_POST['access_preferences']) ? array_filter($_POST['access_preferences']) : array();
			break;

		default:
			global $gMessage, $gL10n;
			$gMessage->show($gL10n->get('SYS_INVALID_PAGE_VIEW'));
	}
}

/**
 * Displays the dialog for deinstallation of the plugin.
 *
 * @return void
 */
function showDeinstallationDialog() {
	global $gL10n, $gNavigation;

	$headline = $gL10n->get('PLG_INVENTORY_MANAGER_DEINSTALLATION');

	// create html page object
	$page = new HtmlPage('plg-inventory-manager-deinstallation', $headline);

	// add current url to navigation stack
	$gNavigation->addUrl(CURRENT_URL, $headline);

	$page->addHtml('<p class="lead">' . $gL10n->get('PLG_INVENTORY_MANAGER_DEINSTALLATION_FORM_DESC') . '</p>');

	// show form
	$form = new HtmlForm('deinstallation_form', SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_PLUGINS . PLUGIN_FOLDER . '/preferences/preferences_function.php', array('mode' => 3)), $page);
	$radioButtonEntries = array('0' => $gL10n->get('PLG_INVENTORY_MANAGER_DEINST_ACTORGONLY'), '1' => $gL10n->get('PLG_INVENTORY_MANAGER_DEINST_ALLORG'));
	$form->addRadioButton('deinst_org_select', $gL10n->get('PLG_INVENTORY_MANAGER_ORG_CHOICE'), $radioButtonEntries, array('defaultValue' => '0'));
	$form->addSubmitButton('btn_deinstall', $gL10n->get('PLG_INVENTORY_MANAGER_DEINSTALLATION'), array('icon' => 'fa-trash-alt', 'class' => 'offset-sm-3'));

	// add form to html page and show page
	$page->addHtml($form->show(false));
	$page->show();
}

/**
 * Performs the deinstallation of the plugin and cleans up the data.
 *
 * @param object $preferences The preferences object to be cleaned up.
 * @return void
 */
function performDeinstallation($preferences) {
	global $gNavigation, $gMessage, $gHomepage, $gL10n;

	$gNavigation->clear();
	$gMessage->setForwardUrl($gHomepage);

	$resMes = $gL10n->get('PLG_INVENTORY_MANAGER_DEINST_STARTMESSAGE');
	$resMes .= $preferences->deleteItemData($_POST['deinst_org_select']);
	$resMes .= $preferences->deleteConfigData($_POST['deinst_org_select']);
	$resMes .= $gL10n->get('PLG_INVENTORY_MANAGER_DEINST_ENDMESSAGE');

	$gMessage->show($resMes);
}

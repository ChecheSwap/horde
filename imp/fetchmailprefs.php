<?php
/**
 * Copyright 2002-2009 The Horde Project (http://www.horde.org/)
 *
 * See the enclosed file COPYING for license information (GPL). If you
 * did not receive this file, see http://www.fsf.org/copyleft/gpl.html.
 *
 * @author  Nuno Loureiro <nuno@co.sapo.pt>
 * @author  Michael Slusarz <slusarz@horde.org>
 * @package IMP
 */

require_once dirname(__FILE__) . '/lib/base.php';

/* Initialize Fetchmail libraries. */
$fm_account = new IMP_Fetchmail_Account();

$driver = Horde_Util::getFormData('fm_driver');
$fetch_url = Horde::applicationUrl('fetchmailprefs.php');
$prefs_url = Horde_Util::addParameter(IMP::prefsURL(true), 'group', 'fetchmail', false);
$to_edit = null;

/* Handle clients without javascript. */
$actionID = Horde_Util::getFormData('actionID');
if ($actionID === null) {
    if (Horde_Util::getPost('edit')) {
        $actionID = 'fetchmail_prefs_edit';
    } elseif (Horde_Util::getPost('save')) {
        $actionID = 'fetchmail_prefs_save';
    } elseif (Horde_Util::getPost('delete')) {
        $actionID = 'fetchmail_prefs_delete';
    } elseif (Horde_Util::getPost('back')) {
        header('Location: ' . $prefs_url);
        exit;
    } elseif (Horde_Util::getPost('select')) {
        header('Location: ' . $fetch_url);
        exit;
    }
}

/* Run through the action handlers */
switch ($actionID) {
case 'fetchmail_create':
    if ($driver) {
        $fetchmail = IMP_Fetchmail::factory($driver, array());
    }
    break;

case 'fetchmail_prefs_edit':
    $to_edit = Horde_Util::getFormData('account');
    $driver = $fm_account->getValue('driver', $to_edit);
    $fetchmail = IMP_Fetchmail::factory($driver, array());
    break;

case 'fetchmail_prefs_save':
    $to_edit = Horde_Util::getFormData('edit_account');
    if ($to_edit == '') {
        $to_edit = $fm_account->add();
    }

    $fetchmail = IMP_Fetchmail::factory($driver, array());

    $id = Horde_Util::getFormData('fm_id');
    if (empty($id)) {
        $id = _("Unnamed");
    }

    foreach ($fetchmail->getParameterList() as $val) {
        $fm_account->setValue($val, ($val == 'id') ? $id : Horde_Util::getFormData('fm_' . $val), $to_edit);
    }

    $prefs->setValue('fetchmail_login', (bool)array_sum($fm_account->getAll('loginfetch')));

    $notification->push(sprintf(_("The account \"%s\" has been saved."), $id), 'horde.success');
    break;

case 'fetchmail_prefs_delete':
    $to_delete = Horde_Util::getFormData('edit_account');
    if ($to_delete !== null) {
        $deleted_account = $fm_account->delete($to_delete);
        $notification->push(sprintf(_("The account \"%s\" has been deleted."), $deleted_account['id']), 'horde.success');
        $prefs->setValue('fetchmail_login', (bool)array_sum($fm_account->getAll('loginfetch')));
        $actionID = null;
    } else {
        $notification->push(_("You must select an account to be deleted."), 'horde.warning');
    }
    break;
}

require_once 'Horde/Prefs/UI.php';
$result = Horde::loadConfiguration('prefs.php', array('prefGroups', '_prefs'), 'imp');
if (is_a($result, 'PEAR_Error')) {
    Horde::fatal($result, __FILE__, __LINE__);
}
extract($result);

$app = 'imp';
$chunk = Horde_Util::nonInputVar('chunk');

/* Prepare template. */
$t = new IMP_Template();
$t->setOption('gettext', true);
$t->set('navcell', Horde_Util::bufferOutput(array('Prefs_UI', 'generateNavigationCell'), 'fetchmail'));
$t->set('fetchurl', $fetch_url);
$t->set('prefsurl', $prefs_url);
$t->set('forminput', Horde_Util::formInput());

if (empty($actionID)) {
    /* If actionID is still empty, we haven't selected an account yet. */
    $accountselect = true;
    $t->set('accountselect', true);
    $t->set('formname', 'fm_switch');
    $t->set('actionid', 'fetchmail_prefs_edit');

    $accounts = $fm_account->getAll('id');
    $accountsval = array();
    for ($i = 0, $iMax = count($accounts); $i < $iMax; $i++) {
        $accountsval[] = array(
            'i' => $i,
            'val' => htmlspecialchars($accounts[$i])
        );
    }
    $t->set('accounts', $accountsval);
} elseif (($actionID == 'fetchmail_create') && empty($driver)) {
    /* We are creating an account and need to select the type. */
    $t->set('driverselect', true);
    $t->set('formname', 'fm_driver_form');
    $t->set('actionid', 'fetchmail_create');

    $drivers = array();
    foreach (IMP_Fetchmail::listDrivers() as $key => $val) {
        $drivers[] = array(
            'key' => $key,
            'val' => htmlspecialchars($val)
        );
    }
    $t->set('drivers', $drivers);
} else {
    $t->set('manage', true);
    $t->set('formname', 'accounts');
    $t->set('actionid', 'fetchmail_prefs_save');
    $t->set('allowfolders', $conf['user']['allow_folders']);
    if ($t->get('allowfolders')) {
        $t->set('fmlmailbox', IMP::flistSelect(array('abbrev' => false, 'selected' => is_null($to_edit) ? '' : $fm_account->getValue('lmailbox', $to_edit))));
    }

    $protocol_list = array();
    foreach ($fetchmail->getProtocolList() as $key => $val) {
        $protocol_list[] = array(
            'key' => $key,
            'selected' => ($fm_account->getValue('protocol', $to_edit) == $key),
            'val' => $val
        );
    }
    $t->set('protocol_list', $protocol_list);

    $t->set('to_edit', ($to_edit !== null));
    if ($t->get('to_edit')) {
        $t->set('edit_account', intval($to_edit));
        $t->set('fmid', $fm_account->getValue('id', $to_edit));
        $t->set('fmusername', $fm_account->getValue('username', $to_edit));
        $t->set('fmpassword', $fm_account->getValue('password', $to_edit));
        $t->set('fmserver', $fm_account->getValue('server', $to_edit));
        if ($t->get('allowfolders')) {
            $t->set('fmrmailbox', $fm_account->getValue('rmailbox', $to_edit));
        }
        $t->set('fmonlynew', $fm_account->getValue('onlynew', $to_edit));
        $t->set('fmmarkseen', $fm_account->getValue('markseen', $to_edit));
        $t->set('fmdel', $fm_account->getValue('del', $to_edit));
        $t->set('fmloginfetch', $fm_account->getValue('loginfetch', $to_edit));
    }
    $t->set('driver', $driver);
    $t->set('colors', $conf['fetchmail']['show_account_colors']);
    if ($t->get('colors')) {
        $fm_colors = array();
        foreach (IMP_Fetchmail::listColors() as $val) {
            $fm_colors[] = array(
                'val' => $val,
                'checked' => (($to_edit !== null) && ($fm_account->getValue('acctcolor', $to_edit) == $val))
            );
        }
        $t->set('fm_colors', $fm_colors);
    }
    $t->set('fm_create', ($actionID == 'fetchmail_create'));
}

Prefs_UI::generateHeader(null, $chunk);
Horde::addScriptFile('prototype.js', 'horde', true);
Horde::addScriptFile('fetchmailprefs.js', 'imp', true);

$charset = NLS::getCharset();
IMP::addInlineScript(array(
    'ImpFetchmailprefs.fetchurl = ' . Horde_Serialize($fetch_url, Horde_Serialize::JSON, $charset),
    'ImpFetchmailprefs.prefsurl = ' . Horde_Serialize($prefs_url, Horde_Serialize::JSON, $charset)
));

echo $t->fetch(IMP_TEMPLATES . '/fetchmail/fetchmailprefs.html');
if (!$chunk) {
    require $registry->get('templates', 'horde') . '/common-footer.inc';
}

<?php
/**
 * Basic Nag test case.
 *
 * PHP version 5
 *
 * @category   Horde
 * @package    Nag
 * @subpackage UnitTests
 * @author     Gunnar Wrobel <wrobel@pardus.de>
 * @license    http://www.fsf.org/copyleft/lgpl.html LGPL
 * @link       http://pear.horde.org/index.php?package=Nag
 */

/**
 * Basic Nag test case.
 *
 * Copyright 2011 The Horde Project (http://www.horde.org/)
 *
 * See the enclosed file COPYING for license instorageion (LGPL). If you
 * did not receive this file, see http://www.fsf.org/copyleft/lgpl.html.
 *
 * @category   Horde
 * @package    Nag
 * @subpackage UnitTests
 * @author     Gunnar Wrobel <wrobel@pardus.de>
 * @license    http://www.fsf.org/copyleft/lgpl.html LGPL
 * @link       http://pear.horde.org/index.php?package=Nag
 */
class Nag_TestCase
extends PHPUnit_Framework_TestCase
{
    protected function getInjector()
    {
        return new Horde_Injector(new Horde_Injector_TopLevel());
    }

    protected function getKolabDriver()
    {
        $GLOBALS['registry'] = new Nag_Stub_Registry();
        $GLOBALS['injector'] = $this->getInjector();
        $kolab_factory = new Horde_Kolab_Storage_Factory(
            array(
                'driver' => 'mock',
                'queryset' => array('list' => array('queryset' => 'horde')),
                'params' => array(
                    'username' => 'test@example.com',
                    'host' => 'localhost',
                    'port' => 143,
                    'data' => array(
                        'user/test' => array(
                            'permissions' => array('anyone' => 'alrid')
                        )
                    )
                )
            )
        );
        $storage = $kolab_factory->create();
        $GLOBALS['injector']->setInstance('Horde_Kolab_Storage', $storage);
        $GLOBALS['injector']->setInstance('Horde_History', new Horde_History_Mock('test@example.com'));
        $GLOBALS['conf']['storage']['driver'] = 'kolab';
        $GLOBALS['nag_shares'] = new Horde_Share_Kolab(
            'nag', 'test@example.com', new Horde_Perms_Null(), new Horde_Group_Mock()
        );
        $GLOBALS['nag_shares']->setStorage($storage);
        $this->share = $GLOBALS['nag_shares']->newShare(
            'test@example.com',
            strval(new Horde_Support_Randomid()),
            "Notepad of Tester"
        );
        $GLOBALS['nag_shares']->addShare($this->share);
        $this->other_share = $GLOBALS['nag_shares']->newShare(
            'test@example.com',
            strval(new Horde_Support_Randomid()),
            "Other Notepad of Tester"
        );
        $GLOBALS['nag_shares']->addShare($this->other_share);
        return new Nag_Driver_Kolab($this->share->getName());
    }
}
<?php
/**
 * Test the Task form with the Kolab driver.
 *
 * PHP version 5
 *
 * @category   Horde
 * @package    Nag
 * @subpackage UnitTests
 * @author     Gunnar Wrobel <wrobel@pardus.de>
 * @link       http://www.horde.org/apps/nag
 * @license    http://www.horde.org/licenses/gpl GNU General Public License, version 2
 */

/**
 * Test the Task form with the Kolab driver.
 *
 * Copyright 2011-2017 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file COPYING for license information (GPLv2). If you did not
 * receive this file, see http://www.horde.org/licenses/gpl
 *
 * @category   Horde
 * @package    Nag
 * @subpackage UnitTests
 * @author     Gunnar Wrobel <wrobel@pardus.de>
 * @link       http://www.horde.org/apps/nag
 * @license    http://www.horde.org/licenses/gpl GNU General Public License, version 2
 */
class Nag_Unit_Form_Task_KolabTest extends Nag_Unit_Form_Task_Base
{
    protected $backupGlobals = false;

    public static function setUpBeforeClass()
    {
        return;

        parent::setUpBeforeClass();
        self::createKolabShares(self::$setup);
        list($share, $other_share) = self::_createDefaultShares();
    }
}

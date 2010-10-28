<?php
/**
 * Test the Dependencies module.
 *
 * PHP version 5
 *
 * @category   Horde
 * @package    Components
 * @subpackage UnitTests
 * @author     Gunnar Wrobel <wrobel@pardus.de>
 * @license    http://www.fsf.org/copyleft/lgpl.html LGPL
 * @link       http://pear.horde.org/index.php?package=Components
 */

/**
 * Prepare the test setup.
 */
require_once dirname(__FILE__) . '/../../../Autoload.php';

/**
 * Test the Dependencies module.
 *
 * Copyright 2010 The Horde Project (http://www.horde.org/)
 *
 * See the enclosed file COPYING for license information (LGPL). If you
 * did not receive this file, see http://www.fsf.org/copyleft/lgpl.html.
 *
 * @category   Horde
 * @package    Components
 * @subpackage UnitTests
 * @author     Gunnar Wrobel <wrobel@pardus.de>
 * @license    http://www.fsf.org/copyleft/lgpl.html LGPL
 * @link       http://pear.horde.org/index.php?package=Components
 */
class Components_Integration_Components_Module_DependenciesTest
extends Components_StoryTestCase
{
    /**
     * @scenario
     */
    public function theDependenciesModuleAddsTheLOptionInTheHelpOutput()
    {
        $this->given('the default Components setup')
            ->when('calling the package with the help option')
            ->then('the help will contain the option', '-L,\s*--list-deps');
    }

    /**
     * @scenario
     */
    public function theTheLOptionListThePackageDependenciesAsTree()
    {
        $this->given('the default Components setup')
            ->when('calling the package with the list dependencies option and a path to a Horde framework component')
            ->then('the non-Horde dependencies of the component will be listed')
            ->and('the Horde dependencies of the component will be listed');
    }

    /**
     * @scenario
     */
    public function theTheLOptionListThePackageDependenciesAsTreeWithoutColorIfSelected()
    {
        $this->given('the default Components setup')
            ->when('calling the package with the list dependencies option, the nocolor option and a path to a Horde framework component')
            ->then('the non-Horde dependencies of the component will be listed')
            ->and('the Horde dependencies of the component will be listed');
    }

    /**
     * @scenario
     */
    public function theTheVerboseLOptionListThePackageDependenciesAsTree()
    {
        $this->given('the default Components setup')
            ->when('calling the package with the verbose list dependencies option and a path to a Horde framework component')
            ->then('the non-Horde dependencies of the component will be listed')
            ->and('the Horde dependencies of the component will be listed');
    }

    /**
     * @scenario
     */
    public function theTheQuietLOptionListThePackageDependenciesAsTree()
    {
        $this->given('the default Components setup')
            ->when('calling the package with the quiet list dependencies option and a path to a Horde framework component')
            ->then('the non-Horde dependencies of the component will be listed')
            ->and('the Horde dependencies of the component will be listed');
    }
}
<?php
/**
 * Horde specific wrapper for Horde_Share drivers. Adds serializable interface,
 * Horde hook calls etc...
 *
 * Copyright 2002-2010 The Horde Project (http://www.horde.org/)
 *
 * See the enclosed file COPYING for license information (LGPL). If you did
 * not receive this file, see http://opensource.org/licenses/lgpl-2.1.php
 *
 * @author Michael J. Rubinsky <mrubinsk@horde.org>
 * @category Horde
 * @license  http://opensource.org/licenses/lgpl-2.1.php LGPL
 * @package  Core
 */
class Horde_Core_Share_Driver implements Serializable
{
    /** Serializable version **/
    const VERSION = 1;

    /**
     * The composed Horde_Share driver
     *
     * @var Horde_Share
     */
    protected $_share;

    /**
     * Maps the concrete share class to the required storage adapter.
     *
     * @var array
     */
    protected $_storageMap = array(
        'Horde_Share_Sql' => 'Horde_Db_Adapter',
        'Horde_Share_Sql_Hierarchical' => 'Horde_Db_Adapter',
        'Horde_Share_Kolab' => 'Horde_Kolab_Storage');

    /**
     */
    public function __construct(Horde_Share $share)
    {
        $this->_share = $share;
        $this->_share->setStorage($GLOBALS['injector']->getInstance($this->_storageMap[get_class($this->_share)]));
        $this->_share->addCallback('add', array($this, 'shareAddCallback'));
        $this->_share->addCallback('modify', array($this, 'shareModifyCallback'));
        $this->_share->addCallback('remove', array($this, 'shareRemoveCallback'));
        $this->_share->addCallback('list', array($this, 'shareListCallback'));

        try {
            Horde::callHook('share_init', array($this, $this->_share->getApp()));
        } catch (Horde_Exception_HookNotSet $e) {}
    }

    /**
     * Delegate method calls to the composed share object.
     *
     * @param string $method  The method name
     * @param array $args     The method arguments
     *
     * @return mixed  The result of the method call
     */
    public function __call($method, $args)
    {
        return call_user_func_array(array($this->_share, $method), $args);
    }

    /**
     * Serializes the object.
     *
     * @return string  The serialized object.
     */
    public function serialize()
    {
        $data = array(
            self::VERSION,
            $this->_share);

        return serialize($data);
    }

    /**
     * Reconstructs object from serialized properties.
     *
     * @param <type> $serialized
     */
    public function unserialize($data)
    {
        // Rebuild the object
        $data = @unserialize($data);
        if (!is_array($data) ||
            !isset($data[0]) ||
            ($data[0] != self::VERSION)) {
            throw new Exception('Cache version change');
        }
        $this->_share = $data[1];

        // Set the storage adapter.
        $this->_share->setStorage($GLOBALS['injector']->getInstance($this->_storageMap[get_class($this->_share)]));

        // Call the init hook
         try {
            Horde::callHook('share_init', array($this, $this->_share->getApp()));
        } catch (Horde_Exception_HookNotSet $e) {}
    }

    /**
     * Lock an item belonging to a share, or an entire share itself.
     *
     * @param Horde_Lock $locks          The lock object
     * @param Horde_Share_Object $share  The share object
     * @param string $uid                The uid of a specific object to lock,
     *                                   if null, entire share is locked.
     *
     * @return mixed  A lock ID on sucess, false if:
     *                  - The share is already locked,
     *                  - The item is already locked,
     *                  - A share lock was requested and an item is already
     *                    locked in the share.
     */
    public function lock(Horde_Lock $locks, $uid = null)
    {
        $shareid = $this->_share->getId();

        // Default parameters.
        $locktype = Horde_Lock::TYPE_EXCLUSIVE;
        $timeout = 600;
        $itemscope = $this->_share->getShareOb()->getApp() . ':' . $shareid;

        if (!empty($uid)) {
            // Check if the share is locked. Share locks are placed at app scope
            try {
                $result = $locks->getLocks($this->_share->getShareOb()->getApp(), $shareid, $locktype);
            } catch (Horde_Lock_Exception $e) {
                throw new Horde_Exception_Prior($e);
            }
            if (!empty($result)) {
                // Lock found.
                return false;
            }

            // Try to place the item lock at app:shareid scope.
            return $locks->setLock($GLOBALS['registry']->getAuth(),
                                   $itemscope,
                                   $uid,
                                   $timeout,
                                   $locktype);
        } else {
            // Share lock requested. Check for locked items.
            try {
                $result = $locks->getLocks($itemscope, null, $locktype);
            } catch (Horde_Lock_Exception $e) {
                throw new Horde_Exception_Prior($e);
            }
            if (!empty($result)) {
                // Lock found.
                return false;
            }

            // Try to place the share lock
            return $locks->setLock($GLOBALS['registry']->getAuth(),
                                   $this->_share->getShareOb()->getApp(),
                                   $shareid,
                                   $timeout,
                                   $locktype);
        }
    }

    /**
     * Removes the lock for a lock ID.
     *
     * @param Horde_Lock $locks  The lock object
     * @param string $lockid     The lock ID as generated by a previous call
     *                           to lock().
     *
     * @return boolean
     */
    public function unlock(Horde_Lock $locks, $lockid)
    {
        return $locks->clearLock($lockid);
    }

    /**
     * Checks for existing locks.
     *
     * First this checks for share locks and if none exists, checks for item
     * locks (if item_uid defined).  It will return the first lock found.
     *
     * @param Horde_Lock  $locks  The lock object.
     * @param string $item_uid    A uid of an item from this share.
     *
     * @return array   Hash with the found lock information in 'lock' and the
     *                 lock type ('share' or 'item') in 'type', or an empty
     *                 array if there are no locks.
     */
    public function checkLocks(Horde_Lock $locks, $item_uid = null)
    {
        $shareid = $this->_share->getId();
        $locktype = Horde_Lock::TYPE_EXCLUSIVE;

        // Check for share locks
        try {
            $result = $locks->getLocks($this->_share->getShareOb()->getApp(), $shareid, $locktype);
        } catch (Horde_Lock_Exception $e) {
            Horde::logMessage($e, 'ERR');
            throw new Horde_Exception_Prior($e);
        }

        if (empty($result) && !empty($item_uid)) {
            // Check for item locks
            $locktargettype = 'item';
            try {
                $result = $locks->getLocks($this->_share->getShareOb()->getApp() . ':' . $shareid, $item_uid, $locktype);
            } catch (Horde_Lock_Exception $e) {
                Horde::logMessage($e, 'ERR');
                throw new Horde_Exception($e->getMessage());
            }
        } else {
            $locktargettype = 'share';
        }

        if (empty($result)) {
            return array();
        }

        return array('type' => $locktargettype,
                     'lock' => reset($result));
    }

    /**
     * share_list callback
     *
     * @param string $userid  The userid listShares was called with
     * @param array  $shares  The result of the listShares() call
     * @param array  $params  The params that listShares() was called with
     *
     * @return array  An array of share objects
     */
    public function shareListCallback($userid, $shares, $params = array())
    {
        try {
            $params = new Horde_Support_Array($params);
            return Horde::callHook('share_list', array($userid, $params['perm'], $params['attributes'], $shares));
        } catch (Horde_Exception_HookNotSet $e) {}

        return $shares;
    }

    /**
     * Adds the share_add hook before delegating to the share object.
     *
     * @param Horde_Share_Object  The share object being added
     */
    public function shareAddCallback(Horde_Share_Object $share)
    {
        try {
            Horde::callHook('share_add', array($share));
        } catch (Horde_Exception_HookNotSet $e) {}
    }

    /**
     * Calls the share_remove hook before delegating to the share object.
     *
     * @see Horde_Share::removeShare
     */
    public function shareRemoveCallback(Horde_Share_Object $share)
    {
        try {
            Horde::callHook('share_remove', array($share));
        } catch (Horde_Exception_HookNotSet $e) {}
    }

    public function shareModifyCallback(Horde_Share_Object $share)
    {
        try {
            Horde::callHook('share_modify', array($this));
        } catch (Horde_Exception_HookNotSet $e) {}
    }

}
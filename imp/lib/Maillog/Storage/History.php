<?php
/**
 * Copyright 2014-2017 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file COPYING for license information (GPL). If you
 * did not receive this file, see http://www.horde.org/licenses/gpl.
 *
 * @category  Horde
 * @copyright 2014-2017 Horde LLC
 * @license   http://www.horde.org/licenses/gpl GPL
 * @package   IMP
 */

/**
 * Horde_History storage driver for the IMP_Maillog class.
 *
 * @author    Michael Slusarz <slusarz@horde.org>
 * @category  Horde
 * @copyright 2014-2017 Horde LLC
 * @license   http://www.horde.org/licenses/gpl GPL
 * @package   IMP
 */
class IMP_Maillog_Storage_History extends IMP_Maillog_Storage_Base
{
    /**
     * Mapping of driver actions -> class names.
     *
     * @var array
     */
    public static $drivers = array(
        'forward' => 'IMP_Maillog_Log_Forward',
        'mdn' => 'IMP_Maillog_Log_Mdn',
        'redirect' => 'IMP_Maillog_Log_Redirect',
        'reply' => 'IMP_Maillog_Log_Reply',
        'reply_all' => 'IMP_Maillog_Log_Replyall',
        'reply_list' => 'IMP_Maillog_Log_Replylist'
    );

    /**
     * History object.
     *
     * @var Horde_History
     */
    protected $_history;

    /**
     * User name.
     *
     * @var string
     */
    protected $_user;

    /**
     * Constructor.
     *
     * @param Horde_History $history  History object.
     * @param string $user            User name.
     */
    public function __construct(Horde_History $history, $user)
    {
        $this->_history = $history;
        $this->_user = $user;
    }

    /**
     */
    public function saveLog(
        IMP_Maillog_Message $msg, IMP_Maillog_Log_Base $log
    )
    {
        if (!$this->isAvailable($msg, $log)) {
            return false;
        }

        $data = array_merge($log->addData(), array(
            'action' => $log->action,
            'ts' => $log->timestamp
        ));

        try {
            $this->_history->log($this->_getUniqueHistoryId($msg), $data);
            return true;
        } catch (RuntimeException $e) {
            /* This is an invalid/missing Message-ID. Ignore. */
        } catch (Exception $e) {
            /* On error, log the error message only since informing the user is
             * just a waste of time and a potential point of confusion,
             * especially since they most likely don't even know the message
             * is being logged. */
            Horde::log(
                sprintf(
                    'Could not log message details to Horde_History. Error returned: %s',
                    $e->getMessage()
                ),
                'ERR'
            );
        }

        return false;
    }

    /**
     */
    public function getLog(IMP_Maillog_Message $msg, array $types = array())
    {
        global $conf;

        $out = array();

        /* Unless configured, this driver doesn't support MDN. */
        if (!empty($types) && empty($conf['maillog']['mdn_history'])) {
            $types = array_diff($types, array('IMP_Maillog_Log_Mdn'));
            if (empty($types)) {
                return $out;
            }
        }

        try {
            $history = $this->_history->getHistory(
                $this->_getUniqueHistoryId($msg)
            );
        } catch (Exception $e) {
            return $out;
        }

        foreach ($history as $val) {
            if (!isset(static::$drivers[$val['action']])) {
                continue;
            }

            $ob = new static::$drivers[$val['action']]($val);

            if (!empty($types) && !in_array(get_class($ob), $types)) {
                continue;
            }

            $ob->timestamp = $val['ts'];

            $out[] = $ob;
        }

        return $out;
    }

    /**
     */
    public function deleteLogs($msgs)
    {
        $ids = array();
        foreach ($msgs as $val) {
            try {
                $ids[] = $this->_getUniqueHistoryId($val);
            } catch (RuntimeException $e) {
                /* This is an invalid/missing Message-ID. Ignore. */
            }
        }

        $this->_history->removeByNames($ids);
    }

    /**
     */
    public function getChanges($ts)
    {
        $msgids = preg_replace(
            '/^([^:]*:){2}/',
            '',
            array_keys($this->_history->getByTimestamp(
                '>',
                $ts,
                array(),
                $this->_getUniqueHistoryId()
            ))
        );

        $out = array();
        foreach ($msgids as $val) {
            $out[] = new IMP_Maillog_Message($val);
        }

        return $out;
    }

    /**
     */
    public function isAvailable(
        IMP_Maillog_Message $msg, IMP_Maillog_Log_Base $log
    )
    {
        global $conf;

        /* Unless configured, this driver doesn't support MDN. */
        return (!($log instanceof IMP_Maillog_Log_Mdn) ||
                !empty($conf['maillog']['mdn_history']));
    }

    /**
     * Generate the unique log ID for an event.
     *
     * @param mixed $msg  An IMP_Maillog_Message object, a Message-ID, or, if
     *                    null, return the parent ID.
     *
     * @return string  The unique log ID.
     * @throws RuntimeException
     */
    protected function _getUniqueHistoryId($msg = null)
    {
        $msgid = $msg
            ? (is_string($msg) ? $msg : $msg->msgid)
            : null;
        if ($msgid === '') {
            throw new RuntimeException('Message-ID missing.');
        }

        return implode(':', array_filter(array(
            'imp',
            str_replace('.', '*', $this->_user),
            $msgid
        )));
    }

}

<?php

class pocketlistsNotifications
{
    /**
     * @var pocketlistsListModel[]
     */
    private static $lists = [];

    /**
     * @var pocketlistsListModel
     */
    private static $lm;

    /**
     * @param pocketlistsItem $item
     *
     * @return pocketlistsListOutputDecorator|pocketlistsNullList
     * @throws waException
     */
    protected static function getList($item)
    {
        if (!isset(self::$lists[$item->getListId()])) {
            if ($item->getListId()) {
                self::$lists[$item->getListId()] = $item->getList();
            } else {
                self::$lists[$item->getListId()] = (new pocketlistsNullList())->setName(_w('Stream'));
            }
        }

        return self::$lists[$item->getListId()];
    }

    /**
     * Notify all related users about completed items (according to their settings)
     *
     * @param pocketlistsItem[] $items
     *
     * @throws waException
     */
    public static function notifyAboutCompleteItems($items)
    {
        if (!$items) {
            return;
        }

        if (!is_array($items)) {
            $items = [$items];
        }

        $csm = new waContactSettingsModel();
        $q = "SELECT
                cs1.contact_id contact_id,
                cs2.value setting
              FROM wa_contact_settings cs1
              LEFT JOIN wa_contact_settings cs2 ON
                cs1.contact_id = cs2.contact_id
                AND cs2.app_id = s:app_id
                AND cs2.name = 'email_complete_item'
                AND cs2.value IN (i:setting)
              WHERE
                cs1.app_id = s:app_id
                AND cs1.name = 'email_complete_item_on'
                AND cs1.value = 1";
        $users = $csm->query(
            $q,
            [
                'app_id'  => wa()->getApp(),
                'setting' => [
                    pocketlistsUserSettings::EMAIL_WHEN_SOMEONE_COMPLETES_ITEM_I_CREATED,
                    pocketlistsUserSettings::EMAIL_WHEN_SOMEONE_COMPETES_ITEM_I_FAVORITE,
                    pocketlistsUserSettings::EMAIL_WHEN_SOMEONE_COMPETES_ITEM_IN_FAVORITE_LIST,
                    pocketlistsUserSettings::EMAIL_WHEN_SOMEONE_COMPETES_ANY_ITEM,
                ],
            ]
        )->fetchAll('contact_id');

        /** @var pocketlistsUserFavoritesModel $ufm */
        $ufm = pl2()->getModel('pocketlistsUserFavorites');

        $subject = 'string:{if !$complete}🚫{else}✅{/if} {str_replace(array("\r", "\n"), " ", $item->getName())|truncate:64}';
        // todo: refactor
        foreach ($users as $user_id => $user) { // foreach user
            $contact = new pocketlistsContact(new waContact($user_id));
            if (!self::canSend($contact)) {
                continue;
            }

            $filtered_items = [];
            switch ($user['setting']) {
                case pocketlistsUserSettings::EMAIL_WHEN_SOMEONE_COMPLETES_ITEM_I_CREATED:
                    foreach ($items as $item) { // filter items according to settings
                        // created NOT by user
                        if ($item->getContactId() != $user_id) {
                            continue;
                        }

                        if (!self::checkCompleteItem($item, $user_id)) {
                            continue;
                        }

                        $filtered_items[$item->getId()] = $item;
                    }

                    break;

                case pocketlistsUserSettings::EMAIL_WHEN_SOMEONE_COMPETES_ITEM_I_FAVORITE:
                    $user_items = $ufm->query(
                        "SELECT item_id FROM {$ufm->getTableName()} WHERE contact_id = {$user_id}"
                    )->fetchAll('item_id');

                    foreach ($items as $item) {
                        if (!array_key_exists($item->getId(), $user_items)) {
                            continue;
                        }

                        if (!self::checkCompleteItem($item, $user_id)) {
                            continue;
                        }

                        $filtered_items[$item->getId()] = $item;
                    }

                    break;

                case pocketlistsUserSettings::EMAIL_WHEN_SOMEONE_COMPETES_ITEM_IN_FAVORITE_LIST:
                    $user_lists = $ufm->query(
                        "SELECT i.key_list_id FROM {$ufm->getTableName()} uf JOIN pocketlists_item i ON uf.item_id = i.id AND i.key_list_id > 0 WHERE uf.contact_id = {$user_id}"
                    )->fetchAll('key_list_id');

                    foreach ($items as $item) {
                        if (!array_key_exists($item->getListId(), $user_lists)) {
                            continue;
                        }

                        if (!self::checkCompleteItem($item, $user_id)) {
                            continue;
                        }

                        $filtered_items[$item->getId()] = $item;
                    }

                    break;
                case pocketlistsUserSettings::EMAIL_WHEN_SOMEONE_COMPETES_ANY_ITEM:
                    foreach ($items as $item) { // filter items according to settings
                        if (!self::checkCompleteItem($item, $user_id)) {
                            continue;
                        }

                        $filtered_items[$item->getId()] = $item;
                    }

                    break;
            }

            if ($filtered_items) {
                $item = reset($filtered_items);
                $list = self::getList($item);
                $items_left = $item->getListId() ? count($list->getUndoneItems()) : 0;

                self::sendMail(
                    [
                        'contact_id' => $user_id,
                        'subject'    => $subject,
                        'body'       => wa()->getAppPath('templates/mails/completeanyitem.html'),
                        'variables'  => [
                            'n'        => $items_left,
                            'list'     => new pocketlistsListOutputDecorator($list),
                            'list_url' => $list ? sprintf(
                                '#/pocket/%s/list/%s/',
                                $list->getPocketId(),
                                $list->getId()
                            ) : false,
                            'complete' => $item->getStatus(),
                            'item'     => new pocketlistsItemOutputDecorator($item),
                        ],
                    ],
                    self::getBackendUrl($user_id)
                );
            }
        }
    }

    /**
     * Notify all related users about new items (according to their settings)
     *
     * @param pocketlistsItem[]    $items
     * @param pocketlistsList|null $list
     *
     * @throws waDbException
     * @throws waException
     */
    public static function notifyAboutNewItems($items, pocketlistsList $list = null)
    {
        if (!$items) {
            return;
        }

        if (!is_array($items)) {
            $items = [$items];
        }

        $csm = new waContactSettingsModel();
        $q = "SELECT
                cs1.contact_id contact_id,
                cs2.value setting
              FROM wa_contact_settings cs1
              LEFT JOIN wa_contact_settings cs2 ON
                cs1.contact_id = cs2.contact_id
                AND cs2.app_id = s:app_id
                AND cs2.name = 'email_add_item'
                AND cs2.value IN (i:setting)
              WHERE
                cs1.app_id = s:app_id
                AND cs1.name = 'email_add_item_on'
                AND cs1.value = 1";
        $users = $csm->query(
            $q,
            [
                'app_id'  => wa()->getApp(),
                'setting' => [
                    pocketlistsUserSettings::EMAIL_WHEN_SOMEONE_ADDS_ITEM_TO_FAVORITE_LIST,
                    pocketlistsUserSettings::EMAIL_WHEN_SOMEONE_ADDS_ITEM_TO_ANY_LIST,
                ],
            ]
        )->fetchAll('contact_id');

        /** @var pocketlistsUserFavoritesModel $ufm */
        $ufm = pl2()->getModel('pocketlistsUserFavorites');

        foreach ($users as $user_id => $user) { // foreach user
            $contact = new pocketlistsContact(new waContact($user_id));
            if (!self::canSend($contact)) {
                continue;
            }

            $filtered_items = [];
            switch ($user['setting']) {
                case pocketlistsUserSettings::EMAIL_WHEN_SOMEONE_ADDS_ITEM_TO_FAVORITE_LIST:
                    $user_lists = $ufm->query(
                        "SELECT i.key_list_id FROM {$ufm->getTableName()} uf JOIN pocketlists_item i ON uf.item_id = i.id AND i.key_list_id > 0 WHERE uf.contact_id = {$user_id}"
                    )->fetchAll('key_list_id');
                    $user_lists = array_keys($user_lists);

                    foreach ($items as $item) {
                        $list = self::getList($item);

                        if ($item['contact_id'] != $user_id
                            && in_array($item['list_id'], $user_lists)
                            &&
                            (
                                pocketlistsRBAC::canAccessToList($list, $user_id)
                                ||
                                ( // not from NULL-list
                                    $item['list_id'] == null && ( // OR from NULL-list,
                                        isset($item['assigned_contact_id']) && $item['assigned_contact_id'] == $user_id // but assigned to this user
                                    )
                                )
                            )
                        ) {
                            $filtered_items[$item['id']] = $item;
                            $c = new waContact($item['contact_id']);
                            $filtered_items[$item['id']]['contact_name'] = $c->getName();
                        }
                    }
                    if ($filtered_items && $list) {
                        self::sendMail(
                            [
                                'contact_id' => $user_id,
                                'subject'    => 'string:{str_replace(array("\r", "\n"), " ", $item.name_original)|truncate:64}',
                                'body'       => wa()->getAppPath('templates/mails/newfavoritelistitem.html'),
                                'variables'  => [
                                    'list_name' => $list ? $list['name'] : false,
                                    'list_url'  => $list ? sprintf(
                                        '#/pocket/%s/list/%s/',
                                        $list['pocket_id'],
                                        $list['id']
                                    ) : '',
                                    'items'     => $filtered_items,
                                    'item'      => reset($filtered_items),
                                ],
                            ],
                            self::getBackendUrl($user_id)
                        );
                    }
                    break;
                case pocketlistsUserSettings::EMAIL_WHEN_SOMEONE_ADDS_ITEM_TO_ANY_LIST:
                    foreach ($items as $item) { // filter items according to settings
                        $list = !empty($item['list_id']) ? self::getList($item['list_id']) : null;

                        if ($item['contact_id'] != $user_id // created not by this user
                            &&
                            (
                                ($list && pocketlistsRBAC::canAccessToList($list, $user_id))
                                ||
                                ( // not from NULL-list
                                    $item['list_id'] == null && ( // OR from NULL-list,
                                        (isset($item['assigned_contact_id']) && $item['assigned_contact_id'] == $user_id) || // but assigned to this user
                                        $item['contact_id'] == $user_id // OR created by user
                                    )
                                )
                            )
                        ) {
                            $filtered_items[$item['id']] = $item;
                            $c = new waContact($item['contact_id']);
                            $filtered_items[$item['id']]['contact_name'] = $c->getName();
                        }
                    }
                    if ($filtered_items && $list) {
                        self::sendMail(
                            [
                                'contact_id' => $user_id,
                                'subject'    => 'string:{str_replace(array("\r", "\n"), " ", $item.name_original)|truncate:64}',
                                'body'       => wa()->getAppPath('templates/mails/newitem.html'),
                                'variables'  => [
                                    'list_name' => $list ? $list['name'] : false,
                                    'list_url'  => $list ? sprintf(
                                        '#/pocket/%s/list/%s/',
                                        $list['pocket_id'],
                                        $list['id']
                                    ) : '',
                                    'items'     => $filtered_items,
                                    'item'      => reset($filtered_items),
                                ],
                            ],
                            self::getBackendUrl($user_id)
                        );
                    }
                    break;
            }
        }
    }

    /**
     * @param pocketlistsItem $item
     * @param string          $by_username
     *
     * @throws waException
     */
    public static function notifyAboutNewAssign(pocketlistsItem $item, $by_username = '')
    {
        if (!$by_username) {
            $by_username = wa()->getUser()->getName();
        }

        $list = self::getList($item);

        $listUrl = '';

        if ($list && pocketlistsRBAC::canAccessToList($list->getObject(), $item->getAssignedContactId())) {
            $listUrl = sprintf(
                '#/pocket/%s/list/%s/',
                $list->getPocketId(),
                $list->getId()
            );
        }

        $contact = $item->getAssignedContact();
        if (!self::canSend($contact)) {
            return;
        }

        /** @var pocketlistsItem $item */
        $item = new pocketlistsItemOutputDecorator($item);
        self::sendMail(
            [
                'contact_id' => $contact->getId(),
                'subject'    => 'string:✊ {str_replace(array("\r", "\n"), " ", $item->getName())|truncate:64}',
                'body'       => wa()->getAppPath('templates/mails/newassignitem.html'),
                'variables'  => [
                    'item'        => $item,
                    'due_date'    => $item->getDueDatetime()
                        ? waDateTime::format(
                            'humandatetime',
                            $item->getDueDatetime(),
                            $contact->getContact()->getTimezone()
                        )
                        : ($item->getDueDate() ? waDateTime::format(
                            'humandate',
                            $item->getDueDate(),
                            $contact->getContact()->getTimezone()
                        ) : false),
                    'list'        => new pocketlistsListOutputDecorator($list),
                    'listUrl'     => $listUrl,
                    'by_username' => $by_username,
                ],
            ],
            self::getBackendUrl($item->getAssignedContactId())
        );
    }

    /**
     * @param pocketlistsComment $comment
     *
     * @throws waException
     */
    public static function notifyAboutNewComment(pocketlistsComment $comment)
    {
        if (!$comment) {
            return;
        }

        // todo: refactor
        $csm = new waContactSettingsModel();
        $q = "SELECT
                cs1.contact_id contact_id,
                cs2.value setting
              FROM wa_contact_settings cs1
              LEFT JOIN wa_contact_settings cs2 ON
                cs1.contact_id = cs2.contact_id
                AND cs2.app_id = s:app_id
                AND cs2.name = 'email_comment_item'
                AND cs2.value IN (i:setting)
              WHERE
                cs1.app_id = s:app_id
                AND cs1.name = 'email_comment_item_on'
                AND cs1.value = 1";
        $users = $csm->query(
            $q,
            [
                'app_id'  => pocketlistsHelper::APP_ID,
                'setting' => [
                    pocketlistsUserSettings::EMAIL_WHEN_SOMEONE_ADDS_COMMENT_TO_MY_ITEM,
                    pocketlistsUserSettings::EMAIL_WHEN_SOMEONE_ADDS_COMMENT_TO_MY_FAVORITE_ITEM,
                    pocketlistsUserSettings::EMAIL_WHEN_SOMEONE_ADDS_COMMENT_TO_ANY_LIST_ITEM,
                ],
            ]
        )->fetchAll('contact_id');

        $comment_user = $comment->getContact();

        /** @var pocketlistsItem $item */
        $item = new pocketlistsItemOutputDecorator($comment->getItem());
        $listUrl = '#/pocket/todo/';
        $list = null;
        if ($item->getListId()) {
            $list = self::getList($item);

            $listUrl = sprintf(
                '#/pocket/%s/list/%s/',
                $list->getPocketId(),
                $list->getId()
            );
        }

        $mailParams = [
            'body'      => wa()->getAppPath('templates/mails/newcomment.html'),
            'variables' => [
                'item'        => $item,
                'comment'     => new pocketlistsCommentOutputDecorator($comment),
                'by_username' => $comment_user->getName(),
                'list'        => $list ? new pocketlistsListOutputDecorator($list) : false,
                'listUrl'     => $listUrl,
            ],
        ];

        foreach ($users as $user_id => $user) { // foreach user
            $contact = new pocketlistsContact(new waContact($user_id));
            if (!self::canSend($contact)) {
                continue;
            }

            // not from NULL-list
            if ($item->getListId() === null) {
                continue;
            }

            // from NULL-list, assigned to another user or created by another user
            if ($item->getListId() === null && ($item->getAssignedContactId() != $user_id || $item->getContactId(
                    ) == $user_id)) {
                continue;
            }

            if ($item->getListId() && !pocketlistsRBAC::canAccessToList($list->getObject(), $user_id)) {
                continue;
            }

            $mailParams['contact_id'] = $user_id;

//            if ($comment['contact_id'] != $user_id) {
            switch ($user['setting']) {
                case pocketlistsUserSettings::EMAIL_WHEN_SOMEONE_ADDS_COMMENT_TO_MY_ITEM:
                    if ($item->getContactId() == $user_id) {
                        $mailParams['subject'] = 'string:💬 {str_replace(array("\r", "\n"), " ", $item->getName())|truncate:64}';
                    }
                    break;

                case pocketlistsUserSettings::EMAIL_WHEN_SOMEONE_ADDS_COMMENT_TO_MY_FAVORITE_ITEM:
                    if ($item->isFavorite()) {
                        $mailParams['subject'] = 'string:💬 {str_replace(array("\r", "\n"), " ", $item->getName())|truncate:64}';
                    }
                    break;

                case pocketlistsUserSettings::EMAIL_WHEN_SOMEONE_ADDS_COMMENT_TO_ANY_LIST_ITEM:
                    if ($item) {
                        $mailParams['subject'] = 'string:💬 {str_replace(array("\r", "\n"), " ", $item->getName())|truncate:64}';
                    }
                    break;
            }

            self::sendMail($mailParams, self::getBackendUrl($user_id));
//            }
        }
    }

    public static function notifyDailyRecap($vars = [], $test = false)
    {
        $time = time();
        $csm = new waContactSettingsModel();

        $check_time = "AND IF(cs2.value IS NULL, 0, cs2.value) <= ($time - 60*60*24)";
        if ($test) {
            $check_time = "";
        }

        // get recap setting for all users and do not select users who received daily recap less then 24 hours ago
        $q = "SELECT
                cs1.contact_id contact_id,
                cs2.value last_recap_cron_time,
                cs3.value setting
              FROM wa_contact_settings cs1
              LEFT JOIN wa_contact_settings cs2 ON
                cs2.contact_id = cs1.contact_id
                AND cs2.app_id = 'pocketlists'
                AND cs2.name = 'last_recap_cron_time'
              LEFT JOIN wa_contact_settings cs3 ON
                cs3.contact_id = cs1.contact_id
                AND cs3.app_id = 'pocketlists'
                AND cs3.name = 'daily_recap'
                AND cs3.value IN (i:setting)
              WHERE
                cs1.app_id = 'pocketlists'
                AND cs1.name = 'daily_recap_on'
                AND cs1.value = 1
                {$check_time}";
        $users = $csm->query(
            $q,
            [
                'setting' => [
                    pocketlistsUserSettings::DAILY_RECAP_FOR_TODAY,
                    pocketlistsUserSettings::DAILY_RECAP_FOR_TODAY_AND_TOMORROW,
                    pocketlistsUserSettings::DAILY_RECAP_FOR_NEXT_7_DAYS,
                ],
            ]
        )->fetchAll('contact_id');
        $im = new pocketlistsItemModel();
        foreach ($users as $user_id => $user) {
            $contact = new waContact($user_id);
            if (!self::canSend($contact)) {
                continue;
            }

            if (wa()->getEnv() == 'cli') { // to load locale in cli
                wa()->setLocale($contact->getLocale());
            }

            $items = $im->getDailyRecapItems($contact->getId(), $user['setting']);
            if ($items) {
                self::sendMail(
                    [
                        'contact_id' => $user_id,
                        'subject'    => 'string:'.sprintf(_w("Daily recap for %s"), waDateTime::format('humandate')),
                        'body'       => wa()->getAppPath('templates/mails/dailyrecap.html'),
                        'variables'  => [
                                'items'    => $items,
                                'timezone' => $contact->getTimezone(),
                            ] + $vars,
                    ],
                    self::getBackendUrl($user_id)
                );
                $csm->set($user_id, 'pocketlists', 'last_recap_cron_time', $time);
            }
        }
    }

    public static function notifyAboutNewList($list)
    {
        $csm = new waContactSettingsModel();
        $q = "SELECT
                cs1.contact_id contact_id
              FROM wa_contact_settings cs1
              WHERE
                cs1.app_id = s:app_id
                AND cs1.name = 'email_create_list_on'
                AND cs1.value = 1";
        $users = $csm->query(
            $q,
            [
                'app_id' => wa()->getApp(),
            ]
        )->fetchAll('contact_id');

        $list_ = self::getList($list['id']);

        $c = new waContact($list['contact_id']);
        $create_contact_name = $c->getName();
        $list['create_datetime'] = waDateTime::format('humandatetime', $list['create_datetime']);
        foreach ($users as $user_id => $user) { // foreach user
            $contact = new waContact($user_id);
            if (!self::canSend($contact)) {
                continue;
            }

            if ($list['contact_id'] != $user_id && pocketlistsRBAC::canAccessToList(
                    $list_,
                    $user_id
                )) { // created not by user
                self::sendMail(
                    [
                        'contact_id' => $user_id,
                        'subject'    => 'string:📝 [`New list!`]',
                        'body'       => wa()->getAppPath('templates/mails/newlist.html'),
                        'variables'  => [
                            'list_name'       => $list['name'],
                            'list_url'        => sprintf('#/pocket/%s/list/%s/', $list['pocket_id'], $list['id']),
                            'by'              => $create_contact_name,
                            'create_datetime' => $list['create_datetime'],
                        ],
                    ],
                    self::getBackendUrl($user_id)
                );
            }
        }
    }

    /**
     * Send email to user
     *
     * @param $data
     */
    public static function sendMail($data, $backend_url = false)
    {
        try {
            $default_variables = [
                'email_settings_url' => '#/settings/',
            ];

            if (empty($data['variables'])) {
                $data['variables'] = [];
            }
            $data['variables'] = array_merge($default_variables, $data['variables']);

            $to = false;
            $view = wa()->getView();
            $view->clearAllAssign();
            $view->clearAllCache();

            if (isset($data['contact_id'])) {
                $contact = new waContact($data['contact_id']);
                $to = $contact->get('email', 'default'); // todo: add email option in settings
                $view->assign('name', $contact->getName());
                $view->assign('now', waDateTime::date("Y-m-d H:i:s", time(), $contact->getTimezone()));
            } elseif (isset($data['to'])) {
                $to = $data['to'];
            }

            if (!$to) {
                return;
            }

            $absolute_backend_url = $backend_url
                ? $backend_url
                : pl2()->getRootUrl(true).pl2()->getBackendUrl();

            $view->assign('backend_url', rtrim($absolute_backend_url, '/').'/pocketlists/');
            if (isset($data['variables'])) {
                foreach ($data['variables'] as $var_name => $var_value) {
                    $view->assign($var_name, $var_value);
                }
            }

            $subject = $view->fetch($data['subject']);
            $body = $view->fetch($data['body']);

            $message = new waMailMessage($subject, $body);
            $message->setTo($to);
// todo: settings?
//        $message->setFrom('pocketlists@webasyst.ru', 'Pocketlists Notifier');

            if (!$message->send()) {
                pocketlistsHelper::logError(sprintf('Email send error to %s', $to));
            }
        } catch (waException $ex) {
            pocketlistsHelper::logError(sprintf('Email send error to %s', $to), $ex);
        }
    }

    private static function getBackendUrl($user_id)
    {
        $us = new waContactSettingsModel();

        return $us->getOne($user_id, 'webasyst', 'backend_url');
    }

    /**
     * @param pocketlistsContact $contact
     *
     * @return bool
     */
    private static function canSend(pocketlistsContact $contact)
    {
        return $contact->isExists();
    }

    /**
     * @param pocketlistsItem $item
     * @param                 $user_id
     *
     * @return bool
     * @throws waException
     */
    private static function checkCompleteItem(pocketlistsItem $item, $user_id)
    {
        // completed not by user
        if ($item->getCompleteContactId() == $user_id) {
            return false;
        }

        // not from NULL-list
        if (!$item->getListId()) {
            return false;
        }

        $list = self::getList($item);
        if ($item->getListId() && !pocketlistsRBAC::canAccessToList($list, $user_id)) {
            return false;
        }

        // from NULL-list but not assigned to this user nor created by user
        if (!$item->getListId() && ($item->getAssignedContactId() != $user_id || $item->getContactId() != $user_id)) {
            return false;
        }

        return true;
    }
}

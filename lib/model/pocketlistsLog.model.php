<?php

/**
 * Class pocketlistsAttachmentModel
 */
class pocketlistsLogModel extends pocketlistsModel
{
    const LIMIT = 30;

    protected $table = 'pocketlists_log';

    /**
     * @param array $availableLists
     * @param array $availablePockets
     * @param int   $offset
     * @param int   $limit
     *
     * @return array
     */
    public function getLast(
        $availableLists = [],
        $availablePockets = [],
        $offset = 0,
        $limit = self::LIMIT
    ) {
        $queryComponents = $this->getQueryComponents();

        if ($availablePockets) {
            $queryComponents['where']['and'][] = 'l.pocket_id in (i:pocket_ids)';
        }

        if ($availableLists) {
            $queryComponents['where']['and'][] = 'l.list_id in (i:list_ids)';
        }

        $sql = $this->buildSqlComponents($queryComponents, $limit, $offset);

        $data = $this->query(
            $sql,
            [
                'pocket_ids' => $availablePockets,
                'list_ids'   => $availableLists,
            ]
        )->fetchAll();

        return $data;
    }

    /**
     * @param array $availableLists
     * @param array $availablePockets
     * @param int   $pocketId
     * @param int   $offset
     * @param int   $limit
     *
     * @return array
     */
    public function getLastByPocketId(
        $availableLists = [],
        $availablePockets = [],
        $pocketId = 0,
        $offset = 0,
        $limit = self::LIMIT
    ) {
        $queryComponents = $this->getQueryComponents();

        if ($availablePockets) {
            $queryComponents['where']['and'][] = 'l.pocket_id in (i:pocket_ids)';
        }

        if ($availableLists) {
            $queryComponents['where']['and'][] = 'l.list_id in (i:list_ids)';
        }

        if ($pocketId) {
            $queryComponents['where']['and'][] = '(l.pocket_id = i:pocket_id)';
        }

        $sql = $this->buildSqlComponents($queryComponents, $limit, $offset);

        $data = $this->query(
            $sql,
            [
                'pocket_ids' => $availablePockets,
                'list_ids'   => $availableLists,
                'pocket_id'  => $pocketId,
            ]
        )->fetchAll();

        return $data;
    }

    /**
     * @param array $availableLists
     * @param int   $listId
     * @param int   $offset
     * @param int   $limit
     *
     * @return array
     */
    public function getLastByListId(
        $availableLists = [],
        $listId = 0,
        $offset = 0,
        $limit = self::LIMIT
    ) {
        $queryComponents = $this->getQueryComponents();

        if ($availableLists) {
            $queryComponents['where']['and'][] = 'l.list_id in (i:list_ids)';
        }

        if ($listId) {
            $queryComponents['where']['and'][] = '(l.list_id = i:list_id)';
        }

        $sql = $this->buildSqlComponents($queryComponents, $limit, $offset);

        $data = $this->query(
            $sql,
            [
                'list_ids' => $availableLists,
                'list_id'  => $listId,
            ]
        )->fetchAll();

        return $data;
    }

    /**
     * @param array $availableLists
     * @param array $availablePockets
     * @param int   $contactId
     * @param int   $offset
     * @param int   $limit
     *
     * @return array
     */
    public function getLastByContactId(
        $availableLists = [],
        $availablePockets = [],
        $contactId = 0,
        $offset = 0,
        $limit = self::LIMIT
    ) {
        $queryComponents = $this->getQueryComponents();

        if ($availablePockets) {
            $queryComponents['where']['and'][] = 'l.pocket_id in (i:pocket_ids)';
        }

        if ($availableLists) {
            $queryComponents['where']['and'][] = 'l.list_id in (i:list_ids)';
        }

        if ($contactId) {
            $queryComponents['where']['and'][] = '(l.contact_id = i:contact_id)';// or (l.additional_id = i.contact_id and ';
        }

        $sql = $this->buildSqlComponents($queryComponents, $limit, $offset);

        $data = $this->query(
            $sql,
            [
                'pocket_ids' => $availablePockets,
                'list_ids'   => $availableLists,
                'contact_id' => $contactId,
            ]
        )->fetchAll();

        return $data;
    }

    /**
     * @return array
     */
    public function getQueryComponents()
    {
        return [
            'select'   => [
                '*' => 'l.*',
            ],
            'from'     => ['l' => "{$this->table} l"],
            'join'     => [],
            'where'    => [
                'and' => [],
                'or'  => [],
            ],
            'group by' => [],
            'order by' => ['l.id desc'],
        ];
    }
}

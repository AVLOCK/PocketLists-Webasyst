<?php

/**
 * Class pocketlistsProPluginLabelFactory
 *
 * @method pocketlistsProPluginLabelModel getModel()
 */
class pocketlistsProPluginLabelFactory extends pocketlistsFactory
{
    protected $entity;

    /**
     * @param pocketlistsItem $item
     *
     * @return pocketlistsProPluginLabel|null
     * @throws pocketlistsLogicException
     * @throws waException
     */
    public function findForItem(pocketlistsItem $item)
    {
        $labelId = $item->getDataField('pro_label_id');
        /** @var pocketlistsProPluginLabel $label */
        $label = $this->findById($labelId);

        return $label ?: null;
    }

    /**
     * @param pocketlistsProPluginLabel $entity
     *
     * @return bool
     * @throws waException
     */
    public function delete(pocketlistsEntity $entity)
    {
        if (parent::delete($entity)) {
            pl2()->getModel(pocketlistsItem::class)->updateByField(
                'pro_label',
                $entity->getId(),
                ['pro_label' => null]
            );

            return true;
        }

        return false;
    }

    /**
     * @param pocketlistsPocket $pocket
     * @param pocketlistsProPluginLabel $label
     *
     * @return pocketlistsItem[]
     * @throws waException
     */
    public function findItemsByPocketAndLabel(pocketlistsPocket $pocket, pocketlistsProPluginLabel $label)
    {
        /** @var pocketlistsItemModel $itemModel */
        $itemModel = pl2()->getModel(pocketlistsItem::class);
        $sqlParts = $itemModel->getQueryComponents();
        $sqlParts['join'] += [
            'join pocketlists_list pl on pl.id = i.list_id',
            'join pocketlists_pocket pp on pp.id = pl.pocket_id',
            'join pocketlists_pro_label ppl on ppl.id = i.pro_label_id',
        ];
        $sqlParts['where']['and'] = ['pp.id = i:pocket_id', 'i.pro_label_id = i:label_id'];
        $sql = $itemModel->buildSqlComponents($sqlParts);

        $itemsData = $itemModel->query(
            $sql,
            [
                'contact_id' => wa()->getUser()->getId(),
                'pocket_id'  => $pocket->getId(),
                'label_id'   => $label->getId(),
            ]
        )->fetchAll();

        /** @var pocketlistsItemFactory $itemFactory */
        $itemFactory = pl2()->getEntityFactory(pocketlistsItem::class);
        $items = $itemFactory->generateWithData($itemsData, true);

        return $items;
    }

    /**
     * @param pocketlistsPocket $pocket
     * @param pocketlistsProPluginLabel $label
     *
     * @return pocketlistsItem[]
     * @throws waException
     */
    public function findListsByPocketAndLabel(pocketlistsPocket $pocket, pocketlistsProPluginLabel $label)
    {
        $available_lists = pocketlistsRBAC::getAccessListForContact();

        /** @var pocketlistsListModel $listModel */
        $listModel = pl2()->getModel(pocketlistsList::class);
        $sqlParts = $listModel->getAllListsSqlComponents($pocket->getId(), $available_lists);
        $sqlParts['join'] += [
            'join pocketlists_pro_label ppl on i.pro_label_id = ppl.id',
        ];
        $sqlParts['where']['and'] = ['i.pro_label_id > 0'];

        $sql = $listModel->buildSqlComponents($sqlParts);

        $listsData = $listModel->query(
            $sql,
            [
                'contact_id' => wa()->getUser()->getId(),
                'pocket_id'  => $pocket->getId(),
                'label_id'   => $label->getId(),
                'list_ids'   => $available_lists,
            ]
        )->fetchAll();

        /** @var pocketlistsListFactory $listFactory */
        $listFactory = pl2()->getEntityFactory(pocketlistsList::class);
        $lists = $listFactory->generateWithData($listsData, true);

        return $lists;
    }
}
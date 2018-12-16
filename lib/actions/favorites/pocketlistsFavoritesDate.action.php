<?php

class pocketlistsFavoritesDateAction extends pocketlistsViewAction
{
    // todo: almost same as ToDo
    public function execute()
    {
        // get pocket dots
        $im = new pocketlistsItemModel();

        $date = waRequest::get('date', false);

        // get all due or priority or assigned to me items
        $items = $im->getFavorites(wa()->getUser()->getId(), $date);

        $this->view->assign('undone_items', $items[0]);
        $this->view->assign('done_items', $items[1]);
        $this->view->assign('count_done_items', count($items[1]));

        $this->view->assign('date', $date);
        $timestamp = $date ? waDateTime::date('Y-m-d', strtotime($date)) : waDateTime::date('Y-m-d', time() + 60 * 60 * 24, wa()->getUser()->getTimezone());
        $this->view->assign('timestamp', $timestamp);

        $us = new pocketlistsUserSettings();
        $lm = new pocketlistsListModel();
        $stream_list_id = $us->getStreamInboxList();
        if ($stream_list_id && $stream_list = $lm->getById($stream_list_id)) {
            $this->view->assign("stream_list_id", $stream_list_id);
            $this->view->assign("stream_list", $stream_list);
        }

        $this->view->assign('pl2_attachments_path', wa()->getDataUrl('attachments/', true, pocketlistsHelper::APP_ID));
        $this->view->assign('this_is_stream', true);
        $this->view->assign('print', waRequest::get('print', false));
        $this->view->assign('user', $this->user);
    }
}

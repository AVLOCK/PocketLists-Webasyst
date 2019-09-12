<?php

/**
 * Class pocketlistsNotificationSendService
 */
class pocketlistsNotificationSendService
{
    /**
     * @param pocketlistsNotification $notification
     *
     * @throws pocketlistsNotImplementedException
     * @throws waException
     */
    public function send(pocketlistsNotification $notification)
    {
        wa()->getStorage()->close();

        $notification->setStatus(pocketlistsNotification::STATUS_SENDING);
        pl2()->getEntityFactory(pocketlistsNotification::class)->update($notification);

        $content = $notification->getContent();

        try {
            if ($content->send()) {
                $notification
                    ->setSentAt(date('Y-m-d H:i:s'))
                    ->setStatus(pocketlistsNotification::STATUS_OK);
            } else {
                $notification
                    ->setError($content->getError())
                    ->setStatus(pocketlistsNotification::STATUS_FAIL);
            }
        } catch (Exception $ex) {
            $notification
                ->setError($ex->getMessage())
                ->setStatus(pocketlistsNotification::STATUS_FAIL);
        }

        pl2()->getEntityFactory(pocketlistsNotification::class)->update($notification);

        pl2()->getEventDispatcher()->dispatch(
            new pocketlistsEvent(pocketlistsEventStorage::NOTIFICATION_SENT, $notification)
        );
    }

    /**
     * @param int $chunk
     *
     * @return int
     */
    public function sendBatch($chunk = 100)
    {
        try {
            $notifications = pl2()->getEntityFactory(pocketlistsNotification::class)->findUnsent($chunk);

            $sentCount = 0;
            foreach ($notifications as $notification) {
                $this->send($notification);

                if ($notification->getStatus() === pocketlistsNotification::STATUS_OK) {
                    $sentCount++;
                }
            }
        } catch (Exception $ex) {
            pocketlistsHelper::logError('Notifications send error', $ex);
        }

        return $sentCount;
    }

    public function sendDirect()
    {
        $user = pl2()->getUser();

        try {
            $notifications = pl2()->getEntityFactory(pocketlistsNotification::class)->findUnsentForUser();

            $response = [];
            foreach ($notifications as $notification) {
                $this->send($notification);

                if ($notification->getStatus() === pocketlistsNotification::STATUS_OK) {
                    $response = $notification->getContent();
                }
            }
        } catch (Exception $ex) {
            pocketlistsHelper::logError(sprintf('Direct notifications send error for user %d', $user->getId()), $ex);
        }
    }
}

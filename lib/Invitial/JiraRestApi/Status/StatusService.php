<?php
namespace Invitial\JiraRestApi\Status;

use JiraRestApi\JiraClient;

class StatusService extends JiraClient
{
    private $uri = '/status';

    /**
     * get all Statuses.
     *
     * @return array of Status class
     */
    public function getStatuses()
    {
        $ret = $this->exec($this->uri, null);

        $status = $this->json_mapper->mapArray(
            json_decode($ret, false), new \ArrayObject(), '\Invitial\JiraRestApi\Status\Status'
        );

        return $status;
    }
}

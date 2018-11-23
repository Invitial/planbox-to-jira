<?php
namespace Invitial\JiraRestApi\IssueType;

use JiraRestApi\JiraClient;

class IssueTypeService extends JiraClient
{
    private $uri = '/issuetype';

    /**
     * get all IssueTypes.
     *
     * @return array of IssueType class
     */
    public function getIssueTypes()
    {
        $ret = $this->exec($this->uri, null);

        $status = $this->json_mapper->mapArray(
            json_decode($ret, false), new \ArrayObject(), '\Invitial\JiraRestApi\IssueType\IssueType'
        );

        return $status;
    }
}

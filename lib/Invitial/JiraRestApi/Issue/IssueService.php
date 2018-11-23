<?php
namespace Invitial\JiraRestApi\Issue;

use JiraRestApi\Issue\Issue;
use JiraRestApi\Issue\IssueService;

class IssueTypeService extends IssueService
{
    /**
     * Edit an issue.
     *
     * @param $id int ID of Issue
     * @param $issueField object of Issue class
     *
     * @return Issue
     */
    public function edit($id, $issueField)
    {
        $issue = new Issue();

        // serilize only not null field.
        $issue->fields = $issueField;

        $data = json_encode($issue);

        $this->log->addInfo("Edit Issue=\n".$data);

        $ret = $this->exec('/issue/' . $id . '?overrideScreenSecurity=true', $data, 'POST');

        return $this->getIssueFromJSON(json_decode($ret));
    }

}

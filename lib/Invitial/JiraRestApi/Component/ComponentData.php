<?php
namespace Invitial\JiraRestApi\Component;

use JiraRestApi\ClassSerialize;

class ComponentData implements \JsonSerializable
{
    use ClassSerialize;

    public function jsonSerialize()
    {
        $vars = array_filter(get_object_vars($this));

        return $vars;
    }

    public function getName()
    {
        return $this->name;
    }

    public function setName($name)
    {
        $this->name = $name;

        return $this;
    }

    public function getDescription()
    {
        return $this->description;
    }

    public function setDescription($description)
    {
        $this->description = $description;

        return $this;
    }

    public function getProjectKey()
    {
        return $this->project;
    }

    public function setProjectKey($key)
    {
        $this->project = $key;

        return $this;
    }

    public function getProjectId()
    {
        return $this->projectId;
    }

    public function setProjectId($id)
    {
        $this->projectId = $id;

        return $this;
    }

    public function getAssigneeType()
    {
        return $this->assigneeType;
    }

    public function setSssigneeType($assigneeType)
    {
        $this->assigneeType = $assigneeType;

        return $this;
    }

    public function getLeadUserName()
    {
        return $this->leadUserName;
    }

    public function setLeadUserName($leadUserName)
    {
        $this->leadUserName = $leadUserName;

        return $this;
    }

    /** @var string */
    public $name;

    /** @var string */
    public $description = '';

    /** @var string */
    public $project;

    /** @var int|null */
    public $projectId;

    /** @var string */
    public $assigneeType = 'PROJECT_LEAD';

    /** @var string|null */
    public $leadUserName;
}

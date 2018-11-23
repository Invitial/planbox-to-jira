<?php
namespace Invitial\JiraRestApi\IssueType;

class IssueType implements \JsonSerializable
{
    /* @var string */
    public $self;

    /* @var string */
    public $id;

    /* @var string */
    public $name;

    /* @var string */
    public $description;

    public function jsonSerialize()
    {
        return array_filter(get_object_vars($this));
    }
}

<?php
namespace Invitial\JiraRestApi\User;

class User implements \JsonSerializable
{
    /**
     * @var string
     */
    public $expand;

    /* @var string */
    public $self;

    /* @var string */
    public $key;

    /* @var string */
    public $name;

    /* @var string */
    public $displayName;

    /* @var string */
    public $emailAddress;

    /** @var UserData */
    public $fields;

    public function jsonSerialize()
    {
        return array_filter(get_object_vars($this));
    }
}

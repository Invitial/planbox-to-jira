<?php
namespace Invitial\JiraRestApi\User;

use JiraRestApi\ClassSerialize;

class UserData implements \JsonSerializable
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

    public function getEmailAddress()
    {
        return $this->emailAddress;
    }

    public function setEmailAddress($emailAddress)
    {
        $this->emailAddress = $emailAddress;

        return $this;
    }

    public function getDisplayName()
    {
        return $this->displayName;
    }

    public function setDisplayName($displayName)
    {
        $this->displayName = $displayName;

        return $this;
    }

    /** @var string */
    public $name;

    /** @var string */
    public $emailAddress;

    /** @var string */
    public $displayName;
}

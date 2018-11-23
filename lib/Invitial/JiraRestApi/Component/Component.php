<?php
namespace Invitial\JiraRestApi\Component;

class Component implements \JsonSerializable
{
    /**
     * return only if Project query by key(not id).
     *
     * @var string
     */
    public $expand;

    /* @var string */
    public $self;

    /* @var string */
    public $id;

    /* @var string */
    public $key;

    /** @var ComponentData */
    public $fields;

    public function jsonSerialize()
    {
        return array_filter(get_object_vars($this));
    }
}

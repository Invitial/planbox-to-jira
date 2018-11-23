<?php
namespace Invitial\JiraRestApi\Component;

use JiraRestApi\JiraClient;

class ComponentService extends JiraClient
{
    private $uri = '/component';

    /**
     * create new component.
     *
     * @param $componentData object of Component data
     *
     * @return Component The new component
     */
    public function create(ComponentData $componentData)
    {
        $component = new Component();

        // serilize only not null field.
        //$component->fields = $componentData;

        $data = json_encode($componentData);

        $this->log->addInfo("Create Component=\n".$data);

        $ret = $this->exec($this->uri, $data, 'POST');

        return $this->getComponentFromJSON(json_decode($ret));
    }

    public function getComponentFromJSON($json)
    {
        $component = $this->json_mapper->map(
            $json, new Component()
        );

        return $component;
    }
}

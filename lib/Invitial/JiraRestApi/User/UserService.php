<?php
namespace Invitial\JiraRestApi\User;

use JiraRestApi\JiraClient;

class UserService extends JiraClient
{
    private $uri = '/user';

    /**
     * create new user.
     *
     * @param $userData object of User data
     *
     * @return User The new user
     */
    public function create(UserData $userData)
    {
        $data = json_encode($userData);

        $this->log->addInfo("Create User=\n".$data);

        $ret = $this->exec($this->uri, $data, 'POST');

        return $this->getUserFromJSON(json_decode($ret));
    }

    public function getUserFromJSON($json)
    {
        $user = $this->json_mapper->map(
            $json, new User()
        );

        return $user;
    }
}

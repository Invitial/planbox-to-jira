<?php

use Httpful\Http;
use Httpful\Mime;
use Httpful\Request;
use JiraRestApi\Configuration\DotEnvConfiguration;
use JiraRestApi\Issue\IssueService;
use JiraRestApi\Project\ProjectService;
use JiraRestApi\JiraException;

require('../vendor/autoload.php');

date_default_timezone_set('Europe/London');

$dotenv = new Dotenv\Dotenv(__DIR__ . '/../');
$dotenv->load();

$app = new Silex\Application();
$app['debug'] = true;

// Register the monolog logging service
$app->register(new Silex\Provider\MonologServiceProvider(), array(
  'monolog.logfile' => 'php://stderr',
));

// Register view rendering
$app->register(new Silex\Provider\TwigServiceProvider(), array(
    'twig.path' => __DIR__.'/views',
));

$app->register(new Silex\Provider\SessionServiceProvider());

define('PB_BASE', 'https://work.planbox.com/api');

$t = $app['session']->get('planboxToken');

$template = Request::init()
    ->method(Http::POST)            // Alternative to Request::post
    ->expectsJson()                 // Expect JSON responses
    ->sendsType(Mime::FORM)         // Send application/x-www-form-urlencoded
    ->authenticateWith($t, 'pass'); //

// Set it as a template
Request::ini($template);

/*
 * Function to get stories from planbox based on a product_id or a product_id and an iteration_id
 */
$getStories = function($id, $it) {
    $data['product_id']   = $id;
    $data['iteration_id'] = $it;
    $stories = [];

    //if we want stories from all iterations
    if (empty($it)) {
        $its = [];
        $r1 = Request::post(PB_BASE . '/get_product')->body(http_build_query(['product_id' => $id]))->send();
        $i=0;
        foreach ($r1->body->content->iterations as $itt) {
            $its[] = $itt->id;
            $i++;
            //planbox limit is max is 60 iterations at a time
            if($i == 50) {
                $data['iteration_id'] = $its;
                $r2 = Request::post(PB_BASE . '/get_stories')->body(http_build_query($data))->send();
                $stories = array_merge($stories, $r2->body->content);
                $its = [];
                $i = 0;
            }
        }
        if (count($data['iteration_id'])) {
            $data['iteration_id'] = $its;
            $r2 = Request::post(PB_BASE . '/get_stories')->body(http_build_query($data))->send();
            $stories = array_merge($stories, $r2->body->content);
        }
    } else {
        $r2 = Request::post(PB_BASE . '/get_stories')->body(http_build_query($data))->send();
        $stories = $r2->body->content;
    }

    return $stories;
};


/*
 * PLANBOX 1 - INITIATIVES
 */
$app->get('/planbox/{id}', function($id) use($app) {
    if (null == $id) {
        $r1 = Request::get(PB_BASE . '/get_products')->send();

        return $app['twig']->render('planbox/planbox.twig', ['content' => $r1->body->content]);
    } else {
        $id  = $app->escape($id);
        $r1 = Request::post(PB_BASE . '/get_product')->body(http_build_query(['product_id' => $id]))->send();
        $app['session']->set('planProduct', ['id' => $r1->body->content->id, 'name' => $r1->body->content->name]);

        return $app->redirect('/initiative/' . $id);
    }
})->value('id', null);

/*
 * PLANBOX 2 - INITIATIVE DETAILS
 */
$app->get('/initiative/{id}', function($id) use($app) {
    $id   = $app->escape($id);
    $data = ['product_id' => $id];

    $r1 = Request::post(PB_BASE . '/get_product')->body(http_build_query($data))->send();
    $r2 = Request::post(PB_BASE . '/get_resources')->body(http_build_query($data))->send();
    $r3 = Request::post(PB_BASE . '/get_projects')->body(http_build_query($data))->send();

    return $app['twig']->render('planbox/initiative.twig', ['initiative' => $r1->body->content, 'resources' => $r2->body->content, 'projects' => $r3->body->content]);
});

/*
 * PLANBOX 3 - STORIES
 */
$app->get('/stories/{id}/{it}', function($id, $it) use($app, $getStories) {
    $id      = $app->escape($id);
    $it      = $app->escape($it);
    $filter  = ((empty($it)) ? 'Project' : 'Iteration');
    $stories = $getStories($id, $it);

    return $app['twig']->render('planbox/stories.twig', ['filter' => $filter, 'stories' => $stories, 'count' => count($stories)]);
})->value('id', null)->value('it', null);

/*
 * IMPORT
 */
$app->get('/import/{id}/{it}', function($id, $it) use($app, $getStories) {
    $id = $app->escape($id);
    $it = $app->escape($it);
echo '<pre>';
    $json = file_get_contents(__DIR__ . '/../mappings.json');
    $map  = json_decode($json, true);

    //Create JIRA Users
    $r1 = Request::post(PB_BASE . '/get_resources')->body(http_build_query(['product_id' => $id]))->send();
    $users = $r1->body->content;

    $userServ = new \Invitial\JiraRestApi\User\UserService();
    $userStatus = ['ok' => [], 'error' => []];
    $userLookup = [];

    foreach ($users as $pbUser) {
        $un   = explode(' ', strtolower($pbUser->name));
        $un1  = array_pop($un);
        $username = $un[0][0] . $un1;
        $new = [
            'name'     => $pbUser->name,
            'email'    => $pbUser->email,
            'username' => $username
        ];
        $userLookup[$pbUser->id] = $username;

        try {
            $user = new \Invitial\JiraRestApi\User\UserData();
            $user->setName($username);
            $user->setDisplayName($new['name']);
            $user->setEmailAddress($new['email']);
            $newUser = $userServ->create($user);
            $userStatus['ok'][] = $new;
        } catch (JiraException $e) {
            $userStatus['error'][] = $new;
        }
    }

    //Create JIRA Components
    $r2 = Request::post(PB_BASE . '/get_projects')->body(http_build_query(['product_id' => $id]))->send();
    $projects   = $r2->body->content;
    $compServ   = new \Invitial\JiraRestApi\Component\ComponentService();
    $compStatus = ['ok' => [], 'error' => []];
    $compLookup = [];

    foreach ($projects as $project) {
        try {
            $comp = new \Invitial\JiraRestApi\Component\ComponentData();
            $comp->setName($project->name);
            $comp->setDescription($project->description);
            $comp->setProjectId($app['session']->get('jiraProject')['id']);
            $comp->setProjectKey($app['session']->get('jiraProject')['key']);
            $newComp = $compServ->create($comp);
            $compStatus['ok'][] = $project->name;
            $compLookup[$project->id] = $newComp->id;
        } catch (JiraException $e) {
            $compStatus['error'][] = $project->name;
        }
    }

    //Create JIRA Issues
    $stories     = $getStories($id, $it);
    $issueServ   = new \JiraRestApi\Issue\IssueService();
    $issueStatus = ['ok' => [], 'error' => []];
    $issueLookup = [];

    $r2 = Request::post(PB_BASE . '/get_story')->body(http_build_query(['story_id' => 15724779]))->send();
    $stories = [$r2->body->content];

    //issues
    foreach ($stories as $story) {
        try {
            $created  = DateTime::createFromFormat('Y-m-d H:i:s', $story->created_on);
            $resolved = DateTime::createFromFormat('Y-m-d H:i:s', $story->completed_on);
            
            $pbId = ' [#' . $story->id . ']';
            $issueType     = new \JiraRestApi\Issue\IssueType();
            $issueType->id = $map['type'][$story->type];
            $issue = new JiraRestApi\Issue\IssueField();
            $issue->setSummary($story->name . $pbId);
            $issue->setDescription($story->description);
            $issue->setIssueType($issueType);
            $issue->setAssigneeName($userLookup[$story->tasks[0]->resource_id]);
            $issue->setReporterName($userLookup[$story->creator_id]);
            $issue->components = [['id' => $compLookup[$story->project_id]]];
            $issue->status = ['id' => $map['status'][$story->status]];
            $issue->priority = ['id' => $map['importance'][$story->importance]];
            $issue->labels = explode(',', $story->tags);
            $issue->created = $created->format(DATE_ATOM);
            $issue->resolutiondate = $resolved->format(DATE_ATOM);
            $issue->setProjectId($app['session']->get('jiraProject')['id']);
            $issue->setProjectKey($app['session']->get('jiraProject')['key']);

            $issue1 = new \JiraRestApi\Issue\Issue();
            // serilize only not null field.
            $issue1->fields = $issue;
            $data = json_encode($issue1);

            var_dump('JIRA post',json_decode($data));

            $newIssue = $issueServ->create($issue);

            $issueStatus['ok'][] = $story->name;
            $issueLookup[$story->id] = $newIssue->id;
            
            //sub tasks
            foreach ($story->tasks as $task) {
                try {
                    $issueType     = new \JiraRestApi\Issue\IssueType();
                    $issueType->id = $map['type']['sub-task'];
                    $issue         = new JiraRestApi\Issue\IssueField();
                    $issue->setSummary($task->name . $pbId);
                    $issue->setDescription($task->description);
                    $issue->setIssueType($issueType);
                    $issue->setAssigneeName($userLookup[$task->resource_id]);
                    $issue->setReporterName($userLookup[$task->creator_id]);
                    $issue->components = [['id' => $compLookup[$story->project_id]]];
                    $issue->status = $map['status'][$task->status];
                    $issue->priority['id'] = $map['importance'][$task->importance];
                    $issue->labels = explode(',', $task->tags);
                    $issue->created = '';
                    $issue->resolutiondate = '';
                    $issue->setProjectId($app['session']->get('jiraProject')['id']);
                    $issue->setProjectKey($app['session']->get('jiraProject')['key']);
                    $newIssue = $issueServ->create($issue);

                    $issueStatus['ok'][]       = 'Sub-task - ' . $task->name;
                    $issueLookup[$story->name] = $newIssue['id'];
                } catch (JiraException $e) {
                    $issueStatus['error'][] = $story->name;
                }
            }

        } catch (JiraException $e) {
            var_dump($e,$issueStatus, $issueLookup);die;
            $issueStatus['error'][] = $story->name;
        }
        var_dump($story);break;
    }

    $data = [
        'users'      => $userStatus,
        'components' => $compStatus,
        'issues'     => $issueStatus
    ];

    return $app['twig']->render('jira/import.twig', $data);
})->value('id', null)->value('it', null);

/*
 * JIRA 1 - PROJECTS
 */
$app->get('/jira/{id}', function($id) use($app) {
    if (null == $id) {
        $proj = new ProjectService();
        $prjs = $proj->getAllProjects();
        return $app['twig']->render('jira/jira.twig', ['jiraProjects' => $prjs]);
    } else {
        $id   = $app->escape($id);
        $proj = new ProjectService();
        $prj  = $proj->get($id);
        $app['session']->set('jiraProject', ['id' => $prj->id, 'key' => $prj->key, 'name' => $prj->name]);

        return $app->redirect('/planbox');
    }
})->value('id', null);

/*
 * MAPPINGS
 */
$app->get('/mappings', function() use($app) {
    $json = file_get_contents(__DIR__ . '/../mappings.json');
    $map = json_decode($json, true);

    $statusService = new \Invitial\JiraRestApi\Status\StatusService();
    $jStatuses = $statusService->getStatuses();
    $data['jStatuses'] = $jStatuses;
    $data['pStatuses'] = array_keys($map['status']);

    $typeService = new \Invitial\JiraRestApi\IssueType\IssueTypeService();
    $jtypes = $typeService->getIssueTypes();
    $data['jTypes'] = $jtypes;
    $data['pTypes'] = array_keys($map['type']);

    return $app['twig']->render('mappings.twig', $data);
});

/*
 * LOGIN - PLANBOX
 */
$app->get('/login/planbox', function() use($app) {
    $app['monolog']->addDebug('Logging in to Planbox.');

    $data = ['email' => $_ENV['PLANBOX_USER'], 'password' => $_ENV['PLANBOX_PASS']];

    Request::resetIni();
    $r1 = Request::post(PB_BASE . '/auth')
        ->expectsJson()
        ->sendsType(Mime::FORM)
        ->body(http_build_query($data))
        ->send();

    if ('ok' == $r1->body->code) {
        $app['session']->set('planboxToken', $r1->body->content->access_token);
        return $app->redirect('/jira');
    } else {
        return 'Planbox login failed, please check the credentials and try again.';
    }
});

/*
 * LOGIN - JIRA
 */
$app->get('/login/jira', function() use($app) {
    $app['monolog']->addDebug('Logging in to JIRA.');

    try {
        $iss  = new IssueService(new DotEnvConfiguration(__DIR__ . '/../'));
        $proj = new ProjectService();
        $prjs = $proj->getAllProjects();
        return $app->redirect('/login/planbox');
    } catch (JiraException $e) {
        return 'JIRA login failed, please check the credentials and try again.';
    }
});

/*
 * START
 */
$app->get('/start', function() use($app) {
    return $app->redirect('/login/jira');
});


$app->get('/', function() use($app) {
    return $app['twig']->render('index.twig');
});

$app->run();

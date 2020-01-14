<?php

require 'vendor/autoload.php';

use JiraRestApi\Issue\IssueService;
use JiraRestApi\JiraException;
use Pimple\Container;
use \Gnello\Mattermost\Driver;

/**
 * @param IssueService $issueService
 * @param string $type
 * @param string $project
 * @return string
 * @throws JiraException
 * @throws JsonMapper_Exception
 */
function buildMessage(IssueService $issueService, string $type, string $project): string
{
    $jql = sprintf('"Story status" = "Ready for %s" AND project = "%s"', $type, $project);
    $issueSearchResult = $issueService->search($jql);

    if ($issueSearchResult->total > 0) {
        $message = sprintf('We have **%s** issues for **%s**', $issueSearchResult->total, $type) . PHP_EOL . PHP_EOL;
        $message .=
            '| Issue | Summary |'. PHP_EOL .
            '| :--------- |:--------------- |'. PHP_EOL;
        foreach ($issueSearchResult->issues as $issue) {
            $message .= sprintf(
                '| [%s](https://smartbox.atlassian.net/browse/%s) | %s |' . PHP_EOL ,
                $issue->key,
                $issue->key,
                $issue->fields->summary
            );
        }
    } else {
        $message = sprintf('There is nothing for **%s**', $type) . PHP_EOL;
    }

    return $message;
}

try {
    $env = Dotenv\Dotenv::create(__DIR__);
    $env->load();
    $env->required(['MATTERMOST_URL', 'MATTERMOST_USER', 'MATTERMOST_PASSWORD', 'MATTERMOST_CHANNEL_ID', 'JIRA_PROJECT']);

    $issueService = new IssueService();
    $project = getenv('JIRA_PROJECT');
    $message = buildMessage($issueService, 'Estimation', $project);
    $message .= buildMessage($issueService, 'Refinement', $project);


    $container = new Container([
        'driver' => [
            'url' => getenv('MATTERMOST_URL'),
            'login_id' => getenv('MATTERMOST_USER'),
            'password' => getenv('MATTERMOST_PASSWORD'),
        ],
    ]);

    $driver = new Driver($container);
    $result = $driver->authenticate();
    if (200 === $result->getStatusCode()) {
        $result = $driver->getPostModel()->createPost([
            'channel_id' => getenv('MATTERMOST_CHANNEL_ID'),
            'message' => $message,
        ]);

        echo $message;
    } else {
        echo 'HTTP ERROR ' . $result->getStatusCode();
    }

} catch (JsonMapper_Exception | JiraException $exception) {
    print('Jira Error Occurred! ' . $exception->getMessage());
}

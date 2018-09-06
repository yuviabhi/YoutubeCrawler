<?php
require_once 'init-crawler.php';

// Sample php code for channels.list
function channelsListById($service, $part, $params)
{
    $params = array_filter($params);
    $response = $service->channels->listChannels($part, $params);
    return json_encode($response);
}

$channel_list = channelsListById($service, 'snippet,contentDetails,statistics,topicDetails,status,brandingSettings,contentOwnerDetails,localizations', array(
    'id' => 'UCJjC1hn78yZqTf0vdTC6wAQ'
));
print_r($channel_list);

?>

<?php
//Get all playlists(names only)  in a channel by channelID

require_once 'init-crawler.php';

// Sample php code for playlists.list
function playlistsListByChannelId($service, $part, $params)
{
    $params = array_filter($params);
    $response = $service->playlists->listPlaylists($part, $params);
    return json_encode($response);
}

$playlist_names_info = playlistsListByChannelId($service, 'snippet, contentDetails, status, player', array(
    'channelId' => 'UCX6b17PVsYBQ0ip5gyeme-Q',
    'maxResults' => 50
));
print_r($playlist_names_info);

?>
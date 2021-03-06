<?php
/**
 * handles youtube crawling
 *
 * @author abhisek
 */

function channelsListById($service, $part, $params) {
    $params = array_filter($params);
    $response = $service->channels->listChannels($part, $params);
    return json_encode($response);
}

 
function playlistsListByChannelId($service, $part, $params) {
    $params = array_filter($params);
    $response = $service->playlists->listPlaylists($part, $params);
    return json_encode($response);
}



/**
 * Retreive all Playlist by Channel ID (with pagination)
 *
 * @param string $service
 * @param string $channel_id
 * @return JsonSerializable $playlist_names_info
 */
function retrieveAllPlaylistsListByChannelId($service , $channel_id) {
    $playlist_names_info = playlistsListByChannelId($service, 'snippet, contentDetails, status, player', array(
        'channelId' => $channel_id,
        'maxResults' => 50
    ));
    $playlist_names_info = json_decode($playlist_names_info);
    $nextPageToken = $playlist_names_info -> nextPageToken;
    // print_r($nextPageToken);
    
    while ($nextPageToken != NULL) {
        
        $next_page_lists = playlistsListByChannelId($service, 'snippet, contentDetails, status, player', array(
            'channelId' => $channel_id,
            'maxResults' => 50, 'pageToken'=>$nextPageToken));
        $next_page_lists = json_decode($next_page_lists);
        $playlist_names_info -> items = array_merge($playlist_names_info -> items , $next_page_lists -> items);
        
        $nextPageToken = $next_page_lists -> nextPageToken;
        //     print_r($nextPageToken);
    }
    return json_encode($playlist_names_info);
}

function playlistItemsListByPlaylistId($service, $part, $params)
{
    $params = array_filter($params);
    $response = $service->playlistItems->listPlaylistItems($part, $params);
    return json_encode($response);
}



/**
 * Retreive all Playlist items by Playlist ID (with pagination)
 *
 * @param string $service
 * @param string $playlist_id
 * @return JsonSerializable $playlist_all_videos
 */
function retrieveAllPlaylistItemsListByPlaylistId($service, $playlist_id) {
    $playlist_all_videos = playlistItemsListByPlaylistId($service, 'snippet,contentDetails', array(
        'maxResults' => 50,
        'playlistId' => $playlist_id
    ));
    
    $playlist_all_videos = json_decode($playlist_all_videos);
    $nextPageToken = $playlist_all_videos -> nextPageToken;
    // print_r($nextPageToken);
    
    while ($nextPageToken != NULL) {
        
        $next_page_lists = playlistItemsListByPlaylistId($service, 'snippet,contentDetails', array(
            'maxResults' => 50,
            'playlistId' => $playlist_id, 'pageToken'=>$nextPageToken));
        $next_page_lists = json_decode($next_page_lists);
        $playlist_all_videos -> items = array_merge($playlist_all_videos -> items , $next_page_lists -> items);
        
        $nextPageToken = $next_page_lists -> nextPageToken;
        //     print_r($nextPageToken);
    }
    return json_encode($playlist_all_videos);
}


function videosByChannelId($service, $part, $params) {
    $params = array_filter($params);
    $response = $service->search->listSearch($part,$params);
    return json_encode($response);
}



/**
 * Retreive all videos by Channel ID (with pagination)
 *
 * @param string $service
 * @param string $channelID
 * @return JsonSerializable $video_lists
 */
function retrieveAllVideosByChannelID($service, $channelID) {
    $video_lists = videosByChannelId($service,'snippet',
        array('channelId' => $channelID,  'maxResults' => 50));
    
    $video_lists = json_decode($video_lists);
//     $prevPageToken = $video_lists -> prevPageToken;
    $nextPageToken = $video_lists -> nextPageToken;
//     echo sizeof($video_lists -> items);
//     echo "\n";
//     print_r($nextPageToken);
    
    while ($nextPageToken != NULL) {        
        
        $next_page_lists = videosByChannelId($service,'snippet',
            array('channelId' => $channelID, 'maxResults' => 50, 'pageToken'=>$nextPageToken));
        $next_page_lists = json_decode($next_page_lists);
        $video_lists -> items = array_merge($video_lists -> items , $next_page_lists -> items);
        
        $nextPageToken = $next_page_lists -> nextPageToken;
//         echo sizeof($video_lists -> items);
//         echo "\n";
//         print_r($nextPageToken);
        
    }
    $next_page_lists = videosByChannelId($service,'snippet',
        array('channelId' => $channelID,  'maxResults' => 50, 'pageToken'=>$nextPageToken));
    $next_page_lists = json_decode($next_page_lists);
    $video_lists -> items = array_merge($video_lists -> items , $next_page_lists -> items);
    $nextPageToken = $next_page_lists -> nextPageToken;
//     echo sizeof($video_lists -> items);
//     echo "\n";
//     print_r($nextPageToken);
    
    
    return json_encode($video_lists);
}





/**
 * Retreive info of a video by Video ID 
 *
 * @param string $service
 * @param string $channelID
 * @return JsonSerializable $video_lists
 */
 function retrieveVideoInfosById($service, $channelID) {
 	return (videosListById($service,
    'id,snippet,contentDetails,statistics,liveStreamingDetails,localizations,recordingDetails,status,topicDetails,player', 
    array('id' => $channelID)));
 
 }
 
function videosListById($service, $part, $params) {
    $params = array_filter($params);
    $response = $service->videos->listVideos(
        $part,
        $params
    );

    return json_encode($response);
}
?>

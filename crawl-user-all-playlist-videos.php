<?php
//Get all videos(playlist-wise) of a user by username and save to disk according to username/playlists
//Get all non-playlist videos of a user as well and save as Videos-Without-Playlist.json

require_once 'init-crawler.php';
require_once 'crawler-methods.php';

// $username = 'crashcourse';

if($argc>1) {
    if (defined('STDIN')) {
        $username = $argv[1];
    }    
} else {
    print("Enter username as 1st arguement");
    exit();
}

$channel_list = channelsListById($service, 'snippet,contentDetails', array(
    'forUsername' => $username
));

$channel_list = json_decode($channel_list);

$channel_id = $channel_list->items[0]->id;
//  print_r($channel_id);

 $savepath = __DIR__ . '/data/' . $username . "/";

 
// SAVING VIDEOS WHICH ARE UPLOADED WITHOUT PLAYLISTS
$all_direct_uploaded_video_lists = retrieveAllVideosByChannelID($service, $channel_id);
if (! file_exists($savepath)) {
    mkdir($savepath, 0777, true);
}
if (file_put_contents($savepath . "Videos-Without-Playlist.json", $all_direct_uploaded_video_lists)) {
    echo "Videos-Without-Playlist.json successfully saved under " . $savepath;    
} else
    echo "\nError saving Videos-Without-Playlist.json in " . $savepath ;




    
// SAVING VIDEOS WHICH ARE UPLOADED WITH PLAYLISTS
$playlist_names_info = retrieveAllPlaylistsListByChannelId($service , $channel_id);
// print_r($playlist_names_info);
$playlist_names_info = json_decode($playlist_names_info);
$playlist_all_items = $playlist_names_info -> items;
$playlist_counter = 0;

foreach ($playlist_all_items as $each_playlist) {
    // print($each_playlist -> id);
    $playlist_id = $each_playlist->id;
    
    $playlist_all_videos = retrieveAllPlaylistItemsListByPlaylistId($service, $playlist_id);
    // print_r(json_encode($each_playlist));

    $channel_title = $each_playlist->snippet->channelTitle;
    $channel_title = str_replace("/", " ", $channel_title);
    $playlist_title = $each_playlist->snippet->title;
    $playlist_title = str_replace("/", " ", $playlist_title);
    $filename = $playlist_title . ".json";
    // print $channel_title;
    // print $playlist_title;

    if (! file_exists($savepath)) {
        mkdir($savepath, 0777, true);
    }
    if (file_put_contents($savepath . $filename, $playlist_all_videos)) {
        // echo 'Data successfully saved : ' . $save_path;
        $playlist_counter = $playlist_counter + 1;
    } else
        echo "\nError saving in " . $savepath . $filename;
    
    // break;
}
echo "\nTotal " . $playlist_counter . " playlists saved";

?>
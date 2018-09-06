<?php
//Get all videos in a platlists by playlistID

require_once 'init-crawler.php';
require_once 'crawler-methods.php';

// $playlistId = 'PLEbnTDJUr_IeRM8lEyzv0J_3ZxfYvO0SP';
if($argc>1) {
    if (defined('STDIN')) {
        $playlistId = $argv[1];
    }
} else {
    print("Enter playlistID as 1st arguement");
    exit();
}

$playlist_all_items = retrieveAllPlaylistItemsListByPlaylistId($service, $playlistId);


print_r($playlist_all_items);

?>
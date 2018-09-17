<?php
// Crawl info of a video by video-id

require_once 'init-crawler.php';
require_once 'crawler-methods.php';

if($argc>1) {
    if (defined('STDIN')) {
        $video_id = $argv[1];
    }
} else {
    print("Enter video-id as 1st arguement");
    exit();
}

$video_info = retrieveVideoInfosById($service, $video_id);


print_r($video_info);

?>

<?php
// Get all videos in a channel by channelID 
// Applicable in those channels where no playlist-wise uploaded done.


require_once 'init-crawler.php';
require_once 'crawler-methods.php';

// $channelID = 'UCpRCG3gGtWqieJe-LGmi93w';

if($argc>1) {
    if (defined('STDIN')) {
        $channelID = $argv[1];
    }
} else {
    print("Enter channelID as 1st arguement");
    exit();
}


$all_direct_uploaded_video_lists = retrieveAllVideosByChannelID($service, $channelID);

print_r($all_direct_uploaded_video_lists);




// UCMkybZyI_B-xgkLQo_eCQ_w
// UCpRCG3gGtWqieJe-LGmi93w


//UCJjC1hn78yZqTf0vdTC6wAQ = Ravindrababu Ravula
//UCpRCG3gGtWqieJe-LGmi93w = Teach Engineering
//psLUrTjk21Y = The Benefits of Inclined Planes: Heave Ho!

// Search API : https://www.googleapis.com/youtube/v3/search?order=date&part=snippet&channelId={channelID}&maxResults={1-50}&key={your_key}

?>
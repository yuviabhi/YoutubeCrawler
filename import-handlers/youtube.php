<?php
// set project path
$project_path = dirname(__DIR__);

// import libraries
require_once $project_path . '/classes/utilities-common.php';


// define source specific class
class YoutubeImportHandler extends ImportHandler
{
    // this array stores all video-ids of all playlists in a channel
    public static $videoids_all_playlists = array();
    
    // this array stores all video-ids of a single playlist
    protected $videoids_single_playlist = array();

    public function process_src_document($src_document)
    {
        // print_r(($src_document)); //exit(0);
        $youtube_id = null;

        /*
         * if(isset($src_document->snippet->playlistId)){
         * $youtube_id .= $src_document->snippet->playlistId;
         * }
         */

        if (isset($src_document->id->videoId)) {
            $youtube_id = $src_document->id->videoId;
        } else if (isset($src_document->contentDetails)) {
            $youtube_id = $src_document->contentDetails->videoId;
        }

        array_push($this->videoids_single_playlist, (string)$youtube_id);
        
        if (in_array($youtube_id, YoutubeImportHandler::$videoids_all_playlists)) {
            return;
        }
        array_push(YoutubeImportHandler::$videoids_all_playlists, (string)$youtube_id);

        

        if ($youtube_id == null) {
            print_r("Youtube ID is NULL. Ignored : " . $src_document->etag . PHP_EOL);
        } else {

            $ndli_doc_id = $this->generate_ndli_document_id($youtube_id);
            $document = new NDLIDocument($ndli_doc_id);

            /*
             * if($src_document->etag){
             * $document->add_metadata($src_document->etag, "");
             * }
             *
             * if($src_document->id){
             * $document->add_metadata($src_document->id, "");
             * }
             *
             * if($src_document->kind){
             * $document->add_metadata($src_document->kind, "");
             * }
             */

            foreach ($src_document->snippet as $field => $values) {
                if ($field == "resourceId") {
                    foreach ($values as $key => $value) {
                        $document->add_metadata($field . "_" . $key, $value);
                    }
                } else if ($field == "thumbnails") {
                    $document->add_thumbnail_url($values->default->url);
                } else {
                    if ($values) {
                        $document->add_metadata($field, $values);
                    }
                }
            }
            if (isset($src_document->contentDetails)) {
                foreach ($src_document->contentDetails as $field => $values) {
                    if ($values) {
                        $document->add_metadata($field, $values);
                    }
                }
            }

            // FIXME contentDetails
            $this->add_document($document);
        }
    }

    public function process_all_documents($data_src)
    {
        $playlists = FileSystemUtils::get_filelist($data_src);
        $c = 0;
        foreach ($playlists as $playlist) {
            $c++;
            print_r($data_src . "/" . $playlist . PHP_EOL);
            $data = json_decode(file_get_contents($data_src . "/" . $playlist));
            // exit(0);

            
            if(!$playlist == "_Videos-Without-Playlist.json"){  // for playlist videos
                
                $playlist_id = null;
                if (count($data->items)!=0) {
                    try {
                        $playlist_id = $data->items[0]->snippet->playlistId;
                    } catch(Exception $e){
                        print_r("Can't find playlist id at : ".$data->items);
                    }
                }
                if ($playlist_id != null) {
                    
                    $ndli_doc_id = $this->generate_ndli_document_id($playlist_id);
                    $document_p = new NDLIDocument($ndli_doc_id);
                    
                    foreach ($data as $key => $value) {
                        if ($key == "pageInfo") {
                            foreach ($value as $k => $v) {
                                $document_p->add_metadata($key . "_" . $k, $v);
                            }
                        } else if ($key == "items"){
                            foreach ($data->items as $src_document) {
                                $this->process_src_document($src_document);
                            }
                        } else if ($value) {
                            $document_p->add_metadata($key, $value);
                        }
                    }
                    
                    
                    
                    $id_array = $this->videoids_single_playlist;
                    $document_p->add_parts($id_array);
                    $this->add_document($document_p);
                    $this->videoids_single_playlist = array();
                    
                    
                }
            } else { // for non-playlist videos
                
                foreach ($data->items as $src_document) {
                    $this->process_src_document($src_document);
                }
                
            }
            
            
//             if ($c == 3) {
//             break;
//             }
        }
        
        

        // // store
        $source_details = new DataSource($this->src_id);
        $source_details->store_documents($this->get_documents()); // store a set of data
        $source_details->index_all_documents(); // commit
        
        print_r($c. " playlists processed...");
    }

    protected function generate_ndli_document_id(string $youtube_id)
    {
        return preg_replace("/(\W+)/", "_", $youtube_id);
    }
}

// ////////////////////////////////////////////////////////////////////////////////////////////////



// setting up the data source
$src_id = "youtube";


// remove existing data
$source_details = new DataSource($src_id);
if($source_details->remove_all_douments()== true){
    echo 'Deleted all sources' . PHP_EOL;
} else {
    echo 'Unable to delete all sources' . PHP_EOL;
}
exit(0);



$sources = FileSystemUtils::get_filelist( $project_path . "/data");
rsort($sources);
foreach($sources as $s) {
    $data_src = $project_path . "/data/" . $s;
    echo "Processing ... ".$data_src.PHP_EOL;
    // processing
    $youtube_import_handler = new YoutubeImportHandler($src_id);
    
    $youtube_import_handler->process_all_documents($data_src);
    // print_r(array_count_values(YoutubeImportHandler::$videoid_array));
}



?>
	



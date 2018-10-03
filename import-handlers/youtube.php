<?php
// set project path
$project_path = dirname(__DIR__);

// import libraries
require_once $project_path . '/classes/utilities-common.php';

// setting up the data source
$src_id = "youtube";
$data_src = $project_path . "/data/AMNHorg";

// remove existing data
 $source_details = new DataSource($src_id);
 $source_details->remove_all_douments();
 echo 'Deleted '.PHP_EOL;
// exit(0);


// define source specific class
class YoutubeImportHandler extends ImportHandler
{
    
    public static $id_arr = array();

    public function process_src_document($src_document){
        // print_r(($src_document)); //exit(0);
        $youtube_id = null;
        
//         if(isset($src_document->snippet->playlistId)){
//             $youtube_id .= $src_document->snippet->playlistId;
//         }

        
        if (isset($src_document->id->videoId)) {
        
            $youtube_id = $src_document->id->videoId;
        
        } else if (isset($src_document->contentDetails)) {
            
            $youtube_id = $src_document->contentDetails->videoId;
        
        }
               
        
        
        if ($youtube_id == null) {
            print_r("Youtube ID is NULL. Ignored : " . $src_document->etag . PHP_EOL);
        } 
        
        else {
            
            $ndli_doc_id = $this->generate_ndli_document_id($youtube_id);
            $document = new NDLIDocument($ndli_doc_id);

            foreach ($src_document->snippet as $field => $values) {
                if ($field == "thumbnails") {
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
            
            //array_push(YoutubeImportHandler::$id_arr, $youtube_id);
        }
    }

    public function process_all_documents($data_src){
        $playlists = FileSystemUtils::get_filelist($data_src);

        foreach ($playlists as $playlist) {
            $data = json_decode(file_get_contents($data_src . "/" . $playlist));
            print_r($data_src . "/" . $playlist . PHP_EOL);
            // exit(0);
            foreach ($data->items as $src_document) {
                // print_r($src_document);
                $this->process_src_document($src_document);
            }
        }

        // // store
        $source_details = new DataSource($this->src_id);
        $source_details->store_documents($this->get_documents()); // store a set of data
        $source_details->index_all_documents(); // commit

    }

    protected function generate_ndli_document_id(string $youtube_id){
        return preg_replace("/(\W+)/", "_", $youtube_id);
    }
}




// ////////////////////////////////////////////////////////////////////////////////////////////////


// processing
$youtube_import_handler = new YoutubeImportHandler($src_id);

$youtube_import_handler->process_all_documents($data_src);
//print_r( array_count_values(YoutubeImportHandler::$id_arr));

?>
	



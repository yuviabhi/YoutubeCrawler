<?php
/**
 *
 * @author subhayan
 *
 */
class AIPReader {
}

/**
 *
 * @author subhayan
 *        
 */
class SIPReader {
}

/**
 *
 * @author subhayan
 *        
 */
class CSVReader {
}
class EPrints {
	private $base_url = null;
	private $prefix = null;
	private $structure = array ();
	private $special_keys = array (
			"documents",
			"files",
			"related_url"
	);
	private $person_fields = array (
			"contributors",
			"creators",
			"editors",
			"fellow_name",
			"gscholar",
			"guides",
			"icrisatcreators",
			"iicb-creators",
			"supervisors",
			"thesis_guide"
	);

	/**
	 *
	 * @param string $base_url
	 */
	public function __construct(string $base_url, $prefix) {
		$this->base_url = $base_url;
		$this->prefix = $prefix;
	}

	/**
	 */
	public function extract_subject_mapping() {
		$subject_map = array ();

		$curl_options = array (
				CURLOPT_FOLLOWLOCATION => true,
				CURLOPT_RETURNTRANSFER => true,
				CURLOPT_SSL_VERIFYHOST => false,
				CURLOPT_TIMEOUT => 60,
				CURLOPT_HTTPHEADER => array (
						"User-Agent:Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/59.0.3071.115 Safari/537.36"
				)
		);

		if ($proxy = Configuration::get_proxy ()) {
			$curl_options [CURLOPT_PROXY] = $proxy;
		}
		$curl = curl_init ( $this->base_url . "/view/subjects/" );
		curl_setopt_array ( $curl, $curl_options );
		$response = curl_exec ( $curl );
		curl_close ( $curl );

		libxml_use_internal_errors ( true );
		$doc = new DOMDocument ();
		$doc->loadHTML ( $response, LIBXML_BIGLINES | LIBXML_NOWARNING | LIBXML_ERR_NONE );
		libxml_clear_errors ();
		$xpath = new DOMXPath ( $doc );

		$nodes = $xpath->query ( "//div[@class='ep_view_menu']//a", $doc );

		foreach ( $nodes as $node ) {
			$code = str_replace ( "=5F", "_", str_replace ( ".html", "", $node->getAttribute ( "href" ) ) );
			$subject_map [$code] = $node->nodeValue;
		}

		return $subject_map;
	}
	/**
	 *
	 * @param boolean $enable_print
	 * @return array
	 */
	public function extract_structure($enable_print = true) {
		$curl_options = array (
				CURLOPT_FOLLOWLOCATION => true,
				CURLOPT_RETURNTRANSFER => true,
				CURLOPT_SSL_VERIFYHOST => false,
				CURLOPT_TIMEOUT => 60,
				CURLOPT_HTTPHEADER => array (
						"User-Agent:Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/59.0.3071.115 Safari/537.36"
				)
		);

		if ($proxy = Configuration::get_proxy ()) {
			$curl_options [CURLOPT_PROXY] = $proxy;
		}
		$curl = curl_init ( $this->base_url . "/view/year/" );
		curl_setopt_array ( $curl, $curl_options );
		$response = curl_exec ( $curl );
		curl_close ( $curl );

		libxml_use_internal_errors ( true );
		$doc = new DOMDocument ();
		$doc->loadHTML ( $response, LIBXML_BIGLINES | LIBXML_NOWARNING | LIBXML_ERR_NONE );
		libxml_clear_errors ();
		$xpath = new DOMXPath ( $doc );

		$nodes = $xpath->query ( "(//table[@class='ep_view_cols ep_view_cols_3']|//div[@class='ep_view_menu'])//a", $doc );

		foreach ( $nodes as $node ) {
			$year = trim ( $node->nodeValue );
			if (preg_match ( "/specified/i", $year )) {
				$year = "NULL";
			}
			$obj = new stdClass ();
			$obj->name = $year;
			$obj->handle = $this->prefix . "_AUTO/" . $year;
			$obj->type = "collection";
			if ($enable_print) {
				echo $year . PHP_EOL;
			}
			array_push ( $this->structure, $obj );
		}

		return $this->structure;
	}

	/**
	 *
	 * @param string $year
	 * @param stdClass $subjects
	 * @param int $success_count
	 * @param int $fail_count
	 */
	public function crawl_items_by_year(string $year, stdClass $subjects, int &$success_count = 0, int &$fail_count = 0) {
		$curl_options = array (
				CURLOPT_FOLLOWLOCATION => true,
				CURLOPT_RETURNTRANSFER => true,
				CURLOPT_SSL_VERIFYHOST => false,
				CURLOPT_TIMEOUT => 60,
				CURLOPT_HTTPHEADER => array (
						"User-Agent:Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/59.0.3071.115 Safari/537.36"
				)
		);
		if ($proxy = Configuration::get_proxy ()) {
			$curl_options [CURLOPT_PROXY] = $proxy;
		}
		$curl = curl_init ( $this->base_url . "/cgi/exportview/year/$year/JSON/$year.js" );
		curl_setopt_array ( $curl, $curl_options );
		$response = curl_exec ( $curl );
		curl_close ( $curl );

		$eprints_items = json_decode ( $response );

		if (! $response || json_last_error ()) {
			echo "ERROR: while processing year: $year";
			exit ( 0 );
		}

		$exception_keys = array_merge ( $this->special_keys, $this->person_fields );

		$document_set = new NDLIDocumentSet ();
		foreach ( $eprints_items as $item ) {
			$document = new NDLIDocument ( $item->eprintid );

			// generic keys
			foreach ( $item as $key => $values ) {
				if (! in_array ( $key, $exception_keys )) {
					$document->add_metadata ( $key, $values );
				}
			}

			// processing related_url
			if (isset ( $item->related_url )) {
				foreach ( $item->related_url as $url ) {
					if (isset ( $url->type ) && isset ( $url->url )) {
						$document->add_metadata ( "related_url." . $url->type, $url->url );
					} else if (is_iterable ( $url ) || is_object ( $url )) {
						$document->add_metadata ( "related_url.OBJECT", json_encode ( $url, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) );
					} else {
						$document->add_metadata ( "related_url.OBJECT", $url );
					}
				}
			}

			// processing persons
			foreach ( $this->person_fields as $field ) {
				if (isset ( $item->{$field} )) {
					foreach ( $item->{$field} as $person ) {
						if (is_object ( $person )) {
							if ($name = $this->process_person_names ( $person )) {
								$document->add_metadata ( $field, $name );
							}
						} else {
							$document->add_metadata ( $field, $person );
						}
					}
				}
			}

			// processing editors
			// if (isset ( $item->guides )) {
			// foreach ( $item->guides as $guide ) {
			// if ($name = $this->process_person_names ( $guide )) {
			// $document->add_metadata ( "guide", $name );
			// }
			// }
			// }

			// processing subjects
			if (isset ( $item->subjects )) {
				foreach ( $item->subjects as $subject_code ) {
					$subjects->$subject_code = isset ( $subjects->$subject_code ) ? $subjects->$subject_code : "UNKNOWN";
					$document->add_metadata ( "subject.name", $subjects->$subject_code );
				}
			}

			// processing assets
			if (isset ( $item->documents )) {
				$full_text_status = isset ( $item->full_text_status ) ? $item->full_text_status : false;
				foreach ( $item->documents as $doc ) {
					$doc_metadata = array ();
					foreach ( $doc as $field => $value ) {
						if ($field == "files" || $field == "docid" || $field == "eprintid") {
							continue;
						}
						$doc_metadata [$field] = $value;
					}

					$docid = isset ( $doc->docid ) ? $doc->docid : null;

					if (isset ( $doc->files )) {
						foreach ( $doc->files as $file ) {
							$fileid = isset ( $file->fileid ) ? $file->fileid : null;
							$id = implode ( ".", array_values ( array_filter ( array (
									$docid,
									$fileid
							) ) ) );
							$asset = new NDLIAsset ( $id, $this->generate_ndli_asset_id ( $item->eprintid, $id ) );

							foreach ( $file as $field => $value ) {
								$asset->set_asset_metadata ( "file." . $field, $value );
							}
							foreach ( $doc_metadata as $field => $value ) {
								if ($field == "relation") {
									foreach ( $value as $relation ) {
										$rel_name = str_replace ( "http://eprints.org/relation/", "", $relation->type );
										$rel_value = str_replace ( "/id/document/", "", $relation->uri );
										$asset->set_asset_metadata ( "doc." . $field . "." . $rel_name, $rel_value );
									}
								} else {
									$asset->set_asset_metadata ( "doc." . $field, $value );
								}
							}
							// extracting thumbnail link
							if ($full_text_status == "public" && isset ( $doc->placement ) && isset ( $file->filename )) {
								$asset->set_asset_metadata ( "file.thumbnail.url", $this->base_url . "/" . $item->eprintid . "/" . $doc->placement . ".haspreviewThumbnailVersion/" . $file->filename );
							}
							$document->add_asset ( $asset );
						}
					} else {
						// $document->add_metadata ( "files.UNKNOWN", pg_escape_string ( json_encode ( $doc ) ) );
					}
				}
			}

			$document_set->add_document ( $document->get_document (), $year );
		}

		$db = new database ();
		$success_count = $document_set->store_as_raw ( $this->prefix, $db->get_connection () );
		$db->close_database ();
		$fail_count = $document_set->size () - $success_count;
	}
	private function process_person_names(stdClass $person) {
		$name = null;
		if (isset ( $person->name->family ) && isset ( $person->name->given )) {
			$name = $person->name->family . ", " . $person->name->given;
		} else if (isset ( $person->name->given )) {
			$name = $person->name->given;
		} else if (isset ( $person->name->family )) {
			$name = $person->name->family;
		}
		if (isset ( $person->id ) && $person->id) {
			$name .= "||" . $person->id;
		}
		return $name;
	}
	private function generate_ndli_asset_id(string $src_item_id, string $src_asset_id) {
		return $src_item_id . "_" . $src_asset_id;
	}
}
class XMLUI extends DSpace {
	/**
	 *
	 * @param string $case
	 * @param string $url
	 */
	public function crawl_collection(string $collection_handle) {
		// $doc = new DOMDocument ();
		// $curl_options = array (
		// CURLOPT_FOLLOWLOCATION => true,
		// CURLOPT_POST => true,
		// CURLOPT_POSTFIELDS => array (
		// "type" => "title",
		// "sort_by" => 1,
		// "order" => "ASC",
		// "rpp" => $this->lot_size,
		// "update" => "Update"
		// ),
		// CURLOPT_RETURNTRANSFER => true,
		// CURLOPT_SSL_VERIFYHOST => false,
		// CURLOPT_TIMEOUT => 60,
		// CURLOPT_HTTPHEADER => array (
		// "User-Agent:Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/59.0.3071.115 Safari/537.36"
		// )
		// );
		// if ($proxy = Configuration::get_proxy ()) {
		// $curl_options [CURLOPT_PROXY] = $proxy;
		// }
		// $curl = curl_init ( $this->base_url . "/" . $handle . "/browse" );
		// curl_setopt_array ( $curl, $curl_options );
		// $response = curl_exec ( $curl );
		// curl_close ( $curl );

		// $doc->loadHTML ( $response, LIBXML_BIGLINES | LIBXML_NOWARNING | LIBXML_ERR_NONE );
		// $xpath = new DOMXPath ( $doc );

		// $items = $xpath->query ( "//div[@id='aspect_artifactbrowser_ConfigurableBrowse_div_browse-by-title-results']/ul/li/div/div/a" );
		// foreach ( $items as $item ) {
		// $handle = preg_replace ( "/(.*?handle)\/(.*)/", "$2", $item->getAttribute ( "href" ) );
		// try {
		// $this->store_item_details ( $handle, $folder );
		// } catch ( Exception $e ) {
		// echo "ERROR: error while processing Item: " . $handle . PHP_EOL;
		// exit ( 0 );
		// }
		// }
	}
	private function get_structure($itemlist, $xpath) {
		// $list = array ();
		// foreach ( $itemlist as $item ) {
		// // print_r($item);
		// $nodes = $xpath->query ( "./div/div/a", $item );
		// $name = trim ( $nodes->item ( 0 )->nodeValue );
		// $handle = preg_replace ( "/[\/](xmlui|jspui)[\/]handle[\/]/", "", $nodes->item ( 0 )->getAttribute ( "href" ) );
		// $obj = new stdClass ();

		// $obj->name = $name;
		// $obj->handle = $handle;
		// $children = $xpath->query ( "./ul/li", $item );

		// if ($children->length) {
		// $obj->type = "community";
		// $obj->children = $this->get_structure_xmlui ( $children, $xpath );
		// } else {
		// $obj->type = "collection";
		// $obj->children = array ();
		// }
		// array_push ( $list, $obj );
		// }

		// return $list;
	}
}
class JSPUI extends DSpace {
	/**
	 *
	 * @param string $collection_handle
	 * @param string $prefix
	 */
	public function crawl_collection(string $collection_handle, string $prefix) {
		$document_set = new NDLIDocumentSet ();
		$this->prefix = $prefix;

		$curl_options = array (
				CURLOPT_FOLLOWLOCATION => true,
				CURLOPT_RETURNTRANSFER => true,
				CURLOPT_SSL_VERIFYHOST => false,
				CURLOPT_TIMEOUT => 120,
				CURLOPT_HTTPHEADER => array (
						"User-Agent:Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/59.0.3071.115 Safari/537.36"
				)
		);
		if ($proxy = Configuration::get_proxy ()) {
			$curl_options [CURLOPT_PROXY] = $proxy;
		}

		$query = array (
				"type" => "dateissued", // dateaccessioned
				"sort_by" => 2, // 3
				"order" => "DESC",
				"offset" => 0,
				"rpp" => $this->lot_size,
				"submit_browse" => "Update"
		);

		while ( true ) {
			$curl = curl_init ( $this->base_url . "/handle/" . $collection_handle . "/browse?" . http_build_query ( $query ) );
			curl_setopt_array ( $curl, $curl_options );
			$response = curl_exec ( $curl );
			curl_close ( $curl );
			// /
			$doc = new DOMDocument ();
			libxml_use_internal_errors ( true );
			$doc->loadHTML ( $response, LIBXML_BIGLINES | LIBXML_NOWARNING | LIBXML_ERR_NONE );
			$xpath = new DOMXPath ( $doc );
			$items = $xpath->query ( "//td/a" );
			$count = count ( $items );

			foreach ( $items as $item ) {
				$handle = preg_replace ( "/(.*?handle)\/(.*)/", "$2", $item->getAttribute ( "href" ) );
				try {
					$document_set->add_document ( $this->process_item ( $handle )->get_document () );
				} catch ( Exception $e ) {
					echo "ERROR: error while processing Item: " . $handle . PHP_EOL;
					exit ( 0 );
				}
			}
			// TODO extract assets
			$db = new database ();
			$document_set->store_as_raw ( $prefix, $db->get_connection () );
			$db->close_database ();
			if ($count != $this->lot_size) {
				break;
			}
			$query ["offset"] += $count;
		}
	}
	public function extract_structure() {
		$curl_options = array (
				CURLOPT_FOLLOWLOCATION => true,
				CURLOPT_RETURNTRANSFER => true,
				CURLOPT_SSL_VERIFYHOST => false,
				CURLOPT_TIMEOUT => 60,
				CURLOPT_HTTPHEADER => array (
						"User-Agent:Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/59.0.3071.115 Safari/537.36"
				)
		);

		if ($proxy = Configuration::get_proxy ()) {
			$curl_options [CURLOPT_PROXY] = $proxy;
		}
		$curl = curl_init ( $this->base_url . "/community-list" );
		curl_setopt_array ( $curl, $curl_options );
		$response = curl_exec ( $curl );
		curl_close ( $curl );

		libxml_use_internal_errors ( true );
		$doc = new DOMDocument ();
		$doc->loadHTML ( $response, LIBXML_BIGLINES | LIBXML_NOWARNING | LIBXML_ERR_NONE );
		libxml_clear_errors ();
		$xpath = new DOMXPath ( $doc );

		$nodes = $xpath->query ( "//main[@id='content']/div[@class='container']/ul/li", $doc );
		if ($nodes->length) {
			return $this->get_structure ( $nodes, $xpath );
		} else {
			$nodes = $xpath->query ( "//div[@id='aspect_artifactbrowser_CommunityBrowser_div_comunity-browser']/ul/li", $doc );
			return $this->get_structure ( $nodes, $xpath );
		}
	}
	private function get_structure($itemlist, $xpath) {
		$list = array ();
		foreach ( $itemlist as $item ) {

			$nodes = $xpath->query ( "./div[@class='media-body']/h4/a", $item );
			$name = trim ( $nodes->item ( 0 )->nodeValue );
			$handle = preg_replace ( "/[\/](xmlui|jspui)[\/]handle[\/]/", "", $nodes->item ( 0 )->getAttribute ( "href" ) );
			$obj = new stdClass ();

			$obj->name = $name;
			$obj->handle = $handle;
			$children = $xpath->query ( "./div[@class='media-body']/ul[@class='media-list']/li", $item );

			if ($children->length) {
				$obj->type = "community";
				$obj->children = $this->get_structure ( $children, $xpath );
			} else {
				$obj->type = "collection";
				$obj->children = array ();
				echo $handle . PHP_EOL;
			}
			array_push ( $list, $obj );
		}

		return $list;
	}
	private function process_item(string $handle) {
		$document = new NDLIDocument ( $handle );

		$curl_options = array (
				CURLOPT_FOLLOWLOCATION => true,
				CURLOPT_RETURNTRANSFER => true,
				CURLOPT_SSL_VERIFYHOST => false,
				CURLOPT_TIMEOUT => 60,
				CURLOPT_HTTPHEADER => array (
						"User-Agent:Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/59.0.3071.115 Safari/537.36"
				)
		);
		if ($proxy = Configuration::get_proxy ()) {
			$curl_options [CURLOPT_PROXY] = $proxy;
		}
		$curl = curl_init ( $this->base_url . "/handle/" . $handle . "?mode=full" );
		curl_setopt_array ( $curl, $curl_options );
		$response = curl_exec ( $curl );
		curl_close ( $curl );
		// /
		$doc = new DOMDocument ();
		libxml_use_internal_errors ( true );
		$doc->loadHTML ( $response, LIBXML_BIGLINES | LIBXML_NOWARNING | LIBXML_ERR_NONE );
		$xpath = new DOMXPath ( $doc );

		// metadata
		$items = $xpath->query ( "//table[contains(@class,'itemDisplayTable')]//tr" );

		for($i = 1; $i < count ( $items ) - 1; $i ++) {
			$item = $items [$i];
			$field = trim ( $item->childNodes->item ( 0 )->textContent );
			if (in_array ( $field, self::AUTOGEN_FIELDS )) {
				continue;
			}
			$value = trim ( $item->childNodes->item ( 1 )->textContent );
			$lang = trim ( $item->childNodes->item ( 2 )->textContent );
			$lang = strlen ( $lang ) > 1 ? $lang : null;
			$document->add_metadata ( $field, $value );
		}

		// collection
		if ($items [$i]->childNodes->length >= 2) {
			$parent_handle = $items [$i]->childNodes->item ( 1 )->childNodes->item ( 0 )->attributes->getNamedItem ( "href" )->nodeValue;
			$parent_handle = preg_replace ( "/(.*?\/handle\/)(.*)/", "$2", $parent_handle );
			$document->add_metadata ( "ndli.parent.name", trim ( $items [$i]->childNodes->item ( 1 )->textContent ) );
			$document->set_parent_id ( $parent_handle );
		}

		// assets
		$items = $xpath->query ( "//table[@class='table panel-body']/tr" );
		$headings = array ();
		$meta = ($items [0])->childNodes;

		for($i = 0; $i < $meta->length; $i ++) {
			if (strlen ( $head = trim ( $meta->item ( $i )->textContent ) ) > 2) {
				array_push ( $headings, $head );
			}
		}
		for($i = 1; $i < count ( $items ); $i ++) {
			$item = $items [$i];
			$asset = new NDLIAsset ( $handle . "_" . $i, $handle . "_" . $i, NDLIAsset::ASSET_TYPE_ORIGINAL );
			$meta = $item->childNodes;
			for($i = 0; $i < $meta->length; $i ++) {
				if (array_key_exists ( $i, $headings )) {
					$asset->set_asset_metadata ( $headings [$i], trim ( $meta->item ( $i )->textContent ) );
					$href = $xpath->query ( "./a", $meta->item ( $i ) );
					if ($href->length) {
						$asset->set_asset_metadata ( "uri", $href->item ( 0 )->attributes->getNamedItem ( "href" )->nodeValue );
					}
				}
			}
			$document->add_asset ( $asset );
		}

		// TODO generate hash
		// //
		// //
		// //
		// //
		// //
		// //
		// //

		return $document;
	}
}
/**
 *
 * @author subhayan
 *        
 */
class DSpace {
	public $ui = null;
	protected $base_url = null;
	protected $prefix = null;
	protected $structure = null;
	protected $lot_size = 1000;
	const AUTOGEN_FIELDS = array (
			"dc.date.accessioned",
			"dc.date.available"
		// "dc.identifier.uri"
	);

	/**
	 *
	 * @param string $base_url
	 */
	public function __construct(string $base_url) {
		$this->base_url = $base_url;
	}

	/**
	 */
	private function extract_structure() {
		$curl_options = array (
				CURLOPT_FOLLOWLOCATION => true,
				CURLOPT_RETURNTRANSFER => true,
				CURLOPT_SSL_VERIFYHOST => false,
				CURLOPT_TIMEOUT => 60,
				CURLOPT_HTTPHEADER => array (
						"User-Agent:Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/59.0.3071.115 Safari/537.36"
				)
		);

		if ($proxy = Configuration::get_proxy ()) {
			$curl_options [CURLOPT_PROXY] = $proxy;
		}
		$curl = curl_init ( $this->base_url . "/community-list" );
		curl_setopt_array ( $curl, $curl_options );
		$response = curl_exec ( $curl );
		curl_close ( $curl );

		libxml_use_internal_errors ( true );
		$doc = new DOMDocument ();
		$doc->loadHTML ( $response, LIBXML_BIGLINES | LIBXML_NOWARNING | LIBXML_ERR_NONE );
		libxml_clear_errors ();
		$xpath = new DOMXPath ( $doc );

		$nodes = $xpath->query ( "//main[@id='content']/div[@class='container']/ul/li", $doc );
		if ($nodes->length) {
			$this->structure = $this->get_structure ( $nodes, $xpath );
		} else {
			$nodes = $xpath->query ( "//div[@id='aspect_artifactbrowser_CommunityBrowser_div_comunity-browser']/ul/li", $doc );
			$this->structure = $this->get_structure ( $nodes, $xpath );
		}
		return $this->structure;
	}

	/**
	 *
	 * @param string $src_id
	 */
	protected function generate_ndli_id(string $src_id) {
	}
}
?>
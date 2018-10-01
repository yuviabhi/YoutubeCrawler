<?php
/**
 *
 * @author subhayan
 *
 */
abstract class ExportHandler {
	const TYPE_RAW = "type_raw";
	const TYPE_MAPPED = "type_mapped";
	protected $src_id;
	protected $type;
	protected $documents = array ();
	const DOCUMENT_LIMIT = 50000;
	/**
	 *
	 * @param string $src_id
	 */
	public function __construct(string $src_id, string $type = self::TYPE_MAPPED) {
		$this->src_id = $src_id;
		$this->type = $type;
	}

	/**
	 *
	 * @param int $num_rows
	 * @param int $offset
	 * @return int
	 */
	protected function fetch_documents(int $num_rows, int $offset) {
		if ($num_rows > self::DOCUMENT_LIMIT) {
			$num_rows = self::DOCUMENT_LIMIT;
		}
		$db = new Database ();
		$data_source = new DataSourceExtended ( $this->src_id, $db->get_connection () );
		if ($this->type == self::TYPE_RAW) {
			$this->documents = $data_source->get_items_metadata_raw ( array (), $num_rows, $offset );
		} elseif ($this->type == self::TYPE_MAPPED) {
			$this->documents = $data_source->get_items_metadata_mapped ( array (), $num_rows, $offset );
		}
		$db->close_database ();
		return count ( $this->documents );
	}
	abstract public function write_documents();
}

/**
 *
 * @author subhayan
 *        
 */
class CSVExportHandler extends ExportHandler {
	private $output_folder_path = null;
	private $filename_prefix = null;
	private $headers = array (
			"_id",
			"_collection_id"
	);
	private $num_rows = 0;
	public function write_documents() {
		$offset = 0;
		$counter = 0;
		while ( true ) {
			if ($doc_count = $this->fetch_documents ( $this->num_rows, $offset )) {
				$csv_file_pointer = fopen ( $this->output_folder_path . "/" . $this->filename_prefix . ($counter ++) . ".csv", "w" );
				fputcsv ( $csv_file_pointer, $this->headers, ',', '"' );
				foreach ( $this->documents as $document ) {
					$metadata = json_decode ( $document ["meta_value"] );
					$row = array_fill_keys ( $this->headers, null );
					$row ["_id"] = $document ["ndli_uniq_id"];
					$row ["_collection_id"] = $document ["ndli_collection_id"];
					foreach ( $metadata as $field => $values ) {
						$row [$field] = implode ( "||", $values );
					}
					fputcsv ( $csv_file_pointer, $row, ',', '"' );
				}
				fclose ( $csv_file_pointer );
			}
			if ($doc_count < $this->num_rows) {
				break;
			}
			$offset += $this->num_rows;
		}
	}

	/**
	 *
	 * @param string $src_id
	 * @param string $output_folder
	 * @param string $filename_prefix
	 * @param int $num_rows
	 * @param string $type
	 */
	public function __construct(string $src_id, string $output_folder, string $filename_prefix, int $num_rows, string $type) {
		// database
		$db = new Database ();
		$data_source = new DataSourceExtended ( $src_id, $db->get_connection () );
		if ($type == ExportHandler::TYPE_RAW) {
			$headers = $data_source->get_items_metadata_fields_raw ();
		} elseif ($type == ExportHandler::TYPE_MAPPED) {
			$headers = $data_source->get_items_metadata_fields_mapped ();
		}
		$db->close_database ();

		$this->src_id = $src_id;
		$this->output_folder_path = $output_folder;
		$this->filename_prefix = $filename_prefix;
		$this->headers = array_merge ( $this->headers, $headers );
		$this->num_rows = $num_rows;
		$this->type = $type;

		// writing headers
		file_put_contents ( $this->output_folder_path . "/" . $this->filename_prefix . "headers.csv", implode ( PHP_EOL, $this->headers ) );
	}
}

/**
 *
 * @author subhayan
 *        
 */
class JSONExportHandler extends ExportHandler {
	private $output_folder_path = null;
	private $filename_prefix = null;
	private $meta_fields = array ();
	private $num_rows = 0;
	public function write_documents() {
		$offset = 0;
		$counter = 0;
		while ( true ) {
			if ($doc_count = $this->fetch_documents ( $this->num_rows, $offset )) {
				$documents = array ();
				foreach ( $this->documents as $document ) {
					$metadata = json_decode ( $document ["meta_value"] );
					$obj = new stdClass ();
					$obj->{"_id"} = $document ["ndli_uniq_id"];
					$obj->{"_collection_id"} = $document ["ndli_collection_id"];
					$obj->metadata = array_fill_keys ( $this->meta_fields, array () );
					foreach ( $metadata as $field => $values ) {
						$obj->metadata [$field] = $values;
					}
					array_push ( $documents, $obj );
				}
				file_put_contents ( $this->output_folder_path . "/" . $this->filename_prefix . ($counter ++) . ".json", json_encode ( $documents, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) );
			}
			if ($doc_count < $this->num_rows) {
				break;
			}
			$offset += $this->num_rows;
		}
	}
	/**
	 *
	 * @param string $output_folder
	 */
	public function __construct(string $src_id, string $output_folder, string $filename_prefix, int $num_rows, string $type) {
		// database
		$db = new Database ();
		$data_source = new DataSourceExtended ( $src_id, $db->get_connection () );
		if ($type == ExportHandler::TYPE_RAW) {
			$this->meta_fields = $data_source->get_items_metadata_fields_raw ();
		} elseif ($type == ExportHandler::TYPE_MAPPED) {
			$this->meta_fields = $data_source->get_items_metadata_fields_mapped ();
		}
		$db->close_database ();

		$this->src_id = $src_id;
		$this->output_folder_path = $output_folder;
		$this->filename_prefix = $filename_prefix;
		$this->num_rows = $num_rows;
		$this->type = $type;
	}
}

/**
 *
 * @author subhayan
 *        
 */
class SIPExportHandler extends ExportHandler {
	private $output_folder_path = null;
	private $num_rows = 1000;
	/**
	 *
	 * @param string $src_id
	 * @param string $output_folder
	 */
	public function __construct(string $src_id, string $output_folder) {
		$this->src_id = $src_id;
		$this->output_folder_path = $output_folder;
		$this->type = ExportHandler::TYPE_MAPPED;
	}
	public function write_documents() {
		$offset = 0;
		while ( true ) {
			if ($doc_count = $this->fetch_documents ( $this->num_rows, $offset )) {
				foreach ( $this->documents as $document ) {
					$this->create_sip_document ( $document ["ndli_uniq_id"], $document ["ndli_collection_id"], json_decode ( $document ["meta_value"] ) );
				}
			}
			if ($doc_count < $this->num_rows) {
				break;
			}
			$offset += $this->num_rows;
		}
	}
	private function create_sip_document(string $document_id, string $collection_id, stdClass $metadata) {
		$path = $this->output_folder_path . "/COLL@" . str_replace ( "/", "_", $collection_id ) . "/ITEM@" . str_replace ( "/", "_", $document_id );
		FileSystemUtils::create_dir ( $path );

		touch ( $path . "/contents" );
		file_put_contents ( $path . "/handle", $document_id );

		$schemas = array ();
		foreach ( $metadata as $field => $values ) {
			$meta = explode ( ".", $field );
			$schema = $meta [0];
			if (! array_key_exists ( $schema, $schemas )) {
				$schemas [$schema] = array ();
			}
			$schemas [$schema] [$field] = $values;
		}
		foreach ( $schemas as $schema => $fields ) {
			$filename = ($schema == "dc" ? "dublin_core.xml" : "metadata_$schema.xml");
			$xml = new SimpleXMLElement ( '<dublin_core/>' );
			$xml->addAttribute ( "schema", $schema );
			foreach ( $fields as $field => $values ) {
				$components = explode ( ".", $field );
				$element = $components [1];
				$qualifier = @$components [2] ? $components [2] : null;
				foreach ( $values as $value ) {
					$tag = $xml->addChild ( "dcvalue", htmlspecialchars ( $value ) );
					$tag->addAttribute ( "element", $element );
					if ($qualifier) {
						$tag->addAttribute ( "qualifier", $qualifier );
					}
				}
			}
			// $xml->saveXML ( $file );
			$dom = new DOMDocument ( '1.0' );
			$dom->preserveWhiteSpace = false;
			$dom->formatOutput = true;
			$dom->loadXML ( $xml->asXML () );
			$dom->save ( $path . "/" . $filename );
		}
	}
}

/**
 *
 * @see DSpace(v5.x)-AIP-Format https://wiki.duraspace.org/display/DSDOC5x/DSpace+AIP+Format
 * @see DSpace(v6.x)-AIP-Format https://wiki.duraspace.org/display/DSDOC6x/DSpace+AIP+Format
 * @abstract The MODS metadata is included within your AIP to support interoperability.<br/>
 *           It provides a way for other systems to interact with or ingest the AIP without needing to understand the DIM Schema.<br/>
 *           You may choose to disable MODS if you wish, however this may decrease the likelihood that you'd be able to easily ingest your AIPs into a non-DSpace system (unless that non-DSpace system is able to understand the DIM schema).<br/>
 *           When restoring/ingesting AIPs, DSpace will always first attempt to restore DIM descriptive metadata.<br/>
 *           Only if no DIM metadata is found, will the MODS metadata be used during a restore.
 * @author susmita
 *        
 */
class AIPExportHandler extends ExportHandler {
	private $output_folder_path = null;
	private $num_rows = 1000;
	private $structure = null;
	private $src_name = null;
	private $dspace_version = null;
	private $community_schema_file = "/assets/templates/AIP_mets_schema_community.xml";
	private $collection_schema_file = "/assets/templates/AIP_mets_schema_collection.xml";
	private $item_schema_file = "/assets/templates/AIP_mets_schema_item.xml";
	/**
	 *
	 * @param string $src_id
	 * @param string $output_folder
	 */
	public function __construct(string $src_id, string $output_folder) {
		$this->src_id = $src_id;
		$this->output_folder_path = $output_folder;
		$this->type = ExportHandler::TYPE_MAPPED;
		$this->dspace_version = Configuration::get_dspace_export_version ();

		$db = new Database ();
		$data_source = new DataSourceExtended ( $src_id, $db->get_connection () );
		$source_details = $data_source->get_source_details ();
		$db->close_database ();

		$this->src_name = $source_details ["src_name"];
		$this->structure = json_decode ( $source_details ["structure"] ) ?: null;
	}
	public function write_documents() {
		$root_handle = $this->src_id . "/ROOT";

		// creating root
		$list_child_collection = array ();
		$list_child_sub_community = array ();
		foreach ( $this->structure as $nested_node ) {
			if ($nested_node->type == "COMMUNITY") {
				array_push ( $list_child_sub_community, $nested_node->id );
			} else {
				array_push ( $list_child_collection, $nested_node->id );
			}
		}
		$metadata = array (
				"dc.title" => array (
						$this->src_name
				)
		);
		$this->create_community ( $root_handle, null, $metadata, $list_child_collection, $list_child_sub_community );

		// creating communities and collections
		$this->export_as_aip ( $this->structure, $root_handle );

		// creating items
		$offset = 0;
		while ( true ) {
			if ($doc_count = $this->fetch_documents ( $this->num_rows, $offset )) {
				// print_r ( $this->fetch_documents ( $this->num_rows, $offset ) );
				foreach ( $this->documents as $document ) {
					$this->create_item ( $document ["ndli_uniq_id"], $document ["ndli_collection_id"], json_decode ( $document ["meta_value"], true ) );
				}
			}
			if ($doc_count < $this->num_rows) {
				break;
			}
			$offset += $this->num_rows;
		}
	}
	/**
	 *
	 * @param array $nodes
	 * @param string $parent_handle
	 */
	private function export_as_aip(array $nodes, string $parent_handle) {
		foreach ( $nodes as $node ) {
			$metadata = array (
					"dc.title" => array (
							$node->name
					)
			);

			if ($node->type == "COMMUNITY") {
				if ($node->child) {
					$list_child_collection = array ();
					$list_child_sub_community = array ();
					foreach ( $node->child as $nested_node ) {
						if ($nested_node->type == "COMMUNITY") {
							array_push ( $list_child_sub_community, $nested_node->id );
						} else {
							array_push ( $list_child_collection, $nested_node->id );
						}
					}
					$this->create_community ( $node->id, $parent_handle, $metadata, $list_child_collection, $list_child_sub_community );
					$this->export_as_aip ( $node->child, $node->id );
				}
			} else {
				$this->create_collection ( $node->id, $parent_handle, $metadata );
			}
		}
	}

	/**
	 *
	 * @param string $community_handle_id
	 * @param string $parent_community_handle_id
	 * @param array $metadata
	 * @param array $list_child_collection
	 * @param array $list_child_sub_community
	 * @return boolean
	 */
	private function create_community(string $community_handle_id, string $parent_community_handle_id = null, array $metadata, array $list_child_collection = array(), array $list_child_sub_community = array()) {
		$a = explode ( "/", $community_handle_id );
		$handle_prefix = $a [0];
		$handle_hyphen = str_replace ( "/", "-", $community_handle_id );

		$dc_title = array_key_exists ( "dc.title", $metadata ) ? current ( $metadata ["dc.title"] ) : null; // take only first value
		$dc_identifier_uri = array_key_exists ( "dc.identifier.uri", $metadata ) ? $metadata ["dc.identifier.uri"] : array ();

		$dom = new DOMDocument ();
		$dom->formatOutput = true;
		$xml_content = str_replace ( 'xmlns="http://www.loc.gov/METS/" ', "", file_get_contents ( dirname ( __DIR__ ) . "/" . $this->community_schema_file ) );
		$dom->loadXML ( $xml_content );
		$xpath = new DOMXPath ( $dom );

		$xpath->registerNamespace ( "dim", "http://www.dspace.org/xmlns/dspace/dim" );
		$xpath->registerNamespace ( "premis", "http://www.loc.gov/standards/premis" );
		$xpath->registerNamespace ( "mods", "http://www.loc.gov/mods/v3" );

		$tag_mets = $dom->getElementsByTagName ( "mets" )->item ( 0 );
		$tag_mets->setAttribute ( "ID", "DSpace_COMMUNITY_" . $handle_hyphen );
		$tag_mets->setAttribute ( "OBJID", "hdl:" . $community_handle_id );
		$tag_mets->setAttribute ( "xmlns", "http://www.loc.gov/METS/" );

		// metsHdr
		$tag_metsHdr = $dom->getElementsByTagName ( "metsHdr" )->item ( 0 );
		foreach ( $tag_metsHdr as $m ) {

			$agent = $xpath->query ( $m->getNodePath () . '/agent' );
			foreach ( $agent as $a ) {
				if ($a->getAttribute ( "OTHERTYPE" ) == "DSpace Archive" && $a->getAttribute ( "ROLE" ) == "CUSTODIAN") {
					$na = $xpath->query ( $a->getNodePath () . '/name' );
					foreach ( $na as $nm ) {
						$nm->appendChild ( $dom->createTextNode ( $handle_prefix . "/0" ) );
					}
				}
				if ($a->getAttribute ( "OTHERTYPE" ) == "DSpace Software" && $a->getAttribute ( "ROLE" ) == "CREATOR") {
					$name = $xpath->query ( $a->getNodePath () . '/name' );
					foreach ( $name as $nm1 ) {
						$nm1->appendChild ( $dom->createTextNode ( $this->dspace_version ) );
					}
				}
			}
		}

		$dmdSec = $xpath->query ( '//dmdSec' );
		foreach ( $dmdSec as $d ) {
			// dmdSec_1
			if ($d->getAttribute ( "ID" ) == "dmdSec_1") {
				$mods = $xpath->query ( $d->getNodePath () . '/mdWrap/xmlData/mods:mods' );
				$this->configure_dmdSec_1 ( $dom, $xpath, $mods, $dc_title, $dc_identifier_uri );
			}
			// dmdSec_2
			if ($d->getAttribute ( "ID" ) == "dmdSec_2") {
				$mdWrap = $xpath->query ( $d->getNodePath () . '/mdWrap' );
				foreach ( $mdWrap as $w ) {
					if ($w->getAttribute ( "MDTYPE" ) == "OTHER" && $w->getAttribute ( "OTHERMDTYPE" ) == "DIM") {
						$dim = $xpath->query ( $w->getNodePath () . '/xmlData/dim:dim' );
						// foreach ( $dim as $dm ) {
						$this->configure_dmdSec_2 ( $dom, $xpath, $dim, $metadata, $community_handle_id );
						// }
					}
				}
			}
		}
		// amdSec
		$amdSec = $xpath->query ( '//amdSec' );
		foreach ( $amdSec as $a ) {
			// dmdSec_1
			if ($a->getAttribute ( "ID" ) == "amd_3") {
				$sourceMD = $xpath->query ( $a->getNodePath () . '/sourceMD' );
				foreach ( $sourceMD as $s ) {
					if ($s->getAttribute ( "ID" ) == "sourceMD_10") {
						$mdWrap = $xpath->query ( $s->getNodePath () . '/mdWrap' );
						foreach ( $mdWrap as $mw ) {
							if ($mw->getAttribute ( "OTHERMDTYPE" ) == "AIP-TECHMD") {
								$dims = $xpath->query ( $mw->getNodePath () . '/xmlData/dim:dim' );
								foreach ( $dims as $dim ) {

									$elem = $dom->createElement ( "dim:field", "hdl:" . $community_handle_id );
									$elem->setAttribute ( "mdschema", "dc" );
									$elem->setAttribute ( "element", "identifier" );
									$elem->setAttribute ( "qualifier", "uri" );
									$dim->appendChild ( $elem );

									if ($parent_community_handle_id != null) {
										$elem1 = $dom->createElement ( "dim:field", "hdl:" . $parent_community_handle_id );
									} else {
										$elem1 = $dom->createElement ( "dim:field", "hdl:" . $handle_prefix . "/0" );
									}

									$elem1->setAttribute ( "mdschema", "dc" );
									$elem1->setAttribute ( "element", "relation" );
									$elem1->setAttribute ( "qualifier", "isPartOf" );
									$dim->appendChild ( $elem1 );
								}
							}
						}
					}
				}
			}
		}

		// structMap , add list of child info
		$cnt = "13";
		$structMap = $xpath->query ( '//structMap' );
		foreach ( $structMap as $sm ) {
			if ($sm->getAttribute ( "ID" ) == "struct_11" && $sm->getAttribute ( "LABEL" ) == "DSpace Object" && $sm->getAttribute ( "TYPE" ) == "LOGICAL") {
				$divs = $xpath->query ( $sm->getNodePath () . '/div' );
				foreach ( $divs as $div ) {
					if ($div->getAttribute ( "DMDID" ) == "dmdSec_1 dmdSec_2" && $div->getAttribute ( "ADMID" ) == "amd_3") {

						if (sizeof ( $list_child_sub_community ) > 0) {
							foreach ( $list_child_sub_community as $each_community ) {
								$child_handle_id = $each_community;
								$child_handle_hyphen = str_replace ( "/", "-", $child_handle_id );

								$elem2 = $dom->createElement ( "div" );
								$elem2->setAttribute ( "ID", "div_" . $cnt );
								$elem2->setAttribute ( "TYPE", "DSpace COMMUNITY" );
								$cnt ++;

								$elem3 = $dom->createElement ( "mptr" );
								$elem3->setAttribute ( "ID", "mptr_" . $cnt );
								$elem3->setAttribute ( "LOCTYPE", "HANDLE" );
								$elem3->setAttribute ( "xlink:type", "simple" );
								$elem3->setAttribute ( "xlink:href", $child_handle_id );
								$elem2->appendChild ( $elem3 );
								$cnt ++;

								$elem4 = $dom->createElement ( "mptr" );
								$elem4->setAttribute ( "ID", "mptr_" . $cnt );
								$elem4->setAttribute ( "LOCTYPE", "URL" );
								$elem4->setAttribute ( "xlink:type", "simple" );
								$elem4->setAttribute ( "xlink:href", "COMMUNITY@" . $child_handle_hyphen . ".zip" );
								$elem2->appendChild ( $elem4 );
								$cnt ++;

								$div->appendChild ( $elem2 );
							}
						}
						if (sizeof ( $list_child_collection ) > 0) {
							foreach ( $list_child_collection as $each_collection ) {
								// /**** FIXME ***///
								$child_handle_id = $each_collection;
								$child_handle_hyphen = str_replace ( "/", "-", $child_handle_id );

								$elem2 = $dom->createElement ( "div" );
								$elem2->setAttribute ( "ID", "div_" . $cnt );
								$elem2->setAttribute ( "TYPE", "DSpace COLLECTION" );
								$cnt ++;

								$elem3 = $dom->createElement ( "mptr" );
								$elem3->setAttribute ( "ID", "mptr_" . $cnt );
								$elem3->setAttribute ( "LOCTYPE", "HANDLE" );
								$elem3->setAttribute ( "xlink:type", "simple" );
								$elem3->setAttribute ( "xlink:href", $child_handle_id );
								$elem2->appendChild ( $elem3 );
								$cnt ++;

								$elem4 = $dom->createElement ( "mptr" );
								$elem4->setAttribute ( "ID", "mptr_" . $cnt );
								$elem4->setAttribute ( "LOCTYPE", "URL" );
								$elem4->setAttribute ( "xlink:type", "simple" );
								$elem4->setAttribute ( "xlink:href", "COLLECTION@" . $child_handle_hyphen . ".zip" );
								$elem2->appendChild ( $elem4 );
								$cnt ++;

								$div->appendChild ( $elem2 );
							}
						}
					}
				}
			}
		}

		$elem5 = $dom->createElement ( "structMap" );
		$elem5->setAttribute ( "ID", "struct_" . $cnt );
		$elem5->setAttribute ( "LABEL", "Parent" );
		$elem5->setAttribute ( "TYPE", "LOGICAL" );
		$cnt ++;
		$tag_mets->appendChild ( $elem5 );
		$elem6 = $dom->createElement ( "div" );
		$elem6->setAttribute ( "ID", "div_" . $cnt );
		$elem6->setAttribute ( "LABEL", "Parent of this DSpace Object" );
		$elem6->setAttribute ( "TYPE", "AIP Parent Link" );
		$elem5->appendChild ( $elem6 );
		$cnt ++;

		$elem7 = $dom->createElement ( "mptr" );
		$elem7->setAttribute ( "ID", "mptr_" . $cnt );
		$elem7->setAttribute ( "LOCTYPE", "HANDLE" );
		$elem7->setAttribute ( "xlink:type", "simple" );

		if ($parent_community_handle_id != null) { // in case of ROOT community parent id should be null
			$elem7->setAttribute ( "xlink:href", $parent_community_handle_id );
		} else {
			$elem7->setAttribute ( "xlink:href", $handle_prefix . "/0" );
		}

		$elem6->appendChild ( $elem7 );

		$zip = new ZipArchive ();
		if ($parent_community_handle_id == null) {
			$res = $zip->open ( $this->output_folder_path . "/ROOT@" . $handle_hyphen . ".zip", ZipArchive::CREATE );
		} else {
			$res = $zip->open ( $this->output_folder_path . "/COMMUNITY@" . $handle_hyphen . ".zip", ZipArchive::CREATE );
		}

		if ($res === true) {
			$zip->addFromString ( "mets.xml", $dom->saveXML () );
			$zip->close ();
			return true;
		}
		return false;
	}

	/**
	 *
	 * @param string $collection_handle_id
	 * @param string $parent_community_handle_id
	 * @param array $metadata
	 * @return boolean
	 */
	private function create_collection(string $collection_handle_id, string $parent_community_handle_id, array $metadata) {
		$db = new Database ();
		$data_source = new DataSourceExtended ( $this->src_id, $db->get_connection () );
		$list_child_items = $data_source->get_child_items ( $collection_handle_id );
		$db->close_database ();

		$a = explode ( "/", $collection_handle_id );
		$handle_prefix = $a [0];
		$handle_hyphen = str_replace ( "/", "-", $collection_handle_id );

		$dc_title = array_key_exists ( "dc.title", $metadata ) ? current ( $metadata ["dc.title"] ) : null; // take only first value
		$dc_identifier_uri = array_key_exists ( "dc.identifier.uri", $metadata ) ? $metadata ["dc.identifier.uri"] : array ();

		$dom = new DOMDocument ();
		$xml_content = str_replace ( 'xmlns="http://www.loc.gov/METS/" ', "", file_get_contents ( dirname ( __DIR__ ) . "/" . $this->collection_schema_file ) );
		$dom->loadXML ( $xml_content );

		$xpath = new DOMXPath ( $dom );
		$xpath->registerNamespace ( "dim", "http://www.dspace.org/xmlns/dspace/dim" );
		$xpath->registerNamespace ( "premis", "http://www.loc.gov/standards/premis" );
		$xpath->registerNamespace ( "rights", "http://cosimo.stanford.edu/sdr/metsrights" );
		$xpath->registerNamespace ( "mods", "http://www.loc.gov/mods/v3" );

		$tag_mets = $dom->getElementsByTagName ( "mets" )->item ( 0 );
		$tag_mets->setAttribute ( "ID", "DSpace_COLLECTION_" . $handle_hyphen );
		$tag_mets->setAttribute ( "OBJID", "hdl:" . $collection_handle_id );
		$tag_mets->setAttribute ( "xmlns", "http://www.loc.gov/METS/" );

		$tag_metsHdr = $dom->getElementsByTagName ( "metsHdr" )->item ( 0 );

		foreach ( $tag_metsHdr as $m ) {
			$agent = $xpath->query ( $m->getNodePath () . '/agent' );
			foreach ( $agent as $a ) {
				if ($a->getAttribute ( "OTHERTYPE" ) == "DSpace Archive" && $a->getAttribute ( "ROLE" ) == "CUSTODIAN") {
					$name = $xpath->query ( $a->getNodePath () . '/name' );
					foreach ( $name as $nm ) {
						$nm->appendChild ( $dom->createTextNode ( $handle_prefix . "/0" ) );
					}
				}

				if ($a->getAttribute ( "OTHERTYPE" ) == "DSpace Software" && $a->getAttribute ( "ROLE" ) == "CREATOR") {
					$name = $xpath->query ( $a->getNodePath () . '/name' );
					foreach ( $name as $nm ) {
						$nm->appendChild ( $dom->createTextNode ( $this->dspace_version ) );
					}
				}
			}
		}

		$dmdSec = $xpath->query ( '//dmdSec' );
		foreach ( $dmdSec as $d ) {
			// dmdSec_1
			if ($d->getAttribute ( "ID" ) == "dmdSec_1") {
				$mods = $xpath->query ( $d->getNodePath () . '/mdWrap/xmlData/mods:mods' );
				$this->configure_dmdSec_1 ( $dom, $xpath, $mods, $dc_title, $dc_identifier_uri );
			}

			// dmdSec_2
			if ($d->getAttribute ( "ID" ) == "dmdSec_2") {
				$mdWrap = $xpath->query ( $d->getNodePath () . '/mdWrap' );
				foreach ( $mdWrap as $w ) {
					if ($w->getAttribute ( "MDTYPE" ) == "OTHER" && $w->getAttribute ( "OTHERMDTYPE" ) == "DIM") {
						$dim = $xpath->query ( $w->getNodePath () . '/xmlData/dim:dim' );
						// foreach ( $dim as $dm ) {
						$this->configure_dmdSec_2 ( $dom, $xpath, $dim, $metadata, $collection_handle_id );
						// }
					}
				}
			}
		}

		// amdSec
		$amdSec = $xpath->query ( '//amdSec' );
		foreach ( $amdSec as $a ) {
			// dmdSec_1
			if ($a->getAttribute ( "ID" ) == "amd_3") {
				$sourceMD = $xpath->query ( $a->getNodePath () . '/sourceMD' );
				foreach ( $sourceMD as $s ) {
					if ($s->getAttribute ( "ID" ) == "sourceMD_10") {
						$mdWrap = $xpath->query ( $s->getNodePath () . '/mdWrap' );
						foreach ( $mdWrap as $mw ) {
							if ($mw->getAttribute ( "OTHERMDTYPE" ) == "AIP-TECHMD") {
								$dims = $xpath->query ( $mw->getNodePath () . '/xmlData/dim:dim' );
								foreach ( $dims as $dim ) {

									$elem = $dom->createElement ( "dim:field", "hdl:" . $collection_handle_id );
									$elem->setAttribute ( "mdschema", "dc" );
									$elem->setAttribute ( "element", "identifier" );
									$elem->setAttribute ( "qualifier", "uri" );
									$dim->appendChild ( $elem );

									$elem1 = $dom->createElement ( "dim:field", "hdl:" . $parent_community_handle_id );
									$elem1->setAttribute ( "mdschema", "dc" );
									$elem1->setAttribute ( "element", "relation" );
									$elem1->setAttribute ( "qualifier", "isPartOf" );
									$dim->appendChild ( $elem1 );
								}
							}
						}
					}
				}
			}
		}

		// structMap , add list of child items info
		$cnt = "13";
		$structMap = $xpath->query ( '//structMap' );
		foreach ( $structMap as $sm ) {
			if ($sm->getAttribute ( "ID" ) == "struct_11" && $sm->getAttribute ( "LABEL" ) == "DSpace Object" && $sm->getAttribute ( "TYPE" ) == "LOGICAL") {
				$divs = $xpath->query ( $sm->getNodePath () . '/div' );
				foreach ( $divs as $div ) {

					if ($div->getAttribute ( "DMDID" ) == "dmdSec_1 dmdSec_2" && $div->getAttribute ( "ADMID" ) == "amd_3") {
						foreach ( $list_child_items as $each_item ) {

							$child_handle_id = $each_item;
							$child_handle_hyphen = str_replace ( "/", "-", $child_handle_id );

							$elem2 = $dom->createElement ( "div" );
							$elem2->setAttribute ( "ID", "div_" . $cnt );
							$elem2->setAttribute ( "TYPE", "DSpace ITEM" );
							$cnt ++;

							$elem3 = $dom->createElement ( "mptr" );
							$elem3->setAttribute ( "ID", "mptr_" . $cnt );
							$elem3->setAttribute ( "LOCTYPE", "HANDLE" );
							$elem3->setAttribute ( "xlink:type", "simple" );
							$elem3->setAttribute ( "xlink:href", $child_handle_id );
							$elem2->appendChild ( $elem3 );
							$cnt ++;

							$elem4 = $dom->createElement ( "mptr" );
							$elem4->setAttribute ( "ID", "mptr_" . $cnt );
							$elem4->setAttribute ( "LOCTYPE", "URL" );
							$elem4->setAttribute ( "xlink:type", "simple" );
							$elem4->setAttribute ( "xlink:href", "ITEM@" . $child_handle_hyphen . ".zip" );
							$elem2->appendChild ( $elem4 );
							$cnt ++;

							$div->appendChild ( $elem2 );
						}
					}
				}
			}
		}

		$elem5 = $dom->createElement ( "structMap" );
		$elem5->setAttribute ( "ID", "struct_" . $cnt );
		$elem5->setAttribute ( "LABEL", "Parent" );
		$elem5->setAttribute ( "TYPE", "LOGICAL" );
		$tag_mets->appendChild ( $elem5 );
		$cnt ++;

		$elem6 = $dom->createElement ( "div" );
		$elem6->setAttribute ( "ID", "div_" . $cnt );
		$elem6->setAttribute ( "LABEL", "Parent of this DSpace Object" );
		$elem6->setAttribute ( "TYPE", "AIP Parent Link" );
		$elem5->appendChild ( $elem6 );
		$cnt ++;

		$elem7 = $dom->createElement ( "mptr" );
		$elem7->setAttribute ( "ID", "mptr_" . $cnt );
		$elem7->setAttribute ( "LOCTYPE", "HANDLE" );
		$elem7->setAttribute ( "xlink:type", "simple" );
		$elem7->setAttribute ( "xlink:href", $parent_community_handle_id );
		$elem6->appendChild ( $elem7 );

		$zip = new ZipArchive ();
		$res = $zip->open ( $this->output_folder_path . "/COLLECTION@" . $handle_hyphen . ".zip", ZipArchive::CREATE );
		if ($res === true) {
			$zip->addFromString ( "mets.xml", $dom->saveXML () );
			$zip->close ();
			return true;
		}

		return false;
	}

	/**
	 *
	 * @param string $item_handle_id
	 * @param string $parent_collection_handle_id
	 * @param array $metadata
	 * @param array $assets
	 * @param string $last_mod_date
	 * @param string $dc_creator
	 * @return boolean
	 */
	private function create_item(string $item_handle_id, string $parent_collection_handle_id, array $metadata, array $assets = array(), string $last_mod_date = null, string $dc_creator = null) {
		$handle_hyphen = str_replace ( "/", "-", $item_handle_id );
		$a = explode ( "/", $item_handle_id );
		$handle_prefix = $a [0];

		$dc_title = array_key_exists ( "dc.title", $metadata ) ? current ( $metadata ["dc.title"] ) : null; // take only first value
		$dc_identifier_uri = array_key_exists ( "dc.identifier.uri", $metadata ) ? $metadata ["dc.identifier.uri"] : array ();

		$dom = new DOMDocument ();
		$xml_content = str_replace ( 'xmlns="http://www.loc.gov/METS/" ', "", file_get_contents ( dirname ( __DIR__ ) . "/" . $this->item_schema_file ) );
		$dom->loadXML ( $xml_content );
		$xpath = new DOMXPath ( $dom );

		$xpath->registerNamespace ( "dim", "http://www.dspace.org/xmlns/dspace/dim" );
		$xpath->registerNamespace ( "premis", "http://www.loc.gov/standards/premis" );
		$xpath->registerNamespace ( "mods", "http://www.loc.gov/mods/v3" );

		$mets = $xpath->query ( '/mets' );

		// mets (root tag) ID add
		foreach ( $mets as $n ) {
			$n->setAttribute ( "ID", "DSpace_ITEM_" . $handle_hyphen );
			$n->setAttribute ( "OBJID", "hdl:" . $item_handle_id );
			$n->setAttribute ( "xmlns", "http://www.loc.gov/METS/" );
		}
		$metsHdr = $xpath->query ( '//metsHdr' );
		foreach ( $metsHdr as $m ) {
			if ($last_mod_date != null) {
				$m->setAttribute ( "LASTMODDATE", $last_mod_date );
			}
			$agent = $xpath->query ( $m->getNodePath () . '/agent' );
			foreach ( $agent as $a ) {
				if ($a->getAttribute ( "OTHERTYPE" ) == "DSpace Archive" && $a->getAttribute ( "ROLE" ) == "CUSTODIAN") {
					$name = $xpath->query ( $a->getNodePath () . '/name' );
					foreach ( $name as $nm ) {
						$nm->appendChild ( $dom->createTextNode ( $handle_prefix . "/0" ) );
					}
				}
				if ($a->getAttribute ( "OTHERTYPE" ) == "DSpace Software" && $a->getAttribute ( "ROLE" ) == "CREATOR") {
					$name = $xpath->query ( $a->getNodePath () . '/name' );
					foreach ( $name as $n ) {
						$n->appendChild ( $dom->createTextNode ( $this->dspace_version ) );
					}
				}
			}
		}

		// add dim:field within dmdSec
		$dmdSec = $xpath->query ( '//dmdSec' );
		foreach ( $dmdSec as $d ) {

			// dmdSec_1
			if ($d->getAttribute ( "ID" ) == "dmdSec_1") {
				$mods = $xpath->query ( $d->getNodePath () . '/mdWrap/xmlData/mods:mods' );
				$this->configure_dmdSec_1 ( $dom, $xpath, $mods, $dc_title, $dc_identifier_uri );
			}
			// configure_dmdSec_2
			if ($d->getAttribute ( "ID" ) == "dmdSec_2") {
				$mdWrap = $xpath->query ( $d->getNodePath () . '/mdWrap' );
				foreach ( $mdWrap as $w ) {
					if ($w->getAttribute ( "MDTYPE" ) == "OTHER" && $w->getAttribute ( "OTHERMDTYPE" ) == "DIM") {
						$dim = $xpath->query ( $w->getNodePath () . '/xmlData/dim:dim' );
						$this->configure_dmdSec_2 ( $dom, $xpath, $dim, $metadata, null );
					}
				}
			}
		}
		if (sizeof ( $assets ) > 0) {

		/**
		 * ****TODO*******
		 */
		}
		$amdSec = $xpath->query ( '//amdSec' );
		foreach ( $amdSec as $a ) {

			/*
			 * TODO ******
			 * if ($with_license) {
			 * if ($a->getAttribute ( "ID" ) == "amd_3") {
			 * $mdRef = $xpath->query ( $a->getNodePath () . '/rightsMD/mdRef' );
			 * foreach ( $mdRef as $r ) {
			 * if ($r->getAttribute ( "LOCTYPE" ) == "URL" && $r->getAttribute ( "MIMETYPE" ) == "text/plain" && $r->getAttribute ( "OTHERMDTYPE" ) == "DSpaceDepositLicense") {
			 * $r->setAttribute ( "xlink:href", $licenseFile );
			 * }
			 * }
			 * }
			 * }
			 */

			$sourceMD = $xpath->query ( $a->getNodePath () . '/sourceMD' );
			foreach ( $sourceMD as $s ) {
				if ($s->getAttribute ( "ID" ) == "sourceMD_10") {
					$mdWrap2 = $xpath->query ( $s->getNodePath () . '/mdWrap' );
					foreach ( $mdWrap2 as $w ) {
						if ($w->getAttribute ( "OTHERMDTYPE" ) == "AIP-TECHMD") {
							$dim_dim = $xpath->query ( $w->getNodePath () . '/xmlData/dim:dim' );
							foreach ( $dim_dim as $di ) {
								if ($di->getAttribute ( "dspaceType" ) == "ITEM") {

									if ($dc_creator != null) {
										$new_node = $dom->createElement ( "dim:field", $dc_creator );
										$new_node->setAttribute ( "mdschema", "dc" );
										$new_node->setAttribute ( "element", "creator" );
										$di->appendChild ( $new_node );
									}

									$new_node1 = $dom->createElement ( "dim:field", "hdl:" . $item_handle_id );
									$new_node1->setAttribute ( "mdschema", "dc" );
									$new_node1->setAttribute ( "element", "identifier" );
									$new_node1->setAttribute ( "qualifier", "uri" );
									$di->appendChild ( $new_node1 );

									$new_node2 = $dom->createElement ( "dim:field", "hdl:" . $parent_collection_handle_id );
									$new_node2->setAttribute ( "mdschema", "dc" );
									$new_node2->setAttribute ( "element", "relation" );
									$new_node2->setAttribute ( "qualifier", "isPartOf" );
									$di->appendChild ( $new_node2 );
								}
							}
						}
					}
				}
			}
		}

		// asset file
		// license.txt
		if (sizeof ( $assets ) > 0) {
			// if ($with_license) {
			// $amdSec2 = $xpath->query ( '//amdSec' );
			// foreach ( $amdSec2 as $a2 ) {
			// if ($a2->getAttribute ( "ID" ) == "amd_21") {
			// $md_Wrap2 = $xpath->query ( $a2->getNodePath () . '/techMD/mdWrap' );
			// foreach ( $md_Wrap2 as $w2 ) {
			// if ($w2->getAttribute ( "MDTYPE" ) == "PREMIS") {
			// $premis_object = $xpath->query ( $w2->getNodePath () . '/xmlData/premis:premis/premis:object' );
			// foreach ( $premis_object as $po ) {
			// $premis_objectIdentifier = $xpath->query ( $po->getNodePath () . '/premis:objectIdentifier' );
			// foreach ( $premis_objectIdentifier as $pi ) {
			// $new_node3 = $dom->createElement ( "premis:objectIdentifierValue", $lic_file_url );
			// $pi->appendChild ( $new_node3 );
			// }
			// }
			// $r->setAttribute ( "xlink:href", $license_file );
			// }
			// }
			// }
			// }
			// }
		}
		/**
		 * * END asset file **
		 */

		$struct_map = $xpath->query ( '//structMap' );
		foreach ( $struct_map as $sm ) {
			if ($sm->getAttribute ( "LABEL" ) == "Parent" && $sm->getAttribute ( "TYPE" ) == "LOGICAL") {
				$mptr = $xpath->query ( $sm->getNodePath () . '/div/mptr' );
				foreach ( $mptr as $mp ) {
					if ($mp->getAttribute ( "LOCTYPE" ) == "HANDLE") {
						$mp->setAttribute ( "xlink:href", $parent_collection_handle_id );
					}
				}
			}
		}

		$zip = new ZipArchive ();
		$res = $zip->open ( $this->output_folder_path . "/ITEM@" . $handle_hyphen . ".zip", ZipArchive::CREATE );
		if ($res === true) {
			$zip->addFromString ( "mets.xml", $dom->saveXML () );
			$zip->close ();
			return true;
		}

		return false;
	}
	private function configure_dmdSec_1(&$dom, $xpath, &$mods, $dc_title, $dc_identifier_uri) {
		foreach ( $mods as $mod ) {
			foreach ( $dc_identifier_uri as $each ) {
				$new_node = $dom->createElement ( "mods:identifier", $each );
				$new_node->setAttribute ( "type", "uri" );
				$mod->appendChild ( $new_node );
			}
			$titleInfo = $xpath->query ( $mod->getNodePath () . '/mods:titleInfo' );
			foreach ( $titleInfo as $t ) {
				$new_node1 = $dom->createElement ( "mods:title", htmlspecialchars ( $dc_title ) );
				$t->appendChild ( $new_node1 );
			}
		}
	}
	private function configure_dmdSec_2(&$dom, $xpath, &$dim, $metadata, $handle_id) {
		foreach ( $dim as $b ) {
			foreach ( $metadata as $key => $values ) {
				$meta = explode ( ".", $key );
				foreach ( $values as $value ) {
					$elem = $dom->createElement ( "dim:field", htmlspecialchars ( $value ) );

					$elem->setAttribute ( "mdschema", $meta [0] );
					$elem->setAttribute ( "element", $meta [1] );
					if (sizeof ( $meta ) == 3) {
						$elem->setAttribute ( "qualifier", $meta [2] );
					}
					$elem->setAttribute ( "lang", "" );
					$b->appendChild ( $elem );
				}
			}
			if ($handle_id != null) {
				$elem4 = $dom->createElement ( "dim:field", "hdl:" . $handle_id );
				$elem4->setAttribute ( "mdschema", "dc" );
				$elem4->setAttribute ( "element", "identifier" );
				$elem4->setAttribute ( "qualifier", "uri" );
				$b->appendChild ( $elem4 );
			}
		}
	}
}
?>
<?php
date_default_timezone_set ( 'UTC' );
ini_set ( 'memory_limit', '6G' );
ini_set ( 'max_execution_time', 60 );
const PROJECT_NAME = "Data Acquisition System";
const PROJECT_VER = "v0.9b";

require_once dirname ( __DIR__ ) . '/classes/utilities-common.php';

/**
 *
 * @author subhayan
 *        
 */
class Logger {
	const LOG_TYPE_ERROR = 9;
	const LOG_TYPE_EVENT = 0;
	public static function add_log(string $statement, string $src_id, int $type) {
		$line = array (
				date ( "Y-m-d H:i:s" ),
				$src_id,
				$statement
		);
		switch ($type) {
			case self::LOG_TYPE_ERROR :
				file_put_contents ( $_SERVER ["DOCUMENT_ROOT"] . "/logs/error.log", implode ( "\t", $line ) . PHP_EOL, FILE_APPEND | LOCK_EX );
				break;
			case self::LOG_TYPE_EVENT :
				file_put_contents ( $_SERVER ["DOCUMENT_ROOT"] . "/logs/event.log", implode ( "\t", $line ) . PHP_EOL, FILE_APPEND | LOCK_EX );
				break;
		}
	}
}

/**
 *
 * @author subhayan
 *        
 */
class DataSourceExtended extends DataSource {
	const SRC_TYPE_IDR = "idr";
	const SRC_TYPE_WEBSITE = "crawl";
	const SRC_TYPE_AIP = "aip";
	const SRC_TYPE_SIP = "sip";
	const SRC_TYPE_CSV = "csv";
	const SRC_TYPE_MRC = "mrc";
	private $db_conn = null;
	/**
	 *
	 * @param string $src_id
	 * @param resource $db_conn
	 */
	public function __construct(string $src_id, $db_conn) {
		$this->src_id = $src_id;
		$this->db_conn = $db_conn;
	}
	private function get_view_name_item(string $src_id) {
		return $src_id . "_raw_item";
	}
	private function get_view_name_asset(string $src_id) {
		return $src_id . "_raw_asset";
	}

	/**
	 *
	 * @param string $ndli_uniq_doc_id
	 * @return stdClass
	 */
	public static function get_document_details(string $ndli_uniq_doc_id) {
		$obj = new stdClass ();

		// metadata
		$query_metadata = "SELECT meta_field, json_agg(meta_value ORDER BY id ASC)::text meta_values
					FROM metadata_raw WHERE ndli_uniq_id = '" . pg_escape_string ( $ndli_uniq_doc_id ) . "'
					GROUP BY meta_field";

		// assets
		$query_assets = "SELECT assets.ndli_asset_id, asset_sequence seq, json_object_agg(asset_metadata_field, asset_metadata_value) meta_values
					FROM assets_metadata
					LEFT JOIN assets ON assets.ndli_asset_id = assets_metadata.ndli_asset_id
					WHERE ndli_uniq_id = '" . pg_escape_string ( $ndli_uniq_doc_id ) . "'
					GROUP BY assets.ndli_asset_id, asset_sequence
					ORDER BY asset_sequence, assets.ndli_asset_id;";

		$db = new Database ();
		$obj->metadata = pg_fetch_all ( pg_query ( $db->get_connection (), $query_metadata ) );
		$obj->assets = pg_fetch_all ( pg_query ( $db->get_connection (), $query_assets ) );
		if (! $obj->assets) {
			$obj->assets = array ();
		}
		$db->close_database ();

		return $obj;
	}

	/**
	 *
	 * @return array
	 */
	public static function get_all_sources($db_conn) {
		$query = "SELECT sources.src_id, sources.src_name, sources.src_url, sources.src_configuration, to_json(sources.users) users, MAX(ndli_updated) ndli_updated,
					COUNT(items.src_id) item_count,
					CASE
						WHEN sources.src_id IN (
							SELECT DISTINCT(src_id) FROM items
							WHERE ndli_uniq_id IN (SELECT DISTINCT(ndli_uniq_id) FROM metadata_mapped)) THEN (
								CASE
									WHEN COUNT(to_regclass('public.' || sources.src_id ||'_raw_asset'))=0 THEN 'S9'
									ELSE 'S3'
								END
							)
						WHEN sources.rules::text <> '{}'::text THEN 'S2'
						WHEN COUNT(items.src_id) > 0 THEN 'S1'
						ELSE 'S0'
					END state
					FROM sources LEFT JOIN items ON sources.src_id = items.src_id
					GROUP BY sources.src_id ORDER BY item_count DESC";
		$items = pg_fetch_all ( pg_query ( $db_conn, $query ) );
		return $items ?: array ();
	}

	/**
	 *
	 * @return array
	 */
	public function get_source_details() {
		$query = "SELECT sources.src_id, sources.src_name, sources.src_url, sources.src_configuration, to_json(sources.users) users, MAX(ndli_updated) ndli_updated,
					COUNT(items.src_id) item_count, structure, rules,
					CASE
						WHEN sources.src_id IN (SELECT DISTINCT(src_id) FROM items
							WHERE ndli_uniq_id IN (SELECT DISTINCT(ndli_uniq_id) FROM metadata_mapped)) THEN 'S3'
						WHEN sources.rules::text <> '{}'::text THEN 'S2'
						WHEN COUNT(items.src_id) > 0 THEN 'S1'
						ELSE 'S0'
					END state
					FROM sources LEFT JOIN items ON sources.src_id = items.src_id
					WHERE sources.src_id='" . pg_escape_string ( $this->src_id ) . "'
					GROUP BY sources.src_id";
		$items = pg_fetch_array ( pg_query ( $this->db_conn, $query ) );
		return $items ?: array ();
	}

	/**
	 *
	 * @param string $src_type
	 * @param string $src_name
	 * @param string $src_uri
	 * @param array $users
	 * @return boolean
	 */
	public function add_source(string $src_type, string $src_name, string $src_uri, string $added_by, array $users = array()) {
		$query_params = array (
				"src_id" => $this->src_id,
				"src_name" => $src_name,
				"src_url" => $src_uri,
				"added_by" => $added_by
		);
		switch ($src_type) {
			case self::SRC_TYPE_IDR :
				$query_params ["src_configuration"] = '{"url":"' . $src_uri . '"}';
				break;
			case self::SRC_TYPE_WEBSITE :
				break;
			case self::SRC_TYPE_AIP :
				break;
			case self::SRC_TYPE_SIP :
				break;
			case self::SRC_TYPE_CSV :
				break;
			case self::SRC_TYPE_MRC :
				break;
		}
		if ($users) {
			$query_params ["users"] = '{"' . implode ( '","', $users ) . '"}';
		}

		$status = pg_insert ( $this->db_conn, "sources", $query_params );

		return $status ? true : false;
	}

	/**
	 *
	 * @param string $key
	 * @param mixed $data
	 * @return boolean
	 */
	public function add_source_config_data(string $key, $data) {
		$obj = new stdClass ();
		$obj->$key = $data;
		return pg_update ( $this->db_conn, sources, array (
				"src_configuration" => "src_configuration::jsonb - '$key' || " . json_encode ( $obj ) . "::jsonb"
		), array (
				"src_id" => $this->src_id
		) );
		// $query = "UPDATE sources
		// SET src_configuration = src_configuration::jsonb - '$key' || " . pg_escape_literal ( json_encode ( $obj ) ) . "::jsonb
		// WHERE src_id = '" . pg_escape_string ( $this->src_id ) . "';";
		// return pg_query ( $this->db_conn, $query ) ? true : false;
	}

	/**
	 *
	 * @param array $users
	 * @return boolean
	 */
	public function _set_source_users(array $users) {
	}

	/**
	 *
	 * @param stdClass $rules
	 * @return boolean
	 */
	public function set_mapping_rules(stdClass $rules) {
		return pg_update ( $this->db_conn, "sources", array (
				"rules" => json_encode ( $rules )
		), array (
				"src_id" => $this->src_id
		) );
		// $query = "UPDATE sources
		// SET rules = " . pg_escape_literal ( json_encode ( $rules ) ) . "
		// WHERE src_id = '" . pg_escape_string ( $this->src_id ) . "';";
		// return pg_query ( $this->db_conn, $query ) ? true : false;
	}

	/**
	 *
	 * @param stdClass $translate_rules
	 * @param stdClass $add_rules
	 * @param array $asset_meta
	 * @return boolean
	 */
	public function run_mapping(stdClass $translate_rules, stdClass $add_rules, array $asset_meta) {
		pg_flush ( $this->db_conn );
		$result = pg_query ( $this->db_conn, "BEGIN" ) ? true : false;

		// delete already mapped-data (in any)
		if ($result) {
			$query = "DELETE FROM metadata_mapped WHERE ndli_uniq_id IN (
						SELECT ndli_uniq_id FROM items WHERE src_id = '" . pg_escape_string ( $this->src_id ) . "'
					)";
			$result = pg_query ( $this->db_conn, $query );
		}

		// handle $translate_rules
		if ($result) {
			$query = "INSERT INTO metadata_mapped (ndli_uniq_id, meta_field, meta_value, ref_id) (
						SELECT ndli_uniq_id, meta_field, meta_value, id
						FROM metadata_raw
						WHERE ndli_uniq_id IN ( SELECT ndli_uniq_id FROM items WHERE src_id = '" . pg_escape_string ( $this->src_id ) . "' )
							AND meta_field IN ('" . implode ( "','", array_keys ( ( array ) $translate_rules ) ) . "')
					) RETURNING ndli_uniq_id";
			$result = pg_query ( $this->db_conn, $query );
		}
		if ($result) {
			array_values ( array_unique ( pg_fetch_all_columns ( $result ) ) );
			foreach ( $translate_rules as $old => $new ) {
				if ($result) {
					$query = "UPDATE metadata_mapped
					SET meta_field = '$new'
					WHERE meta_field = '$old'
						AND ndli_uniq_id IN ( SELECT ndli_uniq_id FROM items WHERE src_id = '" . pg_escape_string ( $this->src_id ) . "' )";
					$result = pg_query ( $this->db_conn, $query );
				}
			}
		}

		// handle $add_rules
		if ($result) {
			foreach ( $add_rules as $field => $value ) {
				if ($result) {
					$query = "INSERT INTO metadata_mapped (ndli_uniq_id, meta_field, meta_value, ref_id) (
							SELECT ndli_uniq_id, '$field' meta_field, " . pg_escape_literal ( $value ) . " meta_value, 'add' ref_id
							FROM items
							WHERE src_id = '" . pg_escape_string ( $this->src_id ) . "'
						)";
					$result = pg_query ( $this->db_conn, $query );
				} else {
					break;
				}
			}
		}

		// handle $asset_meta
		if ($result) {
			$query = "INSERT INTO metadata_mapped (ndli_uniq_id, meta_field, meta_value) (
					SELECT ndli_uniq_id, 'ndl.sourceMeta.additionalInfo@asset' meta_field,
						('{\"ndli.assset.id\":\"' || (assets.ndli_asset_id) || '\"}')::jsonb || jsonb_object_agg(asset_metadata_field, asset_metadata_value) meta_value
					FROM assets_metadata
					LEFT JOIN assets ON assets.ndli_asset_id = assets_metadata.ndli_asset_id
					WHERE ndli_uniq_id IN ( SELECT ndli_uniq_id FROM items WHERE src_id = '" . pg_escape_string ( $this->src_id ) . "' )
						AND asset_metadata_field IN ('" . implode ( "','", $asset_meta ) . "')
					GROUP BY ndli_uniq_id, assets.ndli_asset_id
				)";
			$result = pg_query ( $this->db_conn, $query );
		}

		return (pg_query ( $this->db_conn, ($result ? "COMMIT" : "ROLLBACK") ) && $result) ? true : false;
	}
	/**
	 *
	 * @return boolean
	 */
	public function _remove_source() {
		return true;
	}

	// /////////////////////////////////////////// RAW ////////////////////////////////////////////
	public function get_items_metadata_fields_raw() {
		$query = "SELECT field FROM " . pg_escape_string ( $this->get_view_name_item ( $this->src_id ) ) . " ORDER BY field ASC";
		return pg_fetch_all_columns ( pg_query ( $this->db_conn, $query ) );
	}

	/**
	 *
	 * @param array $fields
	 * @param int $limit
	 * @param int $offset
	 * @return array
	 */
	public function get_items_metadata_raw(array $fields = array(), int $limit = 100000, int $offset = 0) {
		$query = "SELECT ndli_uniq_id, ndli_collection_id, json_object_agg( s.meta_field, s.meta_value ) meta_value
					FROM (
						SELECT items.ndli_uniq_id, ndli_collection_id, meta_field, json_agg( meta_value ORDER BY id ) meta_value
						FROM metadata_raw
						LEFT JOIN items ON items.ndli_uniq_id = metadata_raw.ndli_uniq_id
						WHERE items.ndli_uniq_id IN (
							SELECT ndli_uniq_id FROM items
							WHERE src_id = '" . pg_escape_string ( $this->src_id ) . "'
						)" . ($fields ? " AND meta_field IN ('" . implode ( "','", $fields ) . "')" : "") . "
						GROUP BY ndli_collection_id, items.ndli_uniq_id, meta_field
					)s
					GROUP BY ndli_collection_id, ndli_uniq_id ORDER BY ndli_uniq_id LIMIT $limit OFFSET $offset";
		return pg_fetch_all ( pg_query ( $this->db_conn, $query ) );
	}

	/**
	 *
	 * @param string $field
	 * @param string $sort_field
	 * @param string $sort_order
	 * @param int $limit
	 * @param int $offset
	 * @param string $search_key
	 * @return stdClass
	 */
	public function get_field_values_item_raw(string $field, string $sort_field = "item_count", string $sort_order = "DESC", int $limit = 0, int $offset = 0, string $search_key = null) {
		$query_count = "SELECT count_values FROM " . $this->get_view_name_item ( $this->src_id ) . " WHERE field  = '$field';";
		$query_values = "SELECT min(id) id, COUNT(*) item_count, COUNT(*) OVER() AS records_filtered,
					CASE WHEN char_length(meta_value)>1000 THEN substring(meta_value, 0, 900)||'......CONTINUED' ELSE meta_value END meta_value
					FROM metadata_raw
					WHERE ndli_uniq_id IN ( SELECT ndli_uniq_id FROM items WHERE src_id = '" . pg_escape_string ( $this->src_id ) . "' )
						AND meta_field='$field'
						" . ($search_key ? " AND meta_value ilike '%" . strtolower ( $search_key ) . "%'" : "") . "
					GROUP BY meta_value
					ORDER BY $sort_field $sort_order
					LIMIT " . $limit . " OFFSET $offset;";
		$obj = new stdClass ();
		$obj->count = intval ( current ( pg_fetch_all_columns ( pg_query ( $this->db_conn, $query_count ) ) ) );
		$obj->values = pg_fetch_all ( pg_query ( $this->db_conn, $query_values ) );
		return $obj;
	}

	/**
	 *
	 * @param string $field
	 * @param string $sort_order
	 * @param int $limit
	 * @param int $offset
	 * @param string $search_key
	 * @return stdClass
	 */
	public function get_field_values_asset(string $field, string $sort_order = null, int $limit = 0, int $offset = 0, string $search_key = null) {
		$query_count = "SELECT count_values FROM " . $this->get_view_name_asset ( $this->src_id ) . " WHERE field  = '$field';";
		$query_values = "SELECT asset_metadata_value meta_value, COUNT(DISTINCT(ndli_uniq_id)) item_count
					FROM assets_metadata
					LEFT JOIN assets ON assets.ndli_asset_id = assets_metadata.ndli_asset_id
					WHERE ndli_uniq_id IN ( SELECT ndli_uniq_id FROM items WHERE src_id = '" . pg_escape_string ( $this->src_id ) . "' )
					AND asset_metadata_field = '$field'
					GROUP BY asset_metadata_value
					ORDER BY item_count DESC
					LIMIT " . $limit . " OFFSET $offset;";
		$obj = new stdClass ();
		$obj->count = intval ( current ( pg_fetch_all_columns ( pg_query ( $this->db_conn, $query_count ) ) ) );
		$obj->values = pg_fetch_all ( pg_query ( $this->db_conn, $query_values ) );
		return $obj;
	}

	/**
	 *
	 * @param string $field
	 * @param string $id
	 * @param int $num_example_ids
	 * @return array
	 */
	public function get_value_details_item_raw(string $field, string $id, int $num_example_ids = 10) {
		// SELECT * FROM sources ORDER BY random() LIMIT 10;
		$query = "SELECT meta_value, array_to_json((translate(json_agg(ndli_uniq_id ORDER BY random())::json::text, '[]', '{}')::text[])[0:$num_example_ids]) ids
			FROM metadata_raw
			WHERE ndli_uniq_id IN (SELECT ndli_uniq_id FROM items WHERE src_id = '" . pg_escape_string ( $this->src_id ) . "')
				AND meta_field = '" . pg_escape_string ( $field ) . "'
				AND meta_value IN (SELECT meta_value FROM metadata_raw WHERE id = '" . pg_escape_string ( $id ) . "')
			GROUP BY meta_value";
		// $query = "SELECT id, meta_value FROM metadata_raw WHERE id IN ('" . implode ( '","', $ids ) . "')";
		return pg_fetch_assoc ( pg_query ( $this->db_conn, $query ) );
	}

	/**
	 *
	 * @param string $src_id
	 */
	public function get_analysis_report_raw() {
		$obj = new stdClass ();

		$query_item = "SELECT * FROM " . pg_escape_string ( $this->get_view_name_item ( $this->src_id ) ) . " ORDER BY count_covered DESC";
		$obj->item = pg_fetch_all ( pg_query ( $this->db_conn, $query_item ) );

		$query_asset = "SELECT * FROM " . pg_escape_string ( $this->get_view_name_asset ( $this->src_id ) ) . " ORDER BY count_covered DESC";
		$obj->asset = pg_fetch_all ( pg_query ( $this->db_conn, $query_asset ) );

		return $obj;
	}

	// ////////////////////////////////////////// MAPPED //////////////////////////////////////////
	public function get_items_metadata_fields_mapped() {
		$query = "SELECT DISTINCT(meta_field) field
					FROM metadata_mapped
					WHERE ndli_uniq_id IN (SELECT ndli_uniq_id FROM items WHERE src_id = '" . pg_escape_string ( $this->src_id ) . "')
					ORDER BY meta_field";
		return pg_fetch_all_columns ( pg_query ( $this->db_conn, $query ) );
	}
	/**
	 *
	 * @param array $fields
	 * @param int $limit
	 * @param int $offset
	 * @return array
	 */
	public function get_items_metadata_mapped(array $fields = array(), int $limit = 100000, int $offset = 0) {
		$query = "SELECT ndli_uniq_id, ndli_collection_id, json_object_agg( s.meta_field, s.meta_value ) meta_value
					FROM (
						SELECT items.ndli_uniq_id, ndli_collection_id, meta_field, json_agg( meta_value ORDER BY id ) meta_value
						FROM metadata_mapped
						LEFT JOIN items ON items.ndli_uniq_id = metadata_mapped.ndli_uniq_id
						WHERE items.ndli_uniq_id IN (
							SELECT ndli_uniq_id FROM items
							WHERE src_id = '" . pg_escape_string ( $this->src_id ) . "'
						)" . ($fields ? " AND meta_field IN ('" . implode ( "','", $fields ) . "')" : "") . "
						GROUP BY ndli_collection_id, items.ndli_uniq_id, meta_field
					)s
					GROUP BY ndli_collection_id, ndli_uniq_id ORDER BY ndli_uniq_id LIMIT $limit OFFSET $offset";
		return pg_fetch_all ( pg_query ( $this->db_conn, $query ) );
	}

	/**
	 *
	 * @param string $field
	 * @param string $sort_field
	 * @param string $sort_order
	 * @param int $limit
	 * @param int $offset
	 * @param string $search_key
	 * @return stdClass
	 */
	public function get_field_values_item_mapped(string $field, string $sort_field = "item_count", string $sort_order = "DESC", int $limit = 0, int $offset = 0, string $search_key = null) {
		$query_count = "SELECT count_values FROM " . $this->get_view_name_item ( $this->src_id ) . " WHERE field  = '$field';";
		$query_values = "SELECT min(id) id, COUNT(*) item_count, COUNT(*) OVER() AS records_filtered,
					CASE WHEN char_length(meta_value)>1000 THEN substring(meta_value, 0, 900)||'......CONTINUED' ELSE meta_value END meta_value
					FROM metadata_mapped
					WHERE ndli_uniq_id IN ( SELECT ndli_uniq_id FROM items WHERE src_id = '" . pg_escape_string ( $this->src_id ) . "' )
						AND meta_field='$field'
						" . ($search_key ? " AND meta_value ilike '%" . strtolower ( $search_key ) . "%'" : "") . "
					GROUP BY meta_value
					ORDER BY $sort_field $sort_order
					LIMIT " . $limit . " OFFSET $offset;";
		$obj = new stdClass ();
		$obj->count = intval ( current ( pg_fetch_all_columns ( pg_query ( $this->db_conn, $query_count ) ) ) );
		$obj->values = pg_fetch_all ( pg_query ( $this->db_conn, $query_values ) );
		return $obj;
	}

	/**
	 *
	 * @param string $field
	 * @param string $id
	 * @param int $num_example_ids
	 * @return array
	 */
	public function get_value_details_item_mapped(string $field, string $id, int $num_example_ids = 10) {
		// SELECT * FROM sources ORDER BY random() LIMIT 10;
		$query = "SELECT meta_value, array_to_json((translate(json_agg(ndli_uniq_id ORDER BY random())::json::text, '[]', '{}')::text[])[0:$num_example_ids]) ids
			FROM metadata_mapped
			WHERE ndli_uniq_id IN (SELECT ndli_uniq_id FROM items WHERE src_id = '" . pg_escape_string ( $this->src_id ) . "')
				AND meta_field = '" . pg_escape_string ( $field ) . "'
				AND meta_value IN (SELECT meta_value FROM metadata_mapped WHERE id = '" . pg_escape_string ( $id ) . "')
			GROUP BY meta_value";
		// $query = "SELECT id, meta_value FROM metadata_raw WHERE id IN ('" . implode ( '","', $ids ) . "')";
		return pg_fetch_assoc ( pg_query ( $this->db_conn, $query ) );
	}

	/**
	 *
	 * @param int $facet_limit
	 * @param int $facet_offset
	 * @return array
	 */
	public function get_faceted_report_mapped(int $facet_limit = 50, int $facet_offset = 0) {
		$query = "SELECT meta_field, (
					SELECT json_object_agg(meta_value, cnt)
					FROM (
						SELECT concat(min(id),'||',meta_value) meta_value, meta_field, COUNT(*) cnt
						FROM metadata_mapped
						WHERE ndli_uniq_id IN (SELECT ndli_uniq_id FROM items WHERE src_id='" . pg_escape_string ( $this->src_id ) . "')
						--WHERE char_length(meta_value)<10
						GROUP BY meta_value, meta_field
						HAVING meta_field = outer_table.meta_field
						ORDER BY cnt DESC
						LIMIT $facet_limit OFFSET $facet_offset
					) nested
				) meta_values,
				min(char_length(meta_value)),
				max(char_length(meta_value)),
				COUNT(DISTINCT(meta_value))
				FROM metadata_mapped outer_table
				WHERE ndli_uniq_id IN (SELECT ndli_uniq_id FROM items WHERE src_id='" . pg_escape_string ( $this->src_id ) . "')
				GROUP BY meta_field
				ORDER BY meta_field";
		$rows = pg_fetch_all ( pg_query ( $this->db_conn, $query ) );
		return $rows;
	}

	/**
	 *
	 * @return boolean
	 */
	public function remove_mapped_data() {
		$query = "DELETE FROM metadata_mapped
					WHERE ndli_uniq_id IN (
						SELECT ndli_uniq_id FROM items WHERE src_id='" . pg_escape_string ( $this->src_id ) . "'
					)";
		return pg_query ( $this->db_conn, $query ) ? true : false;
	}
	/**
	 *
	 * @param string $parent_id
	 * @return array
	 */
	public function get_child_items(string $parent_id) {
		$query = "SELECT ndli_uniq_id
					FROM items
					WHERE ndli_collection_id = '" . pg_escape_string ( $parent_id ) . "'";
		$items = pg_fetch_all_columns ( pg_query ( $this->db_conn, $query ) );

		return $items ?: array ();
	}
}

/**
 *
 * @author subhayan
 *        
 */
class NDLISchema {
	public static function get_schema(string $schema = "general", bool $include_ctrlKey = true) {
		$curl = curl_init ( Configuration::get_data_service_endpoint () . "/getSchema" );
		curl_setopt_array ( $curl, array (
				CURLOPT_RETURNTRANSFER => true,
				CURLOPT_POST => true,
				CURLOPT_POSTFIELDS => http_build_query ( array (
						"schema" => array (
								$schema
						)
				) )
		) );
		$response = curl_exec ( $curl );
		curl_close ( $curl );

		$response = json_decode ( $response )->response->{$schema};
		if ($include_ctrlKey) {
			$ctrlKey_fields = array ();
			foreach ( $response as $field => $const ) {
				if ($const->type == "ctrlKey") {
					array_push ( $ctrlKey_fields, $field );
				}
			}
			$ctrlKey_fields_constraints = self::get_field_constraints ( $ctrlKey_fields );
			foreach ( $ctrlKey_fields_constraints as $field => $const ) {
				$response->$field->keys = $const->keys;
			}
		}

		return $response;
	}
	public static function get_field_constraints(array $fields) {
		$curl = curl_init ( Configuration::get_data_service_endpoint () . "/getConstraints" );
		curl_setopt_array ( $curl, array (
				CURLOPT_RETURNTRANSFER => true,
				CURLOPT_POST => true,
				CURLOPT_POSTFIELDS => http_build_query ( array (
						"fields" => $fields
				) )
		) );
		$response = curl_exec ( $curl );
		curl_close ( $curl );

		return json_decode ( $response )->response;
	}
}

/**
 *
 * @author poonam
 *        
 */
class User extends Database {
	const CATEGORY_LS = "ls";
	const CATEGORY_CS = "cs";
	const CATEGORY_ADMIN = "admin";

	/**
	 *
	 * @param string $mail
	 * @param string $password
	 * @param string $fullname
	 * @param string $category
	 * @return boolean
	 */
	public static function add_user(string $mail, string $password, string $fullname, string $category) {
		$query_params = array (
				"mail_id" => $mail,
				"passwd" => $password,
				"category" => $category,
				"fullname" => $fullname
		);

		$db = self::create_database_connection ();
		$status = pg_insert ( $db, "users", $query_params );
		self::close_database ();
		return $status ? true : false;
	}

	/**
	 *
	 * @param bool $exclude_admin
	 * @param resource $db_conn
	 * @return array
	 */
	public static function get_all_users($db_conn, bool $exclude_admin = true) {
		$query = "SELECT category, json_object_agg(mail_id, fullname) users
					FROM users" . ($exclude_admin ? " WHERE category != 'admin'" : "") . "
					GROUP BY category";
		$items = pg_fetch_all ( pg_query ( $db_conn, $query ) );
		return $items ?: array ();
	}

	/**
	 *
	 * @param string $mail
	 * @param string $password
	 * @return boolean
	 */
	public static function get_user_details(string $mail, string $password = null) {
		$query = ($password) ? "SELECT * FROM users
			WHERE mail_id = '" . pg_escape_string ( trim ( strtolower ( $mail ) ) ) . "' AND passwd = '" . pg_escape_string ( $password ) . "'" : "SELECT * FROM users
			WHERE mail_id = '" . pg_escape_string ( trim ( strtolower ( $mail ) ) ) . "'";
		$db = new Database ();
		$result = pg_query ( $db->get_connection (), $query );
		$db->close_database ();
		return pg_fetch_array ( $result );
	}

	/**
	 *
	 * @param string $mail
	 * @param string $src_id
	 * @return boolean
	 */
	public static function check_accessibility(string $mail, string $src_id) {
		return true;
	}
}

/**
 *
 * @author subhayan
 *        
 */
class ShellArgs {
	/**
	 *
	 * @param array $argv
	 * @return null|array
	 */
	public static function get_args(array $argv) {
		unset ( $argv [0] );
		$argv = array_values ( $argv );
		$args = array ();
		foreach ( $argv as $arg ) {
			$temp = explode ( "=", $arg );
			if (count ( $temp ) != 2) {
				return null;
			}
			$args [$temp [0]] = $temp [1];
		}
		return $args;
	}
}
/**
 *
 * @author subhayan
 *        
 */
class DataTestPortalProject {
	// private $name = null;
	// private $data_source = null;
	// private $data_project = null;
	// public function __construct($name, $data_source, $data_project) {
	// $this->name = $name;
	// $this->data_source = $data_source;
	// $this->data_project = $data_project;
	// }
	// public function upload_items() {
	// $project_name = $this->name; // $this->collection . " (" . basename ( $sip_folder ) . ")";
	// $project_data_path = $this->data_source; // configuration::get_solr_data_dir_path () . "/" . $project_name;
	// $cmd = "cp -r \"" . $this->data_source . "/\" \"" . $this->data_project . "\"";
	// exec ( $cmd );

	// $cmd = "cd \"$project_data_path\"; sh \"" . Configuration::get_path_solr_ingest_script () . "\" -p \"./\" -s \"Internet Archive\" -l \"$project_name\"-inc";
	// echo "INGEST: " . $cmd . PHP_EOL . PHP_EOL;
	// exec ( $cmd, $op );
	// print_r ( $op );
	// }
}

/**
 *
 * @author subhayan
 *        
 */
class Mail {
}

?>

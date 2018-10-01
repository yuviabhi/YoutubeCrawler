<?php


class AIPExport { // extends ExportHandler
	private $dspace_version = null;
	private $output_folder_path = null;
	private $template_file_path = "/assets/templates";
	private $community_schema_file = "AIP_mets_schema_community.xml";
	private $collection_schema_file = "AIP_mets_schema_collection.xml";
	private $item_schema_file = "AIP_mets_schema_item.xml";

	/**
	 *
	 * @param string $dspace_version
	 * @param string $output_folder
	 */
	public function __construct(string $dspace_version, string $output_folder) {
		$this->dspace_version = "DSpace " . $dspace_version;
		$this->template_file_path = $_SERVER ["DOCUMENT_ROOT"] . $this->template_file_path;
		$this->output_folder_path = $output_folder;
	}

	/**
	 *
	 * @param array $items
	 */
	public function create_items(array $items) {
		foreach ( $items as $item ) {
			$this->create_item ( $item->id, $item->parent_id, $item->metadata, array () );
		}
	}
}
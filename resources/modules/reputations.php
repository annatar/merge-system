<?php
/**
 */

class Converter_Module_Reputations extends Converter_Module
{
	public $default_values = array(
		'uid' => 0,
		'adduid' => 0,
		'pid' => 0,
		'reputation' => 0,
		'dateline' => 0,
		'comments' => ''
	);

	/**
	 * Insert reputation into database
	 *
	 * @param pm The insert array going into the MyBB database
	 */
	public function insert($data)
	{
		global $db, $output;

		$this->debug->log->datatrace('$data', $data);

		$output->print_progress("start", $data[$this->settings['progress_column']]);

		$unconverted_values = $data;

		// Call our currently module's process function
		$data = $converted_values = $this->convert_data($data);

		// Should loop through and fill in any values that aren't set based on the MyBB db schema or other standard default values
		$data = $this->process_default_values($data);

		foreach($data as $key => $value)
		{
			$insert_array[$key] = $db->escape_string($value);
		}

		$this->debug->log->datatrace('$insert_array', $insert_array);

		$db->insert_query("reputation", $insert_array);
		$reputationid = $db->insert_id();

		if(!defined("IN_TESTING"))
		{
			$this->after_insert($unconverted_values, $converted_values, $reputationid);
		}

		$this->increment_tracker('reputations');

		$output->print_progress("end");

		return $reputationid;
	}
}

?>
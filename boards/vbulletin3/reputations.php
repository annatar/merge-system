<?php
/**
 */

// Disallow direct access to this file for security reasons
if(!defined("IN_MYBB"))
{
	die("Direct initialization of this file is not allowed.<br /><br />Please make sure IN_MYBB is defined.");
}

class VBULLETIN3_Converter_Module_Reputations extends Converter_Module_Reputations {

	var $settings = array(
		'friendly_name' => 'reputations',
		'progress_column' => 'reputationid',
		'default_per_screen' => 1000,
	);

	function import()
	{
		global $import_session;

		$query = $this->old_db->simple_select("reputation", "*", "", array('order_by' => 'reputationid', 'order_dir' => 'asc', 'limit_start' =>$this->trackers['start_reputation'], 'limit' => $import_session['reputation_per_screen']));
		while($post = $this->old_db->fetch_array($query))
		{
			$this->insert($post);
		}
	}

	function convert_data($data)
	{
		global $db;

		// vBulletin 3 values
		$insert_data['uid'] = $this->get_import->uid($data['userid']);
		$insert_data['adduid'] = $this->get_import->uid($data['whoadded']);
		$insert_data['pid'] = $this->get_import->pid($data['postid']);
		$insert_data['reputation'] = $data['reputation'];
		$insert_data['dateline'] = $data['dateline'];
		$insert_data['comments'] = $data['reason'];

		return $insert_data;
	}

	function after_insert($data, $insert_data, $reputationid)
	{
		global $db;

		$query = $db->simple_select("users", "reputation", "uid='".$insert_data['uid']."'", array('limit' => 1));
		$currentReputation = $db->fetch_field($query, "reputation");
		$db->free_result($query);
		$db->update_query("users", array('reputation' => $currentReputation + $insert_data['reputation']), "uid = '{$insert_data['uid']}'");
	}

	function fetch_total()
	{
		global $import_session;

		// Get number of reputation
		if(!isset($import_session['total_reputations']))
		{
			$query = $this->old_db->simple_select("reputation", "COUNT(*) as count");
			$import_session['total_reputations'] = $this->old_db->fetch_field($query, 'count');
			$this->old_db->free_result($query);
		}

		return $import_session['total_reputations'];
	}
}

?>
<?php
/**
 * MyBB 1.6
 * Copyright 2009 MyBB Group, All Rights Reserved
 *
 * Website: http://www.mybb.com
 * License: http://www.mybb.com/about/license
 */

// Disallow direct access to this file for security reasons
if(!defined("IN_MYBB"))
{
	die("Direct initialization of this file is not allowed.<br /><br />Please make sure IN_MYBB is defined.");
}

class VBULLETIN3_Converter_Module_Attachments extends Converter_Module_Attachments {

	var $settings = array(
		'friendly_name' => 'attachments',
		'progress_column' => 'attachmentid',
		'default_per_screen' => 20,
	);

	function pre_setup()
	{
		$this->check_attachments_dir_perms();
	}

	function import()
	{
		global $import_session;

		$query = $this->old_db->simple_select("attachment", "*", "", array('limit_start' => $this->trackers['start_attachments'], 'limit' => $import_session['attachments_per_screen']));
		while($attachment = $this->old_db->fetch_array($query))
		{
			$this->insert($attachment);
		}
	}

	function convert_data($data)
	{
		$insert_data = array();

		// vBulletin 3 values
		$insert_data['import_aid'] = $data['attachmentid'];
		$insert_data['filetype'] = $this->get_attach_type($data['extension']);

		// Check if it is it an image
		switch(strtolower($insert_data['filetype']))
		{
			case "image/gif":
			case "image/jpeg":
			case "image/x-jpg":
			case "image/x-jpeg":
			case "image/pjpeg":
			case "image/jpg":
			case "image/png":
			case "image/x-png":
				$is_image = 1;
				break;
			default:
				$is_image = 0;
				break;
		}

		// Should have thumbnail if it's an image
		if($is_image == 1)
		{
			$insert_data['thumbnail'] = 'SMALL';
		}
		else
		{
			$insert_data['thumbnail'] = '';
		}

		$posthash = $this->get_import->post_attachment_details($data['postid']);
		$insert_data['pid'] = $posthash['pid'];
		if($posthash['posthash'])
		{
			$insert_data['posthash'] = $posthash['posthash'];
		}
		else
		{
			$insert_data['posthash'] = md5($posthash['tid'].$posthash['uid'].random_str());
		}

		$insert_data['uid'] = $this->get_import->uid($data['userid']);
		$insert_data['filename'] = $data['filename'];
		$insert_data['attachname'] = "post_".$insert_data['uid']."_".$data['dateline'].".attach";
		$insert_data['filesize'] = $data['filesize'];
		$insert_data['downloads'] = $data['counter'];
		$insert_data['visible'] = $data['visible'];

		if($data['thumbnail'])
		{
			$insert_data['thumbnail'] = str_replace(".attach", "_thumb.{$data['extension']}", $insert_data['attachname']);
		}

		return $insert_data;
	}

	function after_insert($data, $insert_data, $aid)
	{
		global $mybb, $db;
	
		// Transfer attachments
		$targetFilePath = $mybb->settings['uploadspath']."/";
		if (empty($data['filedata']))
		{
			// the attachment is on the filesystem not in the database
			$uidDigitArray = str_split(strval($data['userid']));
			// Set sourceFilePath to full path of vB attachments, ex:  /srv/www/vhosts/myhost/attachments/
			$sourceFilePath = '';
			if (empty($sourceFilePath))
			{
				// delete the record we were processing from the database and then die
				$db->delete_query("attachments", "aid = '{$insert_data['aid']}'");
				die("<span style=\"font-weight:bold;font-size:200%;\">You must set the sourceFilePath in boards/vbulletin3/attachments.php</span>");
			}
			foreach ($uidDigitArray as $key => $uidDigit)
			{
				$sourceFilePath.=$uidDigit.'/';
			}
			$sourceFilePath.=$data['attachmentid'].".attach";
			if(file_exists($sourceFilePath))
			{
				$insert_data['attachname'] = date('Yd', $data['dateline'])."/"."post_".$data['userid']."_".$data['dateline']."_".hash('sha256', random_str()).".attach";
			}
			else
			{
				$insert_data['attachname'] = date('Yd', $data['dateline'])."/"."post_".$data['userid']."_".$data['dateline']."_".$data['filehash'].".attach";
			}

			$db->update_query("attachments", array('attachname' => $insert_data['attachname']), "aid = '{$insert_data['aid']}'");
			$targetFilePath.=$insert_data['attachname'];
		}
		else
		{
			$targetFilePath.=$insert_data['attachname'];
		}

		if(file_exists($targetFilePath))
		{
			// a matching file already exists...
		}
		else
		{
			if (file_exists($sourceFilePath))
			{
				if(!file_exists(dirname($targetFilePath)))
				{
					// make sure the path we are going to copy to exists
					mkdir(dirname($targetFilePath), 0777, true);
				}
				$file = @fopen($targetFilePath, 'w');
				if($file)
				{
					if (empty($data['filedata']))
					{
						if(file_exists($sourceFilePath))
						{
							copy($sourceFilePath, $targetFilePath);
						}
					}
					else
					{
						@fwrite($file, $data['filedata']);
					}
				}
				else
				{
					$this->board->set_error_notice_in_progress("Error transferring the attachment (ID: {$aid})");
				}
				@fclose($file);
			}
		}
		@my_chmod($targetFilePath, '0777');
		if(file_exists($targetFilePath))
		{
			// make sure the filehash column exists
			if(!$db->field_exists("filehash", "attachments"))
			{
				$db->query("ALTER TABLE ".TABLE_PREFIX."attachments ADD `filehash` VARCHAR(128) NOT FULL default ''");
			}
			$filehash = hash_file('sha256', $targetFilePath);
			$db->update_query("attachments", array('filehash' => $filehash), "aid = '{$insert_data['aid']}'");
		}

		// Transfer attachment thumbnails
		$insert_data['thumbnail'] = str_replace(".attach", "_thumb.{$data['extension']}", $insert_data['attachname']);
		$db->update_query("attachments", array('thumbnail' => $insert_data['thumbnail']), "aid = '{$insert_data['aid']}'");
		
		if(file_exists($insert_data['thumbnail']))
		{
			// a matching file already exists...
		}
		else
		{
			$targetFilePath = str_replace(".attach", "_thumb.{$data['extension']}", $targetFilePath);
			if(!file_exists(dirname($targetFilePath)))
			{
				// make sure the path we are going to copy to exists
				mkdir(dirname($targetFilePath), 0777, true);
			}
			$file = @fopen($targetFilePath, 'w');
			if($file)
			{
				if (empty($data['thumbnail']))
				{
					$sourceFilePath = str_replace(".attach", ".thumb", $sourceFilePath);
					if(file_exists($sourceFilePath))
					{
						copy($sourceFilePath, $targetFilePath);
					}
				}
				else if($data['thumbnail'])
				{
					@fwrite($file, $data['thumbnail']);
				}
			}
			else
			{
				$this->board->set_error_notice_in_progress("Error transferring the attachment (ID: {$aid})");
			}
			@fclose($file);
		}
		if(file_exists($targetFilePath))
		{
			@my_chmod($targetFilePath, '0777');
		}

		if(!$posthash)
		{
			// Restore connection
			$db->update_query("posts", array('posthash' => $insert_data['posthash']), "pid = '{$insert_data['pid']}'");
		}
	}

	/**
	 * Get a attachment mime type from the vB database
	 *
	 * @param string Extension
	 * @return string The mime type
	 */
	function get_attach_type($ext)
	{
		$query = $this->old_db->simple_select("attachmenttype", "mimetype", "extension = '{$ext}'");
		$mimetype = unserialize($this->old_db->fetch_field($query, "mimetype"));

		$results = str_replace('Content-type: ', '', $mimetype[0]);
		$this->old_db->free_result($query);

		return $results;
	}

	function fetch_total()
	{
		global $import_session;

		// Get number of attachments
		if(!isset($import_session['total_attachments']))
		{
			$query = $this->old_db->simple_select("attachments", "COUNT(*) as count");
			$import_session['total_attachments'] = $this->old_db->fetch_field($query, 'count');
			$this->old_db->free_result($query);
		}

		return $import_session['total_attachments'];
	}
}

?>
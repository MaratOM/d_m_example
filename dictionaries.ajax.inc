<?php

/**
 *  path :: 'dictionaries/word/add/comment'
 */
function _dictionaries_word_add_comment($user_word_nid, $comment_body = NULL, $author_name = NULL) {
	global $user;
	
	//!TODO $comment_body sanitazing
	$cid = db_query("SELECT cid FROM comment WHERE nid = :user_word_nid", array(':user_word_nid' => $user_word_nid))->fetchField();
	comment_delete($cid);
	
	//$comment_body = $_POST['comment_body']['und']['0']['value'];
	if(isset($comment_body) && !empty($comment_body) && empty($author_name)) {
		$comment = new stdClass();
		$comment->nid = $user_word_nid;
		$comment->uid = $user->uid;
		$comment->name = $user->name;	
		$comment->subject = 'no subject';
		$comment->comment_body[LANGUAGE_NONE][0]['format'] = 'plain_text';	
		$comment->comment_body[LANGUAGE_NONE][0]['value'] = $comment_body;	
		$comment->language = LANGUAGE_NONE;
		comment_submit($comment);		
		comment_save($comment);
	}
	else {
		watchdog(
			'dictionaries',
			'Attempt to add word comment with author field filled "' . $author_name . '"',
			null,
			WATCHDOG_INFO,
			null);		
		return drupal_json_output('nodata');
	}
}

/**
 *  path :: 'dictionaries/word/show/subtitle'
 */
function _dictionaries_word_show_subtitle($subtitle_nid) {
	$subtitle_data = db_query("SELECT fdb.body_value AS body, n.title AS title
										FROM field_data_body AS fdb
										INNER JOIN field_data_field_subtitle_movie AS fsm
										ON fdb.entity_id = fsm.entity_id
										INNER JOIN node AS n
										ON fsm.field_subtitle_movie_nid = n.nid
										WHERE fdb.entity_id = :subtitle_nid",
									array(':subtitle_nid' => $subtitle_nid))->fetchAssoc();
	$ouput  = '<div class="tooltip-title">' . $subtitle_data['title'] . '</div>';
	$ouput .= '<div class="tooltip-body">' . $subtitle_data['body'] . '</div>';	

	return drupal_json_output($ouput);	
}

/**
 *  path :: 'dictionaries/word/delete'
 */
function _dictionaries_word_delete($user_word_nid, $last_dict_user_word) {
	if($last_dict_user_word == 1) {
		$dict_nid_res = db_query("SELECT field_user_word_dict_nid as dict_nid
													FROM field_data_field_user_word_dict 
													WHERE field_user_word_dict_nid = 
														(SELECT field_user_word_dict_nid as dict 
														FROM field_data_field_user_word_dict 
														WHERE entity_id=:user_word_nid)",
												 array(':user_word_nid' => $user_word_nid));
		if($dict_nid_res->rowCount() <= 1) {
			$dict_nid_rec = $dict_nid_res->fetchAssoc();
			Dictionary::deleteDict(Dictionary::movieNid($dict_nid_rec['dict_nid']));
		}
	}
	node_delete($user_word_nid);
	
	if(isset($dict_nid_rec) && !empty($dict_nid_rec)) {
		drupal_json_output(1);
	}
}

/**
 *  path :: 'dictionaries/dict/import'
 */
function _dictionaries_dict_import($movie_nid, $dict_owner_uid, $dict_nid) {
	global $user;
	$is_already_imported = FALSE;
	$imported_words_quantity = array();
	$flag_dict_open = flag_get_flag('dict_open');
	$flag_notes_open = flag_get_flag('notes_open');
	if($user->uid == $dict_owner_uid || !$flag_dict_open->is_flagged($dict_nid, $dict_owner_uid)) {
		return;
	}
	else {
		$dict_owner_dict = array();
		$sql = "SELECT fuww.field_user_word_word_nid AS word_nid,
										 fuws.field_user_word_subtitle_nid AS subtitle_nid,
										 nw.title AS title
									FROM field_data_field_user_word_movie AS fuwm
									INNER JOIN field_data_field_user_word_subtitle AS fuws
									ON fuwm.entity_id = fuws.entity_id
									INNER JOIN field_data_field_user_word_word AS fuww
									ON fuwm.entity_id = fuww.entity_id

									INNER JOIN node AS nw
									ON fuww.field_user_word_word_nid = nw.nid
									
									INNER JOIN node AS n
									ON fuwm.entity_id = n.nid									
									WHERE n.uid = :user_uid
									AND fuwm.field_user_word_movie_nid = :movie_nid";
									
		$result = db_query($sql,
								array(':user_uid' => $dict_owner_uid, ':movie_nid' => $movie_nid));
		while($record = $result->fetchAssoc()) {
			$dict_owner_dict[] = array(
				$record['word_nid'],
				$record['title'],
				$record['subtitle_nid'],
			);
		}
	
		$current_user_dict_nid = db_query("SELECT n.nid
																		FROM node AS n
																		INNER JOIN field_data_field_dictionary_movie AS fdm
																		ON n.nid = fdm.entity_id
																		WHERE n.uid = :user_uid
																		AND fdm.field_dictionary_movie_nid = :movie_nid",
																	array(':user_uid' => $user->uid, ':movie_nid' => $movie_nid))
																->fetchField(); 
		if(!empty($current_user_dict_nid)) {
			$is_already_imported = db_query("SELECT *
								 FROM field_data_field_dictionary_import_user
								 WHERE entity_id = :dict_nid
								 AND field_dictionary_import_user_uid = :user_uid",
								array(':dict_nid' => $dict_nid, ':user_uid' => $user->uid))
							->rowCount();
			if(empty($is_already_imported)) {
				$current_user_dict = array();
				$result = db_query($sql,
											array(':user_uid' => $user->uid, ':movie_nid' => $movie_nid));
				while($record = $result->fetchAssoc()) {
					$current_user_dict[] = array(
						$record['word_nid'],
						$record['title'],
						$record['subtitle_nid'],
					);
				}
				foreach($dict_owner_dict as $dict_owner_word) {
					if(!in_array($dict_owner_word, $current_user_dict)){
						$imported_words_quantity[] = _subtitles_uw_node_save($dict_owner_word[0], $dict_owner_word[1], $dict_owner_word[2], $movie_nid, $current_user_dict_nid, $dict_nid);
					}
				}
				
				$current_user_dict_obj = node_load($current_user_dict_nid);
				$current_user_dict_obj->field_dictionary_imported_words[LANGUAGE_NONE][0]['value'] += count($imported_words_quantity);	
				field_attach_update('node', $current_user_dict_obj);				
			}
		}
		else {
			$movie_title = db_query("SELECT title FROM node WHERE nid = :movie_nid", array(':movie_nid' => $movie_nid))->fetchField();
			$current_user_dict_nid = _subtitles_dict_node_save($movie_nid, $movie_title, count($dict_owner_dict));			
			foreach($dict_owner_dict as $dict_owner_word) {
				_subtitles_uw_node_save($dict_owner_word[0], $dict_owner_word[1], $dict_owner_word[2], $movie_nid, $current_user_dict_nid, $dict_nid);
			}
		}
		if(empty($is_already_imported)) {
			//Notes handling
			if($flag_notes_open->is_flagged($dict_nid, $dict_owner_uid)) {
				$result_notes = db_query("SELECT cb.comment_body_value AS comment_body,
																 fuww.field_user_word_word_nid AS word_nid,
																 fuws.field_user_word_subtitle_nid AS subtitle_nid
																 
																 FROM field_data_comment_body AS cb
																 
																 INNER JOIN comment AS c
																 ON cb.entity_id = c.cid
																 
																 INNER JOIN field_data_field_user_word_movie AS fuwm
																 ON c.nid = fuwm.entity_id
	
																 INNER JOIN field_data_field_user_word_word AS fuww
																 ON c.nid = fuww.entity_id
																 
																 INNER JOIN field_data_field_user_word_subtitle AS fuws
																 ON c.nid = fuws.entity_id
																 
																 WHERE fuwm.field_user_word_movie_nid = :movie_nid
																 AND c.uid = :user_uid",
															array(':movie_nid' => $movie_nid, ':user_uid' => $dict_owner_uid));	
				while($record_notes = $result_notes->fetchAssoc()) {
					$new_user_word_nid = db_query("SELECT n.nid
																 FROM node AS n
																 
																 INNER JOIN field_data_field_user_word_word AS fuww
																 ON n.nid = fuww.entity_id
																 
																 INNER JOIN field_data_field_user_word_subtitle AS fuws
																 ON n.nid = fuws.entity_id
																 
																 WHERE n.uid = :user_uid
																 AND fuww.field_user_word_word_nid = :word_nid
																 AND fuws.field_user_word_subtitle_nid = :subtitle_nid",
															 array(':user_uid' => $user->uid, ':word_nid' => $record_notes['word_nid'], ':subtitle_nid' => $record_notes['subtitle_nid']))
														 ->fetchField();
	
					$comments_to_del_arr = array();
					$comments_to_del_res = db_query("SELECT cid FROM comment WHERE nid = :user_word_nid",
																			array(':user_word_nid' => $new_user_word_nid));
					while($comments_to_del_rec = $comments_to_del_res->fetchAssoc()) {
						$comments_to_del_arr[] = $comments_to_del_rec['cid'];
					}
					comment_delete_multiple($comments_to_del_arr);
					
					$comment = new stdClass();
					$comment->nid = $new_user_word_nid;
					$comment->uid = $user->uid;
					$comment->name = $user->name;	
					$comment->subject = 'no subject';
					$comment->comment_body[LANGUAGE_NONE][0]['format'] = 'plain_text';	
					$comment->comment_body[LANGUAGE_NONE][0]['value'] = $record_notes['comment_body'];	
					$comment->language = LANGUAGE_NONE;
					comment_submit($comment);		
					comment_save($comment);			
				}
			}

			//Imported Dict Data Handling
			$imported_dict_obj = node_load($dict_nid);
			if(!empty($imported_dict_obj->field_dictionary_import_user[LANGUAGE_NONE])) {
				array_push($imported_dict_obj->field_dictionary_import_user[LANGUAGE_NONE], array('uid' => $user->uid));
			}
			else {
				$imported_dict_obj->field_dictionary_import_user[LANGUAGE_NONE][0]['uid'] = $user->uid;
			}
			$imported_dict_obj->field_dictionary_import_count[LANGUAGE_NONE][0]['value']++;	
			field_attach_update('node', $imported_dict_obj);
		}
	}	
}

/**
 *  path :: 'dictionaries/dict/delete/%'
 */
function _dictionaries_dict_delete($movie_nid) {
	Dictionary::deleteDict($movie_nid);
}

/**
 *  path :: 'dictionaries/dict/load'
 */
function _dictionaries_dictionary_output_ajax($nid, $lang_tid, $lang_to, $dict_owner_uid = NULL) {
	if(empty($dict_owner_uid)) {
		$dictionary = new DictsFilmDictionary($lang_tid, $nid);
	}
	else {
		$dictionary = new FilmOpenFilmDictionary($lang_tid, $nid, null, $dict_owner_uid);		
	}

	return  drupal_json_output($dictionary->dictionaryOutput());	
}
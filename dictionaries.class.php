<?php

function getGlobals() {
	global $user;
	global $language;
	
	return array(
		'user' => $user,
		'language' => $language,
	);
}		

class ABCDTools {
	public static function JqueryAccordion($accordionLines) {
		drupal_add_library('system', 'ui.accordion');
		drupal_add_js('jQuery(document).ready(function(){jQuery("#accordion").accordion({clearStyle: "true", autoHeight: "true", collapsible: "false", active: "none"});});', 'inline');
		
		$accordion = '<div id="accordion">';
		foreach($accordionLines as $accordionLine) {
			if(isset($accordionLine->href)) {
				$accordionLine->cssClass = 'accordion-line-load';
			}
			else {
				$accordionLine->cssClass = '';
				$accordionLine->href = '';				
			}
			
			$accordionItem = '<h3>
				<a class="' . $accordionLine->cssClass . '" href="' . $accordionLine->href . '">' . $accordionLine->title . '</a>
			</h3>';		
			$accordionItem .= '<div>' . theme('image', array('path' => base_path() . path_to_theme() . '/images/ajax-loader-hor-blue.gif', 'attributes' => array('class' => array('ajax-loader')))) . '</div>';			
			$accordion .= $accordionItem;
		}
		$accordion .= '</div>';
		
		return $accordion;
	}
}

class DictionaryJavascript {
	public function __construct() {
		//drupal_add_library('filmui', 'livequery');
		drupal_add_library('filmui', 'soundmanager2');
		drupal_add_library('filmui', 'jgrow');	
		drupal_add_library('filmui', 'printarea');
		drupal_add_library('filmui', 'backtotop');		
		drupal_add_library('filmui', 'qtip');
		drupal_add_library('system', 'ui.dialog');
		drupal_add_js('misc/jquery.form.js');
		drupal_add_js('misc/jquery.cookie.js');		
		drupal_add_js(drupal_get_path('module', 'dictionaries') . '/js/dictionaries.js');		
	}
}

class DictionarySinglton {
	private static $instance;
	private $user;
	private $language;
	
	private function __construct() {
		new DictionaryJavascript();
		$this->classGetGlobals();
	}
	
	public static function getInstance() {
		if(empty(self::$instance)) {
			self::$instance = new DictionarySinglton();
		}

		return self::$instance;
	}
	
	private function classGetGlobals(){
		$globalsArray = getGlobals();
		$this->user = $globalsArray['user'];
		$this->language = $globalsArray['language'];		
	}
	
	public function getUser() {
		return $this->user;
	}
	
	public function getLanguage() {
		return $this->language;
	}	
}

abstract class Dictionary {
  public $user;
	public $language;
	public $langTid;  
  public $sqlResult;
	public $dictOwnerUid;	
	public $dictWordsQuantity;
	public $showComments = TRUE;
	public $languageLine = TRUE;
  
  protected function __construct($langTid = '') {
		$dictionarySinglton = DictionarySinglton::getInstance();
    $this->user = $dictionarySinglton->getUser();
    $this->language = $dictionarySinglton->getLanguage();
    $this->langTid = $langTid;
    $this->dictOwnerUid = $this->user->uid;		
    $this->dictWordsQuantity = $langTid;		
  }
	
	public static function dictNid($movie_nid, $user_uid) {
		return db_query("SELECT fdm.entity_id
														FROM field_data_field_dictionary_movie AS fdm
														INNER JOIN node AS n
														ON fdm.entity_id = n.nid
														WHERE fdm.field_dictionary_movie_nid = :movie_nid
														AND n.uid = :user_uid",
													array(':movie_nid' => $movie_nid, ':user_uid' => $user_uid))
												->fetchField();
	}
	
	public static function movieNid($dict_nid) {
		return db_query("SELECT field_dictionary_movie_nid
														FROM field_data_field_dictionary_movie
														WHERE entity_id = :dict_nid",
													array(':dict_nid' => $dict_nid))
												->fetchField();
	}	
	
	public static function deleteDict($movie_nid) {
		global $user;
		$nodes_to_delete_arr = array();
		$comments_to_delete_arr = array();
		$import_dicts_arr = array();
		
		$dict_nid = Dictionary::dictNid($movie_nid, $user->uid);
		$nodes_to_delete_arr[] = $dict_nid;
		$wu_data_to_delete_res = db_query("SELECT fuwm.entity_id AS entity_id,
																			fuwid.field_user_word_import_dict_nid AS import_dict_nid,
																			c.cid AS cid
																			FROM field_data_field_user_word_movie AS fuwm
																			
																			INNER JOIN node AS n
																			ON fuwm.entity_id = n.nid
																			
																			LEFT OUTER JOIN field_data_field_user_word_import_dict AS fuwid
																			ON fuwm.entity_id = fuwid.entity_id
																			
																			LEFT OUTER JOIN comment AS c
																			ON n.nid = c.nid
																			
																			WHERE fuwm.field_user_word_movie_nid = :movie_nid
																			AND n.uid = :user_uid",
																		array(':movie_nid' => $movie_nid, ':user_uid' => $user->uid));
		while($wu_data_to_delete_rec = $wu_data_to_delete_res->fetchAssoc()) {
			$nodes_to_delete_arr[] = $wu_data_to_delete_rec['entity_id'];
			if($wu_data_to_delete_rec['import_dict_nid'] != NULL) {
				$import_dicts_arr[] = $wu_data_to_delete_rec['import_dict_nid'];
			}		
			if($wu_data_to_delete_rec['cid'] != NULL) {
				$comments_to_delete_arr[] = $wu_data_to_delete_rec['cid'];
			}
		}
		
		node_delete_multiple($nodes_to_delete_arr);
		comment_delete_multiple($comments_to_delete_arr);
		if(!empty($import_dicts_arr)) {
			$import_dicts_arr = array_unique($import_dicts_arr);
			foreach($import_dicts_arr as $import_dict_nid) {
				db_delete('field_data_field_dictionary_import_user')
					->condition('entity_id', $import_dict_nid)
					->condition('field_dictionary_import_user_uid', $user->uid)		
					->execute();
			}	
		}
		
		if(!in_array(PREMIUM_ACCOUNT, $user->roles) && !in_array(LIMITED_ACCOUNT, $user->roles)) {
			if(_subtitles_user_dicts_quan() < MAX_DICTS_FOR_LIMITED_ACCOUNT) {
        $role = user_role_load_by_name(LIMITED_ACCOUNT);
				$user->roles[$role->rid] = LIMITED_ACCOUNT;
				user_save($user); 
			}
		}		
	}	
	
	
  protected function dictsLanguagesLine() {
    $languages_arr = array();
		$q_arr = explode('/', $_GET['q']);
		if($q_arr[0] != 'movie'){
			array_pop($q_arr);
			$path = implode('/', $q_arr);
			$result = db_query("SELECT DISTINCT ttd.tid, ttd.name, fm.uri
											FROM taxonomy_term_data AS ttd
											INNER JOIN field_data_field_movie_language AS fml
											ON ttd.tid = fml.field_movie_language_tid
											
											INNER JOIN field_data_field_voc_language_image AS fvli
											ON ttd.tid = fvli.entity_id
											
											INNER JOIN file_managed AS fm
											ON fvli.field_voc_language_image_fid = fm.fid										
											
											INNER JOIN field_data_field_dictionary_movie AS fdm
											ON fml.entity_id = fdm.field_dictionary_movie_nid								
											INNER JOIN node AS n
											ON fdm.entity_id = n.nid										
											WHERE ttd.vid = 2
											AND n.uid = :user_uid
											ORDER BY ttd.tid ASC",
											array(':user_uid' => $this->user->uid));
			while($row = $result->fetchAssoc()) {
				$languages_arr[] = l(theme('image', array('path' => $row['uri'], 'title' => $row['name'])), $path . '/' . $row['tid'], array('html' => TRUE));				
			}
			$return = theme('item_list', array('items' => $languages_arr, 'attributes' => array('class' => array('langs-line'))));
		}
		else {
			$return = FALSE;
		}
		
		return $return;
  }
	
	protected function sqlResultRowCount() {
		$this->dictWordsQuantity = $this->sqlResult->rowCount();		
	}
	
	protected function noDictsLinks() {
		return '<p>If you are sure that there should be some please '
				.	l('refresh the page. ', current_path())
				. '<br />'
				.	l('How to create film vocabulary?', 'help/faq', array('fragment' => 'n2435313'))
			. '</p>';
	}

  public function dictionaryOutput() {
    $user = $this->user;
    $language = $this->language;
    $result = $this->sqlResult;
    $lang_tid = $this->langTid;
    $dict_owner_uid = $this->dictOwnerUid ? $this->dictOwnerUid : NULL;		
    $dict_owner = $dict_owner_uid == $this->user->uid ? TRUE : FALSE;
    
    $words_rows = array();
    while($record = $result->fetchAssoc()) {
      $language_link = _subtitles_language_link($record['subtitle'], 'subtitle');			
      $comment_body = '';
      if($record['comment_count'] != 0) {
        $comment_body = db_query("SELECT cb.comment_body_value AS comment_body
                          FROM field_data_comment_body AS cb
                          INNER JOIN comment AS c
                          ON cb.entity_id = c.cid
                          WHERE c.nid = :word_nid
                          AND c.uid = :user_uid LIMIT 0,1",
                        array(
													':word_nid' => $record['user_word_nid'],
													':user_uid' => $dict_owner_uid)
												)
											->fetchField();
      }
      $comment_body = !empty($comment_body) ? $comment_body : '';      
      $comments_html =
          $dict_owner
					?	'<div id="word-' . $record['user_word_nid'] . '" class="word-wraper">'
						. '<form class="comment-form" accept-charset="UTF-8" method="post">'
						. '<input type="text" id="edit-author" name="author" value="" size="60" maxlength="128" class="form-text">'
						. '<textarea rows="1" name="comment_body[und][0][value]" class="text-full form-textarea">'
							. $comment_body
						. '</textarea>'
						. '<input class="form-submit" type="submit" value="Save" name="op">'
						. '</form>'
						. '</div>'
          : '<form><textarea rows="1" name="comment_body[und][0][value]" class="text-full form-textarea">'
						. $comment_body
						. '</textarea></form>';


			if($this->dictOwnerUid == $this->user->uid)  {
				$dict_owner_links = DictionaryLink::dictOwnerLinks($record['user_word_nid']);
			}
			
      $table_header = array(
        '',
        l('', base_path() . path_to_theme() . '/images/eye.png', array('attributes' => array('id' => 'eye-word-column' . (isset($this->dictNid) ? '-' . $this->dictNid : ''), 'class' => array('eye', 'eye-word', 'eye-see')), 'html' => TRUE)),
        '',
        l('', base_path() . path_to_theme() . '/images/eye.png', array('attributes' => array('id' => 'eye-translation-column' . (isset($this->dictNid) ? '-' . $this->dictNid : ''), 'class' => array('eye', 'eye-translation', 'eye-see')), 'html' => TRUE)),
      );

      $word_audio = isset($record['audio_uri'])
        ?	'<div class="audio-player-wrapper">'
          . theme('audiofield_players_wpaudioplayer', array(
            'player_path' => '/' . variable_get('audiofield_players_dir', 'sites/all/libraries/player') . '/audio-player/player.swf',
            'audio_file' => file_create_url($record['audio_uri']),					
          )
        )
        . '</div>'	
        : '';
      $lang_word = '<div class="lang-word"><nobr>'
          . l($record['title'],
          '',
          array('attributes' => array('class' => array('word-link'), 'id' => $record['subtitle'], 'target'=>'_blank'), 'html' => TRUE)) . ' '
          . '<span class="transcription">' . $record['transcription'] . '</span>'
          . '</nobr></div>';				
      $dictionaries_links = '<div class="dict-links">'
          . l('', base_path() . path_to_theme() . '/images/eye.png', array('attributes' => array('id' => 'eye-left-' . $record['word_nid'], 'class' => array('eye', 'eye-left', 'eye-see')), 'html' => TRUE))
          . implode('', _dictionaries_out_dicts_langs($record['title'], $lang_tid, $language->language)) 
          . l('', base_path() . path_to_theme() . '/images/eye.png', array('attributes' => array('id' => 'eye-right-' . $record['word_nid'], 'class' => array('eye', 'eye-right', 'eye-see')), 'html' => TRUE))
          . '</div>';				
      $translation =  '<div class="translation">'
          . $record['translation']
          . '</div>';
      $translation_note_links	=
					'<a href="" class="translation-note add-word-note icon-plus" title="Add note"></a>'
        . '<a href="" class="translation-note collapse icon-arrow-close" title="Hide note"></a>'
				. '<a href="" class="translation-note open icon-arrow-open" title="Show note"></a>';
				
      $words_rows[] = array(
        array(
					'data' => $dict_owner ? $dict_owner_links : '',
					'class' => array('dict-td-utils-icons')
				),
        array(
					'data' => $word_audio . $lang_word,
					'class' => array('dict-td-word')
				),
        array(
					'data' => $dictionaries_links,
					'class' => array('dict-td-dicts-icons')
				),
        array(
          'data' => '<div class="dict-td-translation-wraper">'
                    . ($dict_owner && $this->showComments ? $translation_note_links : '')	
                    . $translation
                    . ($this->showComments ? $comments_html : '')
                    . '</div>',
          'class' => array('dict-td-translation')),				
      );
    }
    if(!empty($words_rows)) {
      $words = '<div class="dictionary-table">' . theme('table', array('rows' => $words_rows, 'header' => $table_header)) . '</div>';		
      $return = $this->languageLine ? $this->dictsLanguagesLine() . $words : $words;
    }
    else {
      return '<p>You have no words in this vocab yet.</p>' . $this->noDictsLinks();
    }
		
    return $return;
  }
}

class CommonDictionary extends Dictionary {
  public function __construct($langTid = 1) {
    parent::__construct($langTid);
    $this->sqlResult = $this->getSqlResult(); 
  }
	
	private function getSqlResult() {
		return db_query("SELECT DISTINCT	n.title AS title,
                                n.nid AS word_nid,
                                fuww.entity_id AS user_word_nid,
                                fwt.field_word_transcription_value AS transcription,
                                fwst.field_word_short_trans_value AS translation,
                                fm.uri AS audio_uri,															
                                fuws.field_user_word_subtitle_nid AS subtitle,
                                ncs.comment_count AS comment_count
    
                       FROM node AS n
                       LEFT OUTER JOIN field_data_field_word_transcription AS fwt
                       ON n.nid = fwt.entity_id
                       LEFT OUTER JOIN field_data_field_word_short_trans AS fwst
                       ON n.nid = fwst.entity_id
                       INNER JOIN field_data_field_word_language AS fwl
                       ON n.nid = fwl.entity_id
                       LEFT OUTER JOIN field_data_field_word_audio AS fwa
                       ON n.nid = fwa.entity_id
                       LEFT OUTER JOIN file_managed AS fm
                       ON fwa.field_word_audio_fid = fm.fid											 
                       
                       INNER JOIN field_data_field_user_word_word AS fuww
                       ON n.nid = fuww.field_user_word_word_nid
                       INNER JOIN field_data_field_user_word_subtitle AS fuws
                       ON fuww.entity_id = fuws.entity_id
                       INNER JOIN node AS nuw
                       ON fuww.entity_id = nuw.nid											 
                       INNER JOIN node_comment_statistics AS ncs
                       ON nuw.nid = ncs.nid										 
                       
                       WHERE nuw.uid = :user_uid
                       AND fwl.field_word_language_tid = :lang_tid
                       AND (fwst.language = :LANGUAGE_TO
											 OR fwst.language = 'und')
                       ORDER BY n.nid DESC",
                    array(
                      ':user_uid' => $this->user->uid,
                      ':lang_tid' => $this->langTid,
                      ':LANGUAGE_TO' => $this->language->language
                    )
                  );
	}		
}

class WorkingDictionary extends Dictionary {
  public function __construct($langTid = 1) {
    parent::__construct($langTid);
    $this->sqlResult = $this->getSqlResult(); 
  }
	
	private function getSqlResult() {
		return db_query("SELECT DISTINCT	n.title AS title,
															n.nid AS word_nid,
															fuww.entity_id AS user_word_nid,
															fwt.field_word_transcription_value AS transcription,
															fwst.field_word_short_trans_value AS translation,
															fm.uri AS audio_uri,
															fuws.field_user_word_subtitle_nid AS subtitle,
															ncs.comment_count AS comment_count

										 FROM node AS n
										 LEFT OUTER JOIN field_data_field_word_transcription AS fwt
										 ON n.nid = fwt.entity_id
										 LEFT OUTER JOIN field_data_field_word_short_trans AS fwst
										 ON n.nid = fwst.entity_id
										 INNER JOIN field_data_field_word_language AS fwl
										 ON n.nid = fwl.entity_id
										 LEFT OUTER JOIN field_data_field_word_audio AS fwa
										 ON n.nid = fwa.entity_id
										 LEFT OUTER JOIN file_managed AS fm
										 ON fwa.field_word_audio_fid = fm.fid												 
										 
										 INNER JOIN field_data_field_user_word_word AS fuww
										 ON n.nid = fuww.field_user_word_word_nid
										 INNER JOIN field_data_field_user_word_subtitle AS fuws
										 ON fuww.entity_id = fuws.entity_id
										 INNER JOIN node AS nuw
										 ON fuww.entity_id = nuw.nid										 
										 INNER JOIN node_comment_statistics AS ncs
										 ON nuw.nid = ncs.nid

										 INNER JOIN flag_content AS fc
										 ON nuw.nid = fc.content_id										 
										 
										 WHERE nuw.uid = :user_uid
										 AND fwl.field_word_language_tid = :lang_tid
										 AND fc.fid = 2
										 AND fc.uid = :user_uid
										 AND (fwst.language = :LANGUAGE_TO
										 OR fwst.language = 'und')
										 ORDER BY n.nid DESC",
                  array(
                    ':user_uid' => $this->user->uid,
                    ':lang_tid' => $this->langTid,
                    ':LANGUAGE_TO' => $this->language->language
                  )
                );
	}	
  
  public function dictionaryOutput() {
    return parent::dictionaryOutput();
  } 
}

abstract class FilmDictionary extends Dictionary {
	public $movieNid;
	public $dictNid;
	public $title;
	public $href;	
	
  protected function __construct($langTid, $movieNid) {
		if(empty($langTid)) {
			$langTid = db_query("SELECT field_movie_language_tid
													 FROM field_data_field_movie_language
													 WHERE entity_id = :movie_nid",
												array('movie_nid' => $movieNid))->fetchField();
		}
    parent::__construct($langTid);
		$this->movieNid = $movieNid;
  }
	
	private function getSqlResult() {}
	private function getdictMovieHref() {}	
}

class DictsFilmDictionary extends FilmDictionary {
  public function __construct($langTid, $movieNid, $dictNid = '', $dictMovieTitle = '') {
    parent::__construct($langTid, $movieNid);
		if(empty($dictNid) && empty($dictMovieTitle)) {
			$dictData = $this->dictNidAndTitle();
			$this->dictNid = $dictData['dictNid'];
			$this->title = $dictData['dictMovieTitle'];			
		}
		else { 
			$this->dictNid = $dictNid;
			$this->title = $dictMovieTitle;
		}
		$this->href = $this->getdictMovieHref();		
    $this->sqlResult = $this->getSqlResult();
  }
	
	private function getSqlResult() {
		return db_query("SELECT  n.title AS title,
																n.nid AS word_nid,
															  fuws.entity_id AS user_word_nid,																
																fwt.field_word_transcription_value AS transcription,
																fwst.field_word_short_trans_value AS translation,
																fm.uri AS audio_uri,
																fuws.field_user_word_subtitle_nid AS subtitle,
																ncs.comment_count AS comment_count																
	
											 FROM node AS n
											 LEFT OUTER JOIN field_data_field_word_transcription AS fwt
											 ON n.nid = fwt.entity_id
											 LEFT OUTER JOIN field_data_field_word_short_trans AS fwst
											 ON n.nid = fwst.entity_id
											 INNER JOIN field_data_field_word_language AS fwl
											 ON n.nid = fwl.entity_id
											 LEFT OUTER JOIN field_data_field_word_audio AS fwa
											 ON n.nid = fwa.entity_id
											 LEFT OUTER JOIN file_managed AS fm
											 ON fwa.field_word_audio_fid = fm.fid												 
											 
											 INNER JOIN field_data_field_user_word_word AS fuww
											 ON n.nid = fuww.field_user_word_word_nid
											 INNER JOIN field_data_field_user_word_subtitle AS fuws
											 ON fuww.entity_id = fuws.entity_id
											 INNER JOIN node AS nuw
											 ON fuww.entity_id = nuw.nid											 
	
											 INNER JOIN field_data_field_subtitle_movie AS fsm
											 ON fuws.field_user_word_subtitle_nid = fsm.entity_id
											 
											 INNER JOIN node_comment_statistics AS ncs
											 ON nuw.nid = ncs.nid													 
											 
											 WHERE nuw.uid = :user_uid
											 AND fwl.field_word_language_tid = :lang_tid
											 AND fsm.field_subtitle_movie_nid = :movie_nid
											 AND (fwst.language = :LANGUAGE_TO
											 OR fwst.language = 'und')",
										array(
											':user_uid' => $this->user->uid,
											':lang_tid' => $this->langTid,
											':movie_nid' => $this->movieNid,											
											':LANGUAGE_TO' => $this->language->language
										)
									);											 		
	}
	
	private function dictNidAndTitle() {
		$result = db_query("SELECT fdm.entity_id AS nid, nt.title AS title
												FROM field_data_field_dictionary_movie AS fdm
												INNER JOIN node AS n
												ON fdm.entity_id = n.nid
												INNER JOIN node AS nt
												ON nt.nid = fdm.field_dictionary_movie_nid
												WHERE fdm.field_dictionary_movie_nid = :movie_nid
												AND n.uid = :user_uid",
											array(':movie_nid' => $this->movieNid, ':user_uid' => $this->user->uid))
										->fetchAssoc();
		return array(
				'dictNid' => $result['nid'],
				'dictMovieTitle' => $result['title'],				
			);
	}	
	
	private function getdictMovieHref() {
		return '/dictionaries/dict/load/' . $this->movieNid . '/' .  $this->langTid . '/' .  $this->language->language;		
	}

	public function isAvailableForOpening() {
		$this->sqlResultRowCount();
		$importedWordsQuantity = db_query("SELECT field_dictionary_imported_words_value
														FROM field_data_field_dictionary_imported_words
														WHERE entity_id = :dictNid",
													array(':dictNid' => $this->dictNid))
												->fetchField();
		return $this->dictWordsQuantity / 100 * 50 >= $importedWordsQuantity;
	}	
  
	public function dictionaryOutput() {
	 $dict_utils_links_left = DictionaryLink::dictUtilsLinksLeft($this);
	 $dict_utils_links_right = DictionaryLink::dictUtilsLinksRight($this);	 
		 
	 $dictUtilsLinks = '<div class="dict-utils-links">'
			 . $dict_utils_links_left
			 . $dict_utils_links_right
		 . '</div>';
	 
	 $this->languageLine = FALSE;
	 return $dictUtilsLinks . parent::dictionaryOutput();
	}   
}

class FilmOpenFilmDictionary extends FilmDictionary {
	public $dictOwnerObj;	
	public $dictImportCount;
	

  public function __construct($langTid, $movieNid, $dictNid = '', $dictOwnerUid = '', $dictImportCount = '') {
    parent::__construct($langTid, $movieNid);
		$this->dictOwnerUid = $dictOwnerUid;
		$this->dictOwnerObj = user_load($this->dictOwnerUid);
		$this->dictNid = !empty($dictNid) ? $dictNid : $this->getDictNid();
		$this->dictImportCount = $dictImportCount;
		$this->title = $this->getdictMovieTitle();		
		$this->href = $this->getdictMovieHref();		
    $this->sqlResult = $this->getSqlResult();
  }
	
	public function isDictImportedByUser() {
		return db_query("SELECT fdiu.entity_id
								 FROM field_data_field_dictionary_import_user AS fdiu
								 WHERE fdiu.entity_id = :dict_nid
								 AND fdiu.field_dictionary_import_user_uid = :user_uid",
								array(':dict_nid' => $this->dictNid, ':user_uid' => $this->user->uid))
							->rowCount();
	}		
	
	private function getSqlResult() {
		return db_query("SELECT  n.title AS title,
																n.nid AS word_nid,
															  fuws.entity_id AS user_word_nid,																
																fwt.field_word_transcription_value AS transcription,
																fwst.field_word_short_trans_value AS translation,
																fm.uri AS audio_uri,
																fuws.field_user_word_subtitle_nid AS subtitle,
																ncs.comment_count AS comment_count																
	
											 FROM node AS n
											 LEFT OUTER JOIN field_data_field_word_transcription AS fwt
											 ON n.nid = fwt.entity_id
											 LEFT OUTER JOIN field_data_field_word_short_trans AS fwst
											 ON n.nid = fwst.entity_id
											 INNER JOIN field_data_field_word_language AS fwl
											 ON n.nid = fwl.entity_id
											 LEFT OUTER JOIN field_data_field_word_audio AS fwa
											 ON n.nid = fwa.entity_id
											 LEFT OUTER JOIN file_managed AS fm
											 ON fwa.field_word_audio_fid = fm.fid												 
											 
											 INNER JOIN field_data_field_user_word_word AS fuww
											 ON n.nid = fuww.field_user_word_word_nid
											 INNER JOIN field_data_field_user_word_subtitle AS fuws
											 ON fuww.entity_id = fuws.entity_id
											 INNER JOIN node AS nuw
											 ON fuww.entity_id = nuw.nid											 
	
											 INNER JOIN field_data_field_subtitle_movie AS fsm
											 ON fuws.field_user_word_subtitle_nid = fsm.entity_id
											 
											 INNER JOIN node_comment_statistics AS ncs
											 ON nuw.nid = ncs.nid													 
											 
											 WHERE nuw.uid = :user_uid
											 AND fwl.field_word_language_tid = :lang_tid
											 AND fsm.field_subtitle_movie_nid = :movie_nid
											 AND (fwst.language = :LANGUAGE_TO
											 OR fwst.language = 'und')",
										array(
											':user_uid' => $this->dictOwnerUid,
											':lang_tid' => $this->langTid,
											':movie_nid' => $this->movieNid,											
											':LANGUAGE_TO' => $this->language->language
										)
									);											 		
	}
	
	private function getDictNid() {
		$dict_nid = db_query("SELECT fdm.entity_id
														FROM field_data_field_dictionary_movie AS fdm
														INNER JOIN node AS n
														ON fdm.entity_id = n.nid
														WHERE fdm.field_dictionary_movie_nid = :movie_nid
														AND n.uid = :user_uid",
													array(':movie_nid' => $this->movieNid, ':user_uid' => $this->dictOwnerUid))
												->fetchField();
		return $dict_nid;
	}	
	
	private function getdictMovieTitle() {
		return  '<span class="dict-owner">vocab by '
				. '<span class="vocab-by-user-name">' . $this->dictOwnerObj->name . '</span>'
				. ' (imported '
					. '<span class="vocab-imported-count">'
						. (!empty($this->dictImportCount) ? $this->dictImportCount : 0)
					. '</span>'
				. ' times)'				
			. '</span>';		
	}		

	private function getdictMovieHref() {
		return '/dictionaries/dict/load/' . $this->movieNid . '/' .  $this->langTid . '/' .  $this->language->language . '/' . $this->dictOwnerUid;		
	}
	
	private function isNotesOpen() {
		$flagNotesOpen = flag_get_flag('notes_open');
		return $flagNotesOpen->is_flagged($this->dictNid, $this->dictOwnerUid);	
	}	
  
	public function dictionaryOutput() {
		$dictOwnerLink = DictionaryLink::dictOwnerProfileLink($this);
		$dictImportLink = DictionaryLink::dictImportLink($this);
			
		$this->languageLine = FALSE;
		$this->showComments = $this->isNotesOpen();
		return $dictImportLink . parent::dictionaryOutput();
	}   
}

abstract class DictionariesList extends Dictionary {
  public $dictionaries = array();
	protected $noDictsLine;
	
	public function __construct($lang_tid = '') {
		parent::__construct($lang_tid);
		drupal_add_js(drupal_get_path('module', 'flag') . '/theme/flag.js');
		drupal_add_css(drupal_get_path('module', 'flag') . '/theme/flag.css');
    drupal_add_js('jQuery(document).ready(function(){(jQuery)(".flag-dict-open .unflag-action").attr("title", "Close this vocab"); });', 'inline');
		drupal_add_js(array('dictAjaxLoad' => 1), 'setting');		
	}
	
	protected function addDictionary(Dictionary $dictionary) {
		$this->dictionaries[] = $dictionary;
	}
	
	private function collectDictionaries() {}
	
	public function dictionaryOutput() {
		if(!empty($this->dictionaries)) {
		$accordion = ABCDTools::JqueryAccordion($this->dictionaries);
		}
		else {
			$accordion = '<p>' . $this->noDictsLine . '</p>' . $this->noDictsLinks();
		}

		return $this->dictsLanguagesLine() . $accordion;		
	}
}

class UserDictionariesList extends DictionariesList {
  public $dictionaries = array();
	
	public function __construct($lang_tid) {
		parent::__construct($lang_tid);
	}
	
	private function collectDictionaries() {
		$user_movie_dicts = db_query("SELECT n.title AS title,
																	fdm.field_dictionary_movie_nid AS movie_nid,
																	n.nid AS dict_nid
																FROM node AS n
																INNER JOIN field_data_field_dictionary_movie AS fdm
																ON n.nid = fdm.entity_id
																INNER JOIN field_data_field_movie_language AS fml
																ON fdm.field_dictionary_movie_nid = fml.entity_id																
																WHERE type = 'dictionary'
																AND n.uid = :user_uid
																AND fml.field_movie_language_tid = :lang_tid
																ORDER BY n.created",
																	array(':user_uid' => $this->user->uid, ':lang_tid' => $this->langTid));
		
		while($user_movie_dict = $user_movie_dicts->fetchAssoc()) {
			$this->addDictionary(new DictsFilmDictionary($this->langTid, $user_movie_dict['movie_nid'], $user_movie_dict['dict_nid'], $user_movie_dict['title']));
		}
	}
	
	public function dictionaryOutput() {
		$this->collectDictionaries();
		$this->noDictsLine = 'You have no vocabs yet.';		
		return parent::dictionaryOutput();		
	}
}

class FilmOpenDictionariesList extends DictionariesList {
  public $dictionaries = array();
	private $movieNid;
	
	public function __construct($movieNid) {
		parent::__construct();
		$this->movieNid = $movieNid;
	}

	private function collectDictionaries() {
		$user_movie_dicts = db_query("SELECT fdm.entity_id AS dict_nid,
												 n.uid AS dict_owner_uid,
												 fdic.field_dictionary_import_count_value AS import_count
											 FROM field_data_field_dictionary_movie AS fdm
											 INNER JOIN node AS n
											 ON fdm.entity_id = n.nid
											 INNER JOIN flag_content AS fc
											 ON fdm.entity_id = fc.content_id
											 LEFT OUTER JOIN field_data_field_dictionary_import_count AS fdic
											 ON fdic.entity_id = fdm.entity_id
											 WHERE fdm.field_dictionary_movie_nid = :movie_nid
											 AND fc.fid = 3
											 ORDER BY fdic.field_dictionary_import_count_value DESC",
										array('movie_nid' => $this->movieNid));
		
		while($user_movie_dict = $user_movie_dicts->fetchAssoc()) {
			$this->addDictionary(new FilmOpenFilmDictionary('', $this->movieNid, $user_movie_dict['dict_nid'], $user_movie_dict['dict_owner_uid'], $user_movie_dict['import_count']));
		}
	}
	
	public function dictionaryOutput() {
		$this->collectDictionaries();
		$this->noDictsLine = 'This film has no open vocabs yet.';
		return parent::dictionaryOutput();
	}
}

class DictionaryLink {
	public static function dictUtilsLinksLeft(FilmDictionary $dictionary) {
		return
			l(
				'',
				'movie/' . $dictionary->movieNid . '/subtitles',
				array(
					'attributes' => array(
						'class' => array(
							'dict-left-icon',
							'icon-film'
						),
						'title' => 'Go to the film subtitles',
					)
				)
			)
		. l(
				'',
				'movie/' . $dictionary->movieNid . '/discuss',
				array(
					'attributes' => array(
						'class' => array(
							'dict-left-icon',
							'icon-comments'),
						'title' => 'Go to the film discussions',
						)
					)
				)
		. l('',
				'',
				array(
					'attributes' => array(
						'class' => array(
							'dict-left-icon',
							'print-button',
							'icon-printer'),
						'title' => 'Print the vocabulary',
						),
					'html' => TRUE)
			)
		. l(
				'',
				'',
				array(
					'attributes' => array(
						'class' => array(
							'dict-left-icon',
							'icon-dict-refresh',
							'dict-refresh-link',
							'accordion-line-load'
						),
						'title' => 'Refresh the vocabulary',
					)
				)
			)		
		. l(
				'',
				'dictionaries/dict/delete/' . $dictionary->movieNid,
				array(
					'attributes' => array(
						'class' => array(
							'dict-left-icon',
							'icon-dict-delete',
							'dict-delete-link'
						),
						'title' => 'Delete the vocabulary',
					)
				)
			);
	}			
		
	public static function dictUtilsLinksRight(FilmDictionary $dictionary) {
		$dict_is_imported = !$dictionary->isAvailableForOpening();
		return '<div class="dict-and-notes">'
		 . (!$dict_is_imported
					? flag_create_link('dict_open', $dictionary->dictNid)
					: '<span class="flag-dict-open">'
						. l(
								'',
								'',
								array(
									'attributes' => array(
										'title' => 'You can not open this vocab because more than 50% of words were imported',
										'class' => array(
											'flag',
											'flag-action',
											'icon-lock-locked',
											'link-disabled'
										)
									)
								)
							)
				)
			. '</span>'
		 . (!$dict_is_imported
				? flag_create_link('notes_open', $dictionary->dictNid)
				: '<span class="flag-notes-open">'
					. l(
							'',
							'',
							array('attributes' => array(
								'title' => 'You can not open notes for this vocab because more than 50% of words were imported',
								'class' => array(
									'flag',
									'flag-action',
									'icon-notes-locked',
									'link-disabled'
								)
							)
						)
					)
				)
			. '</span>'
		 . '</div>';
	}					

	public static function dictOwnerLinks($wordNid) {
		return '<div class="dict-owner-links">'
			. flag_create_link('working_dict', $wordNid)
			.	l(
					'',
					'dictionaries/word/delete/' . $wordNid,
					array(
						'attributes' => array(
							'class' => array(
								'delete-word'
							),
						'title' => 'Delete word from all vocabularies')
					)
				)
			.	l('',
					'',
					array(
						'attributes' => array(
							'id' => 'eye-line-' . $wordNid, 							
							'class' => array(
								'eye',
								'eye-line',
								'eye-see'
							)
						),
						'html' => TRUE
					)
				)
			. '</div>';
	}
	
	public static function dictImportLink(FilmDictionary $dictionary) {
		$is_already_imported = $dictionary->isDictImportedByUser();
		if($dictionary->dictOwnerUid != $dictionary->user->uid) {
			if($is_already_imported) {
				$dict_import_link_active = l('', '', array('attributes' => array('class' => array('icon25', 'dict-import-link', 'icon-import', 'link-disabled'), 'title' => 'You have already imported this vocabulary')));
			}
			else if(!_movie_user_allowed_to_add_words($dictionary->movieNid)) {
				$dict_import_link_active = l('', '', array('attributes' => array('class' => array('icon25', 'dict-import-link', 'icon-import', 'link-disabled', 'buy-premium'), 'title' => 'Buy Premium Account to import this vocabulary')));
			}
			else {
			$dict_import_link_active = l('', 'dictionaries/dict/import/' . $dictionary->movieNid . '/' . $dictionary->dictOwnerUid . '/' . $dictionary->dictNid, array('attributes' => array('class' => array('icon25', 'dict-import-link', 'icon-import', 'link-enabled'), 'title' => 'Import this vocabulary')));
			}
		}	
		else {
			$dict_import_link_active = l('', '', array('attributes' => array('class' => array('icon25', 'dict-import-link', 'icon-import', 'dict-owner', 'link-disabled'), 'title' => 'This is your vocabulary')));
		}
		
		$dict_import_link =
			user_is_logged_in()
				?	$dict_import_link_active
				: l('', 'user/login', array('attributes' => array('class' => array('icon25', 'dict-import-link', 'icon-import', 'not-logged-in'), 'title' => 'login or register to import this vocab')));

		return '<div class="dict-utils-links">' . $dict_import_link . '</div>';
	}
	
	public static function dictOwnerProfileLink(FilmDictionary $dictionary) {
		return '<span class="dict-owner-link">'
			. l(theme('username', array('account' => $dictionary->dictOwnerObj->name)), 'user/' . $dictionary->dictOwnerUid, array('html' => TRUE))
			. '\'s vocab</span>';	
	}	
}

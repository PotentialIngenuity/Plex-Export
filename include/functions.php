<?php 




/**
 * Parse a Movie
 **/
function load_data_for_movie($el) {

	global $options;

	$_el = $el->attributes();
	$key = intval($_el->ratingKey);
	if($key<=0) return false;
	$title = strval($_el->title);
	if (!$titleSort = strval($_el->titleSort)) {
	  $titleSort = $title;
	  plex_log('Scanning movie: '.$title);
	} else {
	  plex_log('Scanning movie: '.$title . ' ( sortTitle: '.$titleSort.' )');
	}

	$thumb = generate_item_thumbnail(strval($_el->thumb), $key, 'mov', $title);

	$item = array(
		'key' => $key,
		'type' => 'movie',
		'thumb' => $thumb,
		'title' => $title,
		'titleSort' => $titleSort,
		'duration' => floatval($_el->duration),
		'view_count' => intval($_el->viewCount),
		'tagline' => ($_el->tagline)?strval($_el->tagline):false,
		'rating' => ($_el->rating)?floatval($_el->rating):false,
		'user_rating' => ($_el->userRating)?floatval($_el->userRating):false,
		'release_year' => ($_el->year)?intval($_el->year):false,
		'release_date' => ($_el->originallyAvailableAt)?strval($_el->originallyAvailableAt):false,
		'addedAt' => false,
		'content_rating' => ($_el->contentRating)?strval($_el->contentRating):false,
		'summary' => ($_el->summary)?strval($_el->summary):false,
		'studio' => ($_el->studio)?strval($_el->studio):false,
		'genre' => false,
		'director' => false,
		'role' => false,
		'media' => false
	);

	$media_el = $el->Media->attributes();
	if(intval($media_el->duration)>0) {
		$item['media'] = array(
			'bitrate' => ($media_el->bitrate)?intval($media_el->bitrate):false,
			'aspect_ratio' => ($media_el->aspectRatio)?floatval($media_el->aspectRatio):false,
			'audio_channels' => ($media_el->audioChannels)?intval($media_el->audioChannels):false,
			'audio_codec' => ($media_el->audioCodec)?strval($media_el->audioCodec):false,
			'video_codec' => ($media_el->videoCodec)?strval($media_el->videoCodec):false,
			'video_resolution' => ($media_el->videoResolution)?intval($media_el->videoResolution):false,
			'video_framerate' => ($media_el->videoFrameRate)?strval($media_el->videoFrameRate):false,
			'total_size' => false
		);
		$total_size = 0;
		foreach($el->Media->Part as $part) {
			$total_size += floatval($part->attributes()->size);
		}
		if($total_size>0) {
			$item['media']['total_size'] = $total_size;
		}
	}

	$url = $options['plex-url'].'library/metadata/'.$key;
	$xml = load_xml_from_url($url);
	if(!$xml) {
		plex_error('Could not load additional metadata for '.$title);
		return $item;
	}

	$genres = array();
	foreach($xml->Video->Genre as $genre) $genres[] = strval($genre->attributes()->tag);
	if(count($genres)>0) $item['genre'] = $genres;

	$directors = array();
	foreach($xml->Video->Director as $director) $directors[] = strval($director->attributes()->tag);
	if(count($directors)>0) $item['director'] = $directors;

	$roles = array();
	foreach($xml->Video->Role as $role) $roles[] = strval($role->attributes()->tag);
	if(count($roles)>0) $item['role'] = $roles;

	$item['addedAt']=intval($xml->Video->attributes()->addedAt);

	return $item;

} // end func: load_data_for_movie





/**
 * Parse a TV Show
 **/
function load_data_for_show($el) {

	global $options;

	$_el = $el->attributes();
	$key = intval($_el->ratingKey);
	if($key<=0) return false;
	$title = strval($_el->title);
	if (!$titleSort = strval($_el->titleSort)) {
	  plex_log('Scanning show: '.$title);
	} else {
	  plex_log('Scanning show: '.$title . ' ( sortTitle: '.$titleSort.' )');
	}

	$thumb = generate_item_thumbnail(strval($_el->thumb), $key, 'show', $title);

	$item = array(
		'key' => $key,
		'type' => 'show',
		'thumb' => $thumb,
		'title' => $title,
		'titleSort' => $titleSort,
		'rating' => ($_el->rating)?floatval($_el->rating):false,
		'user_rating' => ($_el->userRating)?floatval($_el->userRating):false,
		'release_year' => ($_el->year)?intval($_el->year):false,
		'release_date' => ($_el->originallyAvailableAt)?strval($_el->originallyAvailableAt):false,
		'duration' => floatval($_el->duration),
		'content_rating' => ($_el->contentRating)?strval($_el->contentRating):false,
		'summary' => ($_el->summary)?strval($_el->summary):false,
		'studio' => ($_el->studio)?strval($_el->studio):false,
		'tagline' => false,
		'num_episodes' => intval($_el->leafCount),
		'num_seasons' => false,
		'seasons' => array()
	);

	$genres = array();
	foreach($el->Genre as $genre) $genres[] = strval($genre->attributes()->tag);
	if(count($genres)>0) $item['genre'] = $genres;
	
	$url = $options['plex-url'].'library/metadata/'.$key.'/children';
	$xml = load_xml_from_url($url);
	if(!$xml) {
		plex_error('Could not load additional metadata for '.$title);
		return $item;
	}
	
	$seasons = array();
	$season_sort_order = array();
	foreach($xml->Directory as $el2) {
		if($el2->attributes()->type!='season') continue;
		$season_key = intval($el2->attributes()->ratingKey);
		$season_sort_order[intval($el2->attributes()->index)] = $season_key;
		$season = array(
			'key' => $season_key,
			'title' => strval($el2->attributes()->title),
			'num_episodes' => intval($el2->attributes()->leafCount),
			'actual_episodes' => 0,
			'episodes' => array(),
			'index' => intval($el2->attributes()->index)
		);
		
		$url = $options['plex-url'].'library/metadata/'.$season_key.'/children';
		$xml2 = load_xml_from_url($url);
		if(!$xml2) {
			plex_error('Could not load season data for '.$item['title'].' : '.$season['title']);
		}
		
		$episode_sort_order = array();
		foreach($xml2->Video as $el3) {
			if($el3->attributes()->type!='episode') continue;
			$episode_key = intval($el3->attributes()->ratingKey);
			$episode_sort_order[intval($el3->attributes()->index)] = $episode_key;
			$episode = array(
				'key' => $episode_key,
				'title' => strval($el3->attributes()->title),
				'index' => intval($el3->attributes()->index),
				'summary' => strval($el3->attributes()->summary),
				'rating' => floatval($el3->attributes()->rating),
				'duration' => floatval($el3->attributes()->duration),
				'view_count' => intval($el3->attributes()->viewCount)
			);
			$season['episodes'][$episode_key] = $episode;
			$season['actual_episodes']++;
		}
		
		ksort($episode_sort_order);
		$season['episode_sort_order'] = array_values($episode_sort_order);
		
		$seasons[$season_key] = $season;
	}	
	ksort($season_sort_order);
	$item['season_sort_order'] = array_values($season_sort_order);
	$item['num_seasons'] = count($seasons);
	if($item['num_seasons']>0) $item['seasons'] = $seasons;

	return $item;

} // end func: load_data_for_show









/**
 * Load all supported sections from given Plex API endpoint
 **/
function load_all_sections() {

	global $options;
	$url = $options['plex-url'].'library/sections';
	plex_log('Searching for sections in the Plex library at '.$options['plex-url']);

	$xml = load_xml_from_url($url);
	if(!$xml) return false;

	$total_sections = intval($xml->attributes()->size);
	if($total_sections<=0) {
		plex_error('No sections were found in this Plex library');
		return false;
	}

	$sections = array();
	$num_sections = 0;

	foreach($xml->Directory as $el) {
		$_el = $el->attributes();
		$key = intval($_el->key);
		$type = strval($_el->type);
		$title = strval($_el->title);
		if($type=='movie' or $type=='show') {
			$sections[$key] = array('key'=>$key, 'type'=>$type, 'title'=>$title);
			$num_sections++;
		} else {
			plex_error('Skipping section of unknown type: '.$type);
		}
	}

	if($num_sections==0) {
		plex_error('No valid sections found, aborting');
		return false;
	}

	if($total_sections!=$num_sections) {
		plex_log('Found '.$num_sections.' valid '.hl_inflect($num_sections, 'section').' out of a possible '.$total_sections.' '.hl_inflect($total_sections, 'section').' in this Plex library');
	} else {
		plex_log('Found '.$num_sections.' '.hl_inflect($num_sections, 'section').' in this Plex library');
	}

	return $sections;

} // end func: load_all_sections



/**
 * Load all items present in a section
 **/
function load_items_for_section($section) {

	global $options;
	$url = $options['plex-url'].'library/sections/'.$section['key'].'/all';

	$xml = load_xml_from_url($url);
	if(!$xml) return false;

	$num_items = intval($xml->attributes()->size);
	if($num_items<=0) {
		plex_error('No items were found in this section, skipping');
		return false;
	}

	switch($section['type']) {
		case 'movie':
			$object_to_loop = $xml->Video;
			$object_parser = 'load_data_for_movie';
			break;
		case 'show':
			$object_to_loop = $xml->Directory;
			$object_parser = 'load_data_for_show';
			break;
		default:
			plex_error('Unknown section type provided to parse: '.$section['type']);
			return false;
	}

	plex_log('Found '.$num_items.' '.hl_inflect($num_items,$section['type']).' in '.$section['title']);

	$items = array();
	foreach($object_to_loop as $el) {
		$item = $object_parser($el);
		if($item) $items[$item['key']] = $item;

	}

	return $items;

} // end func: load_items_for_section



/**
 * Load URL and parse as XML
 **/
function load_xml_from_url($url) {

    global $options;
    global $context;

	if(!@fopen($url, 'r', false, $context)) {
		plex_error('The Plex library could not be found at '.$options['plex-url']);
		return false;
	}

	$xml = file_get_contents($url, false, $context);
	$xml = @simplexml_load_string($xml);
	if(!$xml) {
		plex_error('Data could not be read from the Plex server at '.$url);
		return false;
	}

	if(!$xml) {
		plex_error('Invalid XML returned by the Plex server, aborting');
		return false;
	}

	return $xml;

} // end func: load_xml_from_url



/**
 * Load a thumbnail via Plex API and save
 **/
function generate_item_thumbnail($thumb_url, $key, $type, $title) {

	global $options;

	$filename = '/'.$type.'_thumb_'.$key.'.jpeg';
	$save_filename = $options['absolute-data-dir'].$filename;
	$return_filename = $options['data-dir'].$filename;

	if(file_exists($save_filename))
		return $return_filename;

	if($thumb_url=='') {
		plex_error('No thumbnail URL was provided for '.$title, ', skipping');
		return false;
	}

    $source_url = '/'.substr($thumb_url,1); # e.g. http://local:32400/library/metadata/123/thumb?=date
	$source_url = $source_url . '&X-Plex-Token='.$options['token'];
	$transcode_url = $options['plex-url'].'photo/:/transcode?width='.$options['thumbnail-width'].'&height='.$options['thumbnail-height'].'&url='.urlencode($source_url).'&X-Plex-Token='.$options['token'];

	$img_data = @file_get_contents($transcode_url);
	if(!$img_data) {
		plex_error('Could not load thumbnail for '.$title,' skipping');
		return false;
	}

	$result = @file_put_contents($save_filename, $img_data);
	if(!$result) {
		plex_error('Could not save thumbnail for '.$title,' skipping');
		return false;
	}

	return $return_filename;

} // end func: generate_item_thumbnail



/**
 * Output a message to STDOUT
 **/
function plex_log($str) {
	$str = @date('H:i:s')." $str\n";
	fwrite(STDOUT, $str);
} // end func: plex_log



/**
 * Output an error to STDERR
 **/
function plex_error($str) {
	$str = @date('H:i:s')." Error: $str\n";
	fwrite(STDERR, $str);
} // end func: plex_error



/**
 * Capture PHP error events
 **/
function plex_error_handler($errno, $errstr, $errfile=null, $errline=null) {
	if(!(error_reporting() & $errno)) return;
	$str = @date('H:i:s')." Error: $errstr". ($errline?' on line '.$errline:'') ."\n";
	fwrite(STDERR, $str);
} // end func: plex_error_handler



/**
 * Check environment meets dependancies, exit() if not
 **/
function check_dependancies() {
	global $options;
	$errors = false;

	if(!extension_loaded('simplexml')) {
		plex_error('SimpleXML is not enabled');
		$errors = true;
	}

	if(!ini_get('allow_url_fopen')) {
		plex_error('Remote URL access is disabled (allow_url_fopen)');
		$errors = true;
	}

	if(!is_writable($options['absolute-data-dir'])) {
		plex_error('Data directory is not writeable at '.$options['absolute-data-dir']);
		$errors = true;
	}

	if($errors) {
		plex_error('Failed one or more dependancy checks; aborting');
		exit();
	}

} // end func: check_dependancies



/**
 * Produce output array from merger of inputs and defaults
 **/
function hl_parse_arguments($cli_args, $defaults) {
	$output = (array) $defaults;
	foreach($cli_args as $str) {
		if(substr($str,0,1)!='-') continue;
		$eq_pos = strpos($str, '=');
		$key = substr($str, 1, $eq_pos-1);
		if(!array_key_exists($key, $output)) continue;
		$output[$key] = substr($str, $eq_pos+1);
	}
	return $output;
} // end func: hl_parse_arguments



/**
 * Return plural form if !=1
 **/
function hl_inflect($num, $single, $plural=false) {
	if($num==1) return $single;
	if($plural) return $plural;
	return $single.'s';
} // end func: hl_inflect


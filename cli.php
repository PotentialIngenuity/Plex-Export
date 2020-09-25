<?php
/*
	Plex Export
	Luke Lanchester <luke@lukelanchester.com>
	Inclues the PHP JavascriptPacker at bottom, all credit to the original authors

	A CLI script to export information from your Plex library.
	Usage:
		php cli.php [-plex-url="http://your-plex-library:32400"] [-data-dir="plex-data"] [-sections=1,2,3 or "Movies,TV Shows"] [-token=TheTokenToUse]
		
	Dane22, a Plex Community member added support for Plex Tokens
	
	Token: To get a valid token, look here: https://support.plex.tv/hc/en-us/articles/204059436-Finding-your-account-token-X-Plex-Token

*/
require_once 'include/JavaScriptPacker.php';
require_once 'include/ParseMaster.php';
require_once 'include/functions.php';

$timer_start = microtime(true);
$plex_export_version = 1;
ini_set('memory_limit', '512M');
set_error_handler('plex_error_handler');
error_reporting(E_ALL ^ E_NOTICE | E_WARNING);


// Set-up
	plex_log('Welcome to the Plex Exporter v'.$plex_export_version);
	$defaults = array(
		'plex-url' => 'http://localhost:32400',
		'data-dir' => 'plex-data',
		'thumbnail-width' => 150,
		'thumbnail-height' => 250,
		'sections' => 'all',
		'sort-skip-words' => 'a,the,der,die,das',
		'token' => ''
	);
	$options = hl_parse_arguments($_SERVER['argv'], $defaults);
	if(substr($options['plex-url'],-1)!='/')
		$options['plex-url'] .= '/'; // Always have a trailing slash
	
	$options['absolute-data-dir'] = dirname(__FILE__).'/'.$options['data-dir']; // Run in current dir (PHP CLI defect)
	$options['sort-skip-words'] = (array) explode(',', $options['sort-skip-words']); // comma separated list of words to skip for sorting titles
	
	// Create the http header with a X-Plex-Token in it	if specified
	
	if (strlen($options['token']) == 0){
		$headers = array(
			'http'=>array(
    	'method'=>"GET"                 
			)
		);
	}
	else
	{
		$headers = array(
		'http'=>array(
		  'method'=>"GET",
		  'header'=>"X-Plex-Token: ".$options['token']              
			)
		);
	}

	$context = stream_context_create($headers);

	check_dependancies(); // Check everything is enabled as necessary


// Load details about all sections
	$all_sections = load_all_sections();
	if(!$all_sections) {
		plex_error('Could not load section data, aborting');
		exit();
	}


	// If user wants to show all (supported) sections...
	if($options['sections'] == 'all') {
		$sections = $all_sections;
	} else {
		// Otherwise, match sections by Title first, then ID
		$sections_to_show = array_filter(explode(',',$options['sections']));
		$section_titles = array();
		foreach($all_sections as $i=>$section)
			$section_titles[strtolower($section['title'])] = $i;
		
		foreach($sections_to_show as $section_key_or_title) {
			
			$section_title = strtolower(trim($section_key_or_title));
			if(array_key_exists($section_title, $section_titles)) {
				$section_id = $section_titles[$section_title];
				$sections[$section_id] = $all_sections[$section_id];
				continue;
			}
			
			$section_id = intval($section_key_or_title);
			if(array_key_exists($section_id, $all_sections)) {
				$sections[$section_id] = $all_sections[$section_id];
				continue;
			}
			
			plex_error('Could not find section: '.$section_key_or_title);
			
		} // end foreach: $sections_to_show
	} // end if: !all sections


// If no sections found (or matched)
	$num_sections = count($sections);
	if($num_sections==0) {
		plex_error('No sections were found to scan');
		exit();
	}

// Load details about each section
	$total_items = 0;
	$section_display_order = array();
	foreach($sections as $i=>$section) {
		plex_log('Scanning section: '.$section['title']);

		$items = load_items_for_section($section);

		if(!$items) {
			plex_error('No items were added for '.$section['title'].', skipping');
			$sections[$i]['num_items'] = 0;
			$sections[$i]['items'] = array();
			continue;
		}
		
		$num_items = count($items);
		if($section['type']=='show') {
			$num_items_episodes = 0;
			foreach($items as $item)
				$num_items_episodes += $item['num_episodes'];
			
			$total_items += $num_items_episodes;
		}
		 else {
			$total_items += $num_items;	
		}

		plex_log('Analysing media items in section...');

		$sorts_title = $sorts_release = $sorts_rating = $sorts_added_at = array();
		$raw_section_genres = array();

		foreach($items as $key=>$item) {
			
			$title_sort = strtolower($item['titleSort']);
			$title_first_space = strpos($title_sort, ' ');
			if($title_first_space>0) {
				$title_first_word = substr($title_sort, 0, $title_first_space);
				if(in_array($title_first_word, $options['sort-skip-words'])) {
					$title_sort = substr($title_sort, $title_first_space+1);
				}
			}
			$sorts_title[$key] = $title_sort;
			$sorts_release[$key] = @strtotime($item['release_date']);
			$sorts_rating[$key] = ($item['user_rating'])?$item['user_rating']:$item['rating'];
			if(is_array($item['genre']) and count($item['genre'])>0) {
				foreach($item['genre'] as $genre) {
					$raw_section_genres[$genre]++;
				}
			}
			$sorts_added_at[$key] = $item['addedAt'];
		} // end foreach: $items (for sorting)

		asort($sorts_title, SORT_STRING);
		asort($sorts_release, SORT_NUMERIC);
		asort($sorts_added_at, SORT_NUMERIC);
		asort($sorts_rating, SORT_NUMERIC);
		$sorts['title_asc'] = array_keys($sorts_title);
		$sorts['release_asc'] = array_keys($sorts_release);
		$sorts['addedAt_asc'] = array_keys($sorts_added_at);
		$sorts['rating_asc'] = array_keys($sorts_rating);
		$sorts['title_desc'] = array_reverse($sorts['title_asc']);
		$sorts['release_desc'] = array_reverse($sorts['release_asc']);
		$sorts['addedAt_desc'] = array_reverse($sorts['addedAt_asc']);
		$sorts['rating_desc'] = array_reverse($sorts['rating_asc']);

		$section_genres = array();
		if(count($raw_section_genres)>0) {
			arsort($raw_section_genres);
			foreach($raw_section_genres as $genre=>$genre_count) {
				$section_genres[] = array(
					'genre' => $genre,
					'count' => $genre_count,
				);
			}
		}
		
		$section_display_order[] = $i;
		$sections[$i]['num_items'] = $num_items;
		$sections[$i]['items'] = $items;
		$sections[$i]['sorts'] = $sorts;
		$sections[$i]['genres'] = $section_genres;

		plex_log('Added '.$num_items.' '.hl_inflect($num_items,'item').' from the '.$section['title'].' section');

	} // end foreach: $sections_to_export


// Output all data

	plex_log('Exporting data for '.$num_sections.' '.hl_inflect($num_sections,'section').' containing '.$total_items.' '.hl_inflect($total_items,'item'));

	$output = array(
		'status' => 'success',
		'version' => $plex_export_version,
		'last_generated' => time()*1000,
		'last_updated' => 'last updated : '.date('Y-m-d - H:i',time()),
		'total_items' => $total_items,
		'num_sections' => $num_sections,
		'section_display_order' => $section_display_order,
		'sections' => $sections
	);

	plex_log('Generating and minifying JSON output, this may take some time...');
	$raw_json = json_encode($output);
	$raw_js = 'var raw_plex_data = '.$raw_json.';';
	//$myPacker = new JavaScriptPacker($raw_js); # See bottom of file for relevant Class
	//$packed_js = $myPacker->pack();
	$packed_js = $raw_js;
	if(!$packed_js) {
		plex_error('Could not minify JSON output, aborting.');
		exit();
	}

	$filename = $options['absolute-data-dir'].'/data.js';
	$bytes_written = file_put_contents($filename, $packed_js);
	if(!$bytes_written) {
		plex_error('Could not save JSON data to '.$filename.', please make sure directory is writeable');
		exit();
	}

	plex_log('Wrote '.$bytes_written.' bytes to '.$filename);

	$timer_end = microtime(true);
	$time_taken = $timer_end - $timer_start;
	plex_log('Plex Export completed in '.round($time_taken,2).' seconds');

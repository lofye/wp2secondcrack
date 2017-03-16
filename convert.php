<?php

$your_domain = 'http://www.derekmartin.ca/';//make sure you leave the trailing / at the end

ini_set('max_execution_time', '1');
ini_set('display_errors', '1');
ini_set('memory_limit','500000000');

require 'vendor/autoload.php';

use League\HTMLToMarkdown\HtmlConverter;

//original Title Case script © John Gruber <daringfireball.net>
//javascript port © David Gouch <individed.com>
//PHP port of the above by Kroc Camen <camendesign.com>
function title_case ($title) {
	//remove HTML, storing it for later
	//       HTML elements to ignore    | tags  | entities
	$regx = '/<(code|var)[^>]*>.*?<\/\1>|<[^>]+>|&\S+;/';
	preg_match_all ($regx, $title, $html, PREG_OFFSET_CAPTURE);
	$title = preg_replace ($regx, '', $title);
	
	//find each word (including punctuation attached)
	preg_match_all ('/[\w\p{L}&`\'‘’"“\.@:\/\{\(\[<>_]+-? */u', $title, $m1, PREG_OFFSET_CAPTURE);
	foreach ($m1[0] as &$m2) {
		//shorthand these- "match" and "index"
		list ($m, $i) = $m2;
		
		//correct offsets for multi-byte characters (`PREG_OFFSET_CAPTURE` returns *byte*-offset)
		//we fix this by recounting the text before the offset using multi-byte aware `strlen`
		$i = mb_strlen (substr ($title, 0, $i), 'UTF-8');
		
		//find words that should always be lowercase…
		//(never on the first word, and never if preceded by a colon)
		$m = $i>0 && mb_substr ($title, max (0, $i-2), 1, 'UTF-8') !== ':' && 
			!preg_match ('/[\x{2014}\x{2013}] ?/u', mb_substr ($title, max (0, $i-2), 2, 'UTF-8')) && 
			 preg_match ('/^(a(nd?|s|t)?|b(ut|y)|en|for|i[fn]|o[fnr]|t(he|o)|vs?\.?|via)[ \-]/i', $m)
		?	//…and convert them to lowercase
			mb_strtolower ($m, 'UTF-8')
			
		//else:	brackets and other wrappers
		: (	preg_match ('/[\'"_{(\[‘“]/u', mb_substr ($title, max (0, $i-1), 3, 'UTF-8'))
		?	//convert first letter within wrapper to uppercase
			mb_substr ($m, 0, 1, 'UTF-8').
			mb_strtoupper (mb_substr ($m, 1, 1, 'UTF-8'), 'UTF-8').
			mb_substr ($m, 2, mb_strlen ($m, 'UTF-8')-2, 'UTF-8')
			
		//else:	do not uppercase these cases
		: (	preg_match ('/[\])}]/', mb_substr ($title, max (0, $i-1), 3, 'UTF-8')) ||
			preg_match ('/[A-Z]+|&|\w+[._]\w+/u', mb_substr ($m, 1, mb_strlen ($m, 'UTF-8')-1, 'UTF-8'))
		?	$m
			//if all else fails, then no more fringe-cases; uppercase the word
		:	mb_strtoupper (mb_substr ($m, 0, 1, 'UTF-8'), 'UTF-8').
			mb_substr ($m, 1, mb_strlen ($m, 'UTF-8'), 'UTF-8')
		));
		
		//resplice the title with the change (`substr_replace` is not multi-byte aware)
		$title = mb_substr ($title, 0, $i, 'UTF-8').$m.
			 mb_substr ($title, $i+mb_strlen ($m, 'UTF-8'), mb_strlen ($title, 'UTF-8'), 'UTF-8')
		;
	}
	
	//restore the HTML
	foreach ($html[0] as &$tag) $title = substr_replace ($title, $tag[0], $tag[1], 0);
	return $title;
}

$export_count = array();
$export_count['pages'] = 0;
$export_count['posts'] = 0;
$files = array();
foreach (glob(__DIR__."/*.xml") as $file) {
  $files[] = $file;
}
$file = $files[0];

if (file_exists($file)) {
    $xml = simplexml_load_file($file);

    foreach($xml->channel->item as $item)
    {
    	ob_start();

		echo title_case($item->title)."\r\n";
		$title_length = strlen($item->title);
		for($i=0; $i<$title_length; $i++)
		{
			echo "=";
		}
		echo "\r\n";	 
	    $tags = array();
	    $raw_tags = $item->category;
	    foreach($raw_tags as $tag)
	    {
	    	$tags[] = $tag->attributes()['nicename'];	    
	    }		
		echo "Tags: ".implode($tags,', ')."\r\n";
		echo "Published: ".$item->pubDate."\r\n";//format this:  Tue, 14 Mar 2017 15:28:52 +0000   as: 2017-03-14 03:28:52pm
		echo "Type: post"."\r\n";

		$md_filename = str_replace($your_domain,'',$item->link);
		$md_filename = explode('/', $md_filename);

		if(!is_numeric($md_filename[0]))
		{
			//e.g. f-a-q, or ?p=872
			$dir = 'pages';
			if(!is_dir($dir))
			{
				mkdir($dir);
			}						

			$filename = $md_filename[0].'.md';
			$filename = str_replace('?p=','',$filename);		

			$export_count['pages']++;	
		}
		else
		{
			//e.g. 1998, 2007

			$pieces = count($md_filename);
			$last_piece = $pieces - 1;
			if(empty($md_filename[$last_piece])){
				unset($md_filename[$last_piece]);
				$last_piece = count($md_filename) - 1;
			}

			$year = $md_filename[0];
			$month = $md_filename[1];
			$day = $md_filename[2];		
			$md_filename = $md_filename[$last_piece].'.md';//could use [3] instead of $last_piece
			if(!is_dir($year))
			{
				mkdir($year);
			}
			if(!is_dir($year.'/'.$month))
			{
				mkdir($year.'/'.$month);	
			}
			if(!is_dir($year.'/'.$month.'/'.$day))
			{
				mkdir($year.'/'.$month.'/'.$day);	
			}
			$dir = $year.'/'.$month.'/'.$day;

			$fi = new FilesystemIterator($dir, FilesystemIterator::SKIP_DOTS);
			$file_count = iterator_count($fi) + 1;	
			if(strlen($file_count)<2){ $file_count = '0'.$file_count; }
			$filename = $year.$month.$day.'-'.$file_count.'-'.$md_filename;			

			$export_count['posts']++;
		}

		$e_content = $item->children("content", true);
	    $html = (string)$e_content->encoded;
	    $converter = new HtmlConverter(array('strip_tags' => true));
	    $converter->getConfig()->setOption('italic_style', '_');
		//$converter->getConfig()->setOption('bold_style', '*');
		$converter->getConfig()->setOption('hard_break', true);
	    $markdown = $converter->convert($html);

		echo "\r\n".$markdown;

	    $content = ob_get_contents();
	    ob_end_clean();

	    file_put_contents($dir.'/'.$filename, $content);
    }
    
} else {
    exit('Failed to open '.$file);
}

echo "Exported ".$export_count['pages']." pages and ".$export_count['posts']." posts from ".$file."\r\n";

?>
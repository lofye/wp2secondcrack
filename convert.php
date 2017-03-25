<?php

//todo - output to proper directories wordpress-content/pages/ and /posts/

if (!isset($argv[1]) || !isset($argv[2])) {
    exit("You must pass in your domain as the first parameter and your xml filename as the second parameter, like:\r\nphp convert.php http://www.derekmartin.ca derekmartinca.wordpress.2017-03-16.xml noComments\r\n\r\n");
}
$your_domain = $argv[1] . '/';
$your_xml = $argv[2];
$with_comments = 'withComments';
if(isset($argv[3])){ 
    $with_comments = $argv[3];
}
if(!in_array($with_comments, array('withComments','noComments')))
{
    exit("The only valid values for the 3rd parameter are: withComments and noComments. If you fail to provide a 3rd paramter, it will default to withComments");
}

ini_set('max_execution_time', '1');
ini_set('display_errors', '1');
ini_set('memory_limit', '50M');

require 'vendor/autoload.php';

use League\HTMLToMarkdown\HtmlConverter;

//original Title Case script © John Gruber <daringfireball.net>
//javascript port © David Gouch <individed.com>
//PHP port of the above by Kroc Camen <camendesign.com>
function title_case($title)
{
    //remove HTML, storing it for later
    //HTML elements to ignore    | tags  | entities
    $regx = '/<(code|var)[^>]*>.*?<\/\1>|<[^>]+>|&\S+;/';
    preg_match_all($regx, $title, $html, PREG_OFFSET_CAPTURE);
    $title = preg_replace($regx, '', $title);

    //find each word (including punctuation attached)
    preg_match_all('/[\w\p{L}&`\'‘’"“\.@:\/\{\(\[<>_]+-? */u', $title, $m1, PREG_OFFSET_CAPTURE);
    foreach ($m1[0] as &$m2) {
        //shorthand these- "match" and "index"
        list ($m, $i) = $m2;

        //correct offsets for multi-byte characters (`PREG_OFFSET_CAPTURE` returns *byte*-offset)
        //we fix this by recounting the text before the offset using multi-byte aware `strlen`
        $i = mb_strlen(substr($title, 0, $i), 'UTF-8');

        //find words that should always be lowercase…
        //(never on the first word, and never if preceded by a colon)
        $m = $i > 0 && mb_substr($title, max(0, $i - 2), 1, 'UTF-8') !== ':' &&
        !preg_match('/[\x{2014}\x{2013}] ?/u', mb_substr($title, max(0, $i - 2), 2, 'UTF-8')) &&
        preg_match('/^(a(nd?|s|t)?|b(ut|y)|en|for|i[fn]|o[fnr]|t(he|o)|vs?\.?|via)[ \-]/i', $m)
            ?    //…and convert them to lowercase
            mb_strtolower($m, 'UTF-8')

            //else:	brackets and other wrappers
            : (preg_match('/[\'"_{(\[‘“]/u', mb_substr($title, max(0, $i - 1), 3, 'UTF-8'))
                ?    //convert first letter within wrapper to uppercase
                mb_substr($m, 0, 1, 'UTF-8') .
                mb_strtoupper(mb_substr($m, 1, 1, 'UTF-8'), 'UTF-8') .
                mb_substr($m, 2, mb_strlen($m, 'UTF-8') - 2, 'UTF-8')

                //else:	do not uppercase these cases
                : (preg_match('/[\])}]/', mb_substr($title, max(0, $i - 1), 3, 'UTF-8')) ||
                preg_match('/[A-Z]+|&|\w+[._]\w+/u', mb_substr($m, 1, mb_strlen($m, 'UTF-8') - 1, 'UTF-8'))
                    ? $m
                    //if all else fails, then no more fringe-cases; uppercase the word
                    : mb_strtoupper(mb_substr($m, 0, 1, 'UTF-8'), 'UTF-8') .
                    mb_substr($m, 1, mb_strlen($m, 'UTF-8'), 'UTF-8')
                ));

        //resplice the title with the change (`substr_replace` is not multi-byte aware)
        $title = mb_substr($title, 0, $i, 'UTF-8') . $m .
            mb_substr($title, $i + mb_strlen($m, 'UTF-8'), mb_strlen($title, 'UTF-8'), 'UTF-8');
    }

    //restore the HTML
    foreach ($html[0] as &$tag) $title = substr_replace($title, $tag[0], $tag[1], 0);
    return $title;
}

function add_attachment($item, $attachments)
{
    $file = array();

    $wp = $item->children("wp", true);

    $file_path = false;
    foreach($wp->postmeta as $ameta)
    {
        if(isset($ameta->meta_key) && ($ameta->meta_key == '_wp_attached_file'))
        {
            $path_parts = explode('/', $ameta->meta_value);
            $path_parts = array_slice($path_parts, -3, 3);
            $file_path = '/media/'.implode('/', $path_parts);
        }
    }

    $file['path'] = $file_path;

    if(!$file['path'])
    {
        echo "Skipping due to missing file path: ".print_r($item);
        return $attachments;
    }

    $link_parts = explode('/',$wp->attachment_url);
    array_reverse($link_parts);
    $last_segment = $link_parts[0];

    $file['title'] = (string)$item->title;

    $caption = '';

    $excerpt = $item->children("excerpt", true);
    $excerpt = (string)$excerpt->encoded;    
    if(!empty($excerpt)){
        $caption = $excerpt;
    }

    $content = $item->children("content", true);
    $content = (string)$content->encoded;
    if(!empty($content)){
        if(!empty($caption)){
            $caption .= ' - '.$content;
        }
        else
        {
            $caption = $content;
        }
    }

    $file['caption'] = $caption;

    $file['image_markdown'] = '!['.$file["caption"].']('.$file['path'].') "'.$file['title'].'"';

    //echo 'OFFSET: '.$wp->post_parent.'***'.print_r($attachments);
    $index = (int)$wp->post_parent;
    $attachments[$index] = $file;

    return $attachments;
}

function has_attachment($item, $attachments)
{
    $wp = $item->children("wp", true);
    $index = (int)$wp->post_id;
    if(isset($attachments[$index]))
    {
        return true;
    }
    return false;
}

function get_attachment($item, $attachments)
{
    $wp = $item->children("wp", true);
    $index = (int)$wp->post_id;    
    return $attachments[$index];
}

$export_count = array();
$export_count['pages'] = 0;
$export_count['posts'] = 0;
$export_count['attachments_found'] = 0;
$export_count['attachments_used'] = 0;

$container_dir = 'wordpress-content';
if (!is_dir($container_dir)) {
    mkdir($container_dir);
}

if (file_exists($your_xml)) {



    $xml = simplexml_load_file($your_xml);

    $attachments = array();
    foreach ($xml->channel->item as $item) {

        $wp = $item->children("wp", true);
        if($wp->post_type=='attachment'){
            $attachments = add_attachment($item, $attachments);
        }        
    }
    $export_count['attachments_found'] = count($attachments);
    echo "\r\n".$export_count['attachments_found']." Attachments Found.\r\n";




    $xml = simplexml_load_file($your_xml);

    foreach($xml->channel->item as $item) {        

        $wp = $item->children("wp", true);

        if($wp->post_type=='attachment'){
            continue;//skipping attachments
        }

        //print_r($item);
        // if($item->status!='publish'){
        //     echo "Skipping Unpublished: ".$wp->post_type.' - '.$item->title."\r\n";
        //     continue;
        // }

        $post_types = array('page','post');
        if(!in_array($wp->post_type, $post_types)){
            echo "UNKNOWN POST: ".$wp->post_type."\r\n";
            continue;
        }

        $e_content = $item->children("content", true);
        $html = (string)$e_content->encoded;
        $converter = new HtmlConverter(array('strip_tags' => true));
        $converter->getConfig()->setOption('italic_style', '_');
        $converter->getConfig()->setOption('hard_break', true);
        $markdown = $converter->convert($html);

        if(substr($item->link, -1, 1) == '/')
        {
            $item->link = substr($item->link, 0, -1);//remove the trailing slash
            //this is used to create the $md_filename
        }

        if(has_attachment($item, $attachments)){
            $file = get_attachment($item, $attachments);
            $markdown = $file["image_markdown"].' '.$markdown;
            $export_count['attachments_used']++;    
            //echo "Attached: ".print_r($file)." to ".$item->link."\r\n";        
        }
        else
        {
            //echo "Unattached Link: ".$item->link."\r\n";    
        }



        ob_start();

        echo title_case($item->title) . "\r\n";
        $title_length = strlen($item->title);
        for ($i = 0; $i < $title_length; $i++) {
            echo "=";
        }
        echo "\r\n";

        $pub_date = DateTime::createFromFormat('D, d M Y H:i:s O', $item->pubDate);//format this:  Tue, 14 Mar 2017 15:28:52 +0000   as: 2017-03-14 03:28:52pm
        echo "Published: " ;
        if(is_bool($pub_date)){
            $time = date('H:i:s');
            $pub_date = DateTime::createFromFormat('Y-m-d H:i:s', '1900-01-01 '.$time);
        }
        echo $pub_date->format('Y-m-d h:i:s a'). "\r\n";

        $tags = array();
        $raw_tags = $item->category;
        foreach ($raw_tags as $tag) {
            $tags[] = $tag->attributes()['nicename'];
        }

        if(count($tags) > 0)
        {
            echo "Tags: " . implode($tags, ', ') . "\r\n";    
        }        

        $md_filename = str_replace($your_domain, '', $item->link);
        $md_filename = explode('/', $md_filename);

        if (!is_numeric($md_filename[0])) {
            //e.g. f-a-q, or ?p=872
            $dir = $container_dir . '/pages';
            if (!is_dir($dir)) {
                mkdir($dir);
            }

            $filename = $md_filename[0] . '.md';
            $filename = str_replace('?p=', '', $filename);

            $type = "page";
            $export_count['pages']++;
        } else {
            //e.g. 1998, 2007

            $pieces = count($md_filename);
            $last_piece = $pieces - 1;
            if (empty($md_filename[$last_piece])) {
                unset($md_filename[$last_piece]);
                $last_piece = count($md_filename) - 1;
            }

            $year = $md_filename[0];
            $month = $md_filename[1];
            $day = $md_filename[2];
            $md_filename = $md_filename[$last_piece] . '.md';//could use [3] instead of $last_piece
            if (!is_dir($container_dir . '/' . $year)) {
                mkdir($container_dir . '/' . $year);
            }
            if (!is_dir($container_dir . '/' . $year . '/' . $month)) {
                mkdir($container_dir . '/' . $year . '/' . $month);
            }
//            if (!is_dir($container_dir . '/' . $year . '/' . $month . '/' . $day)) {
//                mkdir($container_dir . '/' . $year . '/' . $month . '/' . $day);
//            }
            $dir = $container_dir . '/' . $year . '/' . $month; // . '/' . $day;

            $fi = new FilesystemIterator($dir, FilesystemIterator::SKIP_DOTS);
            $file_count = iterator_count($fi) + 1;
            if (strlen($file_count) < 2) {
                $file_count = '0' . $file_count;
            }
            $filename = $year . $month . $day . '-' . $file_count . '-' . $md_filename;

            $type = "post";
            $export_count['posts']++;
        }

        //echo "Type: " .$type. "\r\n";

        echo "\r\n" . $markdown;

        $e_content = $item->children("wp", true);
        // if($e_content->attachment_url)
        // {
        //     //image is at <wp:postmeta><wp:meta_value> for wp:meta_key = _wp_attached_file  e.g. 2008/08/img_1952.jpg
        //     //metadata is json_encoded in <wp:postmeta><wp:meta_value> for wp:meta_key = _wp_attachment_metadata  i.e. caption etc.
        // }

        if($with_comments!='noComments' && $e_content->comment)
        {
            $approved_comments = array();
            foreach($e_content->comment as $key => $comment)
            {
                if(1 == (int)$comment->comment_approved)
                {
                    $approved_comments[] = $comment;
                }
            }
            if(count($approved_comments) > 0)
            {
                echo "\r\n\r\nComments from my old blog: \r\n\r\n";
                foreach($approved_comments as $comment)
                {
                    if(isset($comment->comment_author_url) && !empty(trim($comment->comment_author_url)))
                    {
                        $comment->comment_author = "(".$comment->comment_author.")[".$comment->comment_author_url."]";
                    }
                    echo "*".$comment->comment_author." said:*\r\n> ".(string)$comment->comment_content."\r\n".$comment->comment_date_gmt."\r\n\r\n";
                }
            }
        }

        $content = ob_get_contents();

        ob_end_clean();

        file_put_contents($dir . '/' . $filename, $content);
    }

} else {
    exit('Failed to open ' . $your_xml);
}

echo "\r\nFound ".$export_count['attachments_found']." and used ".$export_count['attachments_used']." attachments.\r\n";
echo "Exported " . $export_count['pages'] . " pages and " . $export_count['posts'] . " posts from " . $your_xml . "\r\n";

?>
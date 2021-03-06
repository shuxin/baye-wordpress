<?php
    $pre_HTML ="<?xml version='1.0' encoding='utf-8' ?>";
    $post_HTML ="";
    $LIMIT = 50;
    global $wpdb;
    
    $last_update_id = get_option('bm_last_update_id', 0);
    $max_comment_id = 0;
    
    $sql = "SELECT * FROM $wpdb->posts WHERE post_status = \"publish\" ";
    $posts = $wpdb->get_results($sql);
    $output = $pre_HTML;
    $output .= "<data><posts>\n";
    foreach ($posts as $post){
        $output .= "<post>\n";
        $output .= "<id>" . $post->ID . "</id>\n";
        $output .= "<title>" . $post->post_title . "</title>\n";
        $output .= "<url>" . get_permalink( $post->ID ) . "</url>\n";
        $output .= "</post>\n";
    }
    $output .= "</posts>";
    $pre_HTML = $output;


    $sql = "SELECT * FROM $wpdb->comments WHERE comment_approved = '1' AND comment_type = '' AND comment_ID > $last_update_id LIMIT $LIMIT";
    
    $comments = $wpdb->get_results($sql);
    $output = $pre_HTML;
    $output .= "<comments>\n";
    $comments_count = 0;
    foreach ($comments as $comment) {
        $output .= '<comment>\n';
        $output .= "<post_id>". $comment->comment_post_ID . "</post_id>\n";
        $output .= "<id>" . $comment->comment_ID . "</id>\n";
        $output .= "<parent>" . $comment->comment_parent . "</parent>\n";
        $output .= "<author>".strip_tags($comment->comment_author) . "</author>\n";
        $output .= "<author_email>" . $comment->comment_author_email . "</author_email>\n";
        $output .= "<author_url>" . $comment->comment_author_url . "</author_url>\n";
        $output .= "<author_ip>" . $comment->comment_author_IP . "</author_ip>\n";
        $output .= "<content><![CDATA[". $comment->comment_content . "]]></content>\n";
        $output .= "<date>". $comment->comment_date . "</date>\n";
        $output .= "<date_gmt>". $comment->comment_date_gmt . "</date_gmt>\n";
        $output .= '</comment>\n';
        $comments_count += 1;

        if( $comment->comment_ID > $max_comment_id ){
            $max_comment_id = $comment->comment_ID ;
        }
    }
    $output .= "\n</comments></data>";
    if($comments_count == 0){
        echo "{
            \"success\": false,
            \"msg\" : \"所有评论都已同步.\"
        }";
        die();
    }
    $fp = fsockopen("baye.me", 80, $errno, $errstr, 10) or exit($errstr."--->".$errno);         
    
    $params = "";
    $params .= "version=".BM_VERSION."&apikey=".get_option("bm_api_key")."&token=".md5(get_option("bm_api_key") . get_option("bm_api_secret"));
    $params .= "&data=".urlencode($output);
    $length = strlen($params);
    //构造post请求的头         
    $header = "POST /wordpress/comments/import HTTP/1.1\r\n";         
    $header .= "Host: baye.me\r\n";
    $header .= "Content-Type: application/x-www-form-urlencoded\r\n";         
    $header .= "Content-Length: ".$length."\r\n";         
    $header .= "Connection: Close\r\n\r\n";        
    //添加post的字符串         
    $header .= $params."";         
    //发送post的数据         
    fputs($fp, $header);         
    while( !feof($fp) ){
        $ret .= fgets($fp);
    }
    fclose($fp);
    if( preg_match('/success=true/', $ret) ){
        $success = True;
        $msg =  "已同步评论 $last_update_id 到 $max_comment_id ...";
        update_option("bm_last_update_id", $max_comment_id);

    }else{
        $success = False;
        if(preg_match('/msg="(\w+)";/', $matches)){
            $msg =  $matches[1];
        }else{
            $msg = "导出失败，服务器发生了一个错误.";
        }
    }

    preg_match('/\r\n\r\n(.+)/', $ret, $matches);

    /// echo $matches[1];

    $sql = "SELECT MAX(comment_ID) FROM $wpdb->comments;";
    $global_max_comment_id = $wpdb->get_var($sql);

    if( $max_comment_id <  $global_max_comment_id){
        $goon = True; 
    }else{
        $goon = False; 
    }
    
    echo "{
        \"success\": \"$success\",
        \"msg\": \"$msg\",
        \"goon\" : \"$goon\"
    }";
    die();

?>  

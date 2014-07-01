<?php 
function my_curl_request($url, $post_data)
{
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, FALSE);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
    curl_setopt($ch, CURLOPT_HEADER, 'content-type: text/plain;');
    curl_setopt($ch, CURLOPT_TRANSFERTEXT, 0);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $post_data);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_PROXY, false);
    curl_setopt($ch, CURLOPT_SSLVERSION, 1);
   
    // added for curl slow connection time 
    // refrence by http://stackoverflow.com/questions/11143180/curl-slow-connect-time
    curl_setopt($ch, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4 );

    $result = @curl_exec($ch);
    if($result === false){
        echo 'Curl error: ' . curl_error($ch);
        exit;
    }
    curl_close($ch);

    return $result;
}


 $result= $DB->get_field('config_plugins', 'value', array ('plugin' => 'local_getkey', 'name' => 'keyvalue'), $strictness=IGNORE_MISSING);
 
 $licen = $result;

//send auth detail to python script 
 $authusername = substr(str_shuffle(MD5(microtime())), 0, 12);
 $authpassword = substr(str_shuffle(MD5(microtime())), 0, 12);
 
 $post_data = array('authuser'=> $authusername,'authpass' => $authpassword, 'licensekey' => $licen);
 $post_data = json_encode($post_data);
 $rid = my_curl_request("https://c.vidya.io", $post_data); // REMOVE HTTP
 
    
 if(empty($rid) or strlen($rid) > 32){
  	echo "Chat server is unavailable!";
  	exit;
 }
 

 if($rid =='Rejected - Key Not Active'){
    echo "<script>alert('license key is not valid for onetoone'); </script>";
    exit;
 }
?>

<script type="text/javascript">
<?php echo "var wbUser = {};";?>
<?php echo "wbUser.auth_user='".$authusername."';"; ?>
<?php echo "wbUser.auth_pass='".$authpassword."';"; ?>
<?php echo "wbUser.path='".$rid."';";?>
</script>
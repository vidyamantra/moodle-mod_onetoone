<?php
// This file is part of vidyamantra - http://www.vidyamantra.com
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 *  along with this program.  If not, see <http://www.gnu.org/licenses/>.
 * @author   Pinky Sharma <http://www.vidyamantra.com>
 * @author   Suman Bogati <http://www.vidyamantra.com>
 * @package mod
 * @subpackage onetoone
 */


function my_curl_request($url, $postdata) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_HEADER, 'content-type: text/plain;');
    curl_setopt($ch, CURLOPT_TRANSFERTEXT, 0);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $postdata);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_PROXY, false);
    curl_setopt($ch, CURLOPT_SSLVERSION, 1);

    // Added for curl slow connection time.
    // Refrence by http://stackoverflow.com/questions/11143180/curl-slow-connect-time.
    curl_setopt($ch, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4 );

    $result = @curl_exec($ch);
    if ($result === false) {
        echo 'Curl error: ' . curl_error($ch);
        exit;
    }
    curl_close($ch);

    return $result;
}


 $result = $DB->get_field('config_plugins', 'value', array ('plugin' => 'local_getkey',
     'name' => 'keyvalue'), $strictness = IGNORE_MISSING);

 $licen = $result;

// Send auth detail to python script.
 $authusername = substr(str_shuffle(md5(microtime())), 0, 12);
 $authpassword = substr(str_shuffle(md5(microtime())), 0, 12);

 $postdata = array('authuser' => $authusername, 'authpass' => $authpassword, 'licensekey' => $licen);
 $postdata = json_encode($postdata);
 $rid = my_curl_request("https://c.vidya.io", $postdata); // REMOVE HTTP.
if (empty($rid) or strlen($rid) > 32) {
    echo "Chat server is unavailable!";
    exit;
}

if ($rid == 'Rejected - Key Not Active') {
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
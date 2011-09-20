<?php

require_once("lib/config.php");
require_once("lib/dropbox.php");

// oAuth dance
$dropbox = new Dropbox(APPLICATION_KEY, APPLICATION_SECRET);
$response = $dropbox->oAuthRequestToken();
if(!isset($_GET['authorize'])) {
  $dropbox->oAuthAuthorize($response['oauth_token'],
      'http://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'] .'?authorize=true');
} else {
  $response = $dropbox->oAuthAccessToken($_GET['oauth_token']);
}

// DEPRECATED: Alternative authorization method.
// Do not use this if you can authorize with OAuth.
// $response = $dropbox->token(EMAIL, PASSWORD);

?>

<html><head><title>Dropix</title></head><body>
Please add the following information to your <code>config.php</code>:
<p>
  <code>define("TOKEN", "<?php echo $response["oauth_token"] ?>");</code><br />
  <code>define("TOKEN_SECRET", "<?php echo $response["oauth_token_secret"] ?>");</code>
</p>
Afterwards delete the file <code>auth.php</code>.
</body></html>

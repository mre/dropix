<?php

require_once("lib/dropix.php");

?>

<html>
  <head>
  <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
</head>
<body>

<?php

// Load gallery plugin
$dropix = new Dropix();

if (empty($_GET)) {
  // Show gallery index
  $albums = $dropix->gallery_index();
  foreach ($albums as $album) {
    echo '<a href="' . $_SERVER["PHP_SELF"] . "?album=" . $album["album_name"] . '" >';
    echo '<img src="data:image/jpeg;base64,' . $album["thumb"] . '" alt="' . $album["album_name"] . '" />';
    echo '</a>';
  }
} else {
  if(isset($_GET["album"])) {
    if (isset($_GET["image"])) {
      // Show image
      $image = $dropix->get_image($_GET["album"], $_GET["image"]);
      echo '<img src="data:image/jpeg;base64,' . $image . '" alt="' . $_GET["image"] . '" />';
    } else {
      // Show album index
      $album_name = $_GET["album"];
      $images = $dropix->album_index($album_name);
      foreach ($images as $image) {
        echo '<a href="' . $_SERVER["PHP_SELF"] . "?album=" . $album_name . "&image=" . $image["name"] . '" >';
        echo '<img src="data:image/jpeg;base64,' . $image["thumb"] . '" alt="' . $image["name"] . '" />';
        echo '</a>';
      }
    }
  }
}
?>


</body>
</html>

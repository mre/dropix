<?php

require_once("config.php");   // Authentication data
require_once("dropbox.php");  // Dropbox API library

class Dropix 
{
  protected $dropbox;
  private $thumb_size;

  public function __construct()
  {
    $this->dropbox = new Dropbox(APPLICATION_KEY, APPLICATION_SECRET);
    if (defined("TOKEN") && defined("TOKEN_SECRET")) {
      // Connect with Dropbox account.
      $this->dropbox->setOAuthToken(TOKEN);
      $this->dropbox->setOAuthTokenSecret(TOKEN_SECRET);
    }
    $this->thumb_size = THUMB_SIZE;
  }

  /**
   * The authorization is necessary at first start,
   * when there is no valid configuration data.
   */
  public function authorize()
  {
    // oAuth dance
    $this->dropbox = new Dropbox(APPLICATION_KEY, APPLICATION_SECRET);
    $response = $this->dropbox->oAuthRequestToken();
    if(!isset($_GET['authorize'])) {
      $this->dropbox->oAuthAuthorize($response['oauth_token'],
          'http://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'] .'?authorize=true');
    } else {
      $response = $this->dropbox->oAuthAccessToken($_GET['oauth_token']);
    }

    // DEPRECATED: Alternative authorization method.
    // Do not use this if you can authorize with OAuth.
    // $response = $dropbox->token(EMAIL, PASSWORD);

    echo "Please add the following information to your <code>config.php</code>:";
    echo htmlspecialchars($response, ENT_QUOTES, 'UTF-8');
  }

  /**
   * Get an overview of all albums inside this gallery.
   *
   * @param  $thumb_size   Size of thumbnail for each album.
   * @return $album_thumbs An array of thumbnails.
   */
  public function gallery_index($thumb_size = "")
  {
    if ($thumb_size) $this->set_thumbsize($thumb_size);
    $albums = $this->get_contents(BASE_PATH, "dir");
    return $this->get_album_thumbs($albums);
  }

  /**
   * Get an overview of all images inside an album.
   *
   * @param  $thumb_size   Size of thumbnail for each album.
   * @return $image_thumbs An array of thumbnails.
   */
  public function album_index($album_name, $thumb_size = "")
  {
    if ($thumb_size) $this->set_thumbsize($thumb_size);
    $images = $this->get_contents(BASE_PATH . $album_name . "/", "no_dir");
    return $this->get_thumbs($images);
  }

  public function get_image($album, $image)
  {
    $path = BASE_PATH . $album . "/" . $image;
    $file = $this->dropbox->filesGet($path);
    return $file["data"];
  }

  /**
   * Get thumbnails for an array of albums.
   *
   * @param $path       Fetch thumbnails for albums in this path
   * @param $thumb_size Size of thumbnail for each album.
   * @return $thumbs    An array of thumbnails.
   */
  private function get_album_thumbs($path, $thumb_size = "")
  {
    if ($thumb_size) $this->set_thumbsize($thumb_size);

    $thumbs = array();
    // Get the first thumbnail inside every supplied album
    foreach ($path as $album) {
      $images = $this->get_contents($album);
      // Get first image thumb in directory as album thumb.
      $thumb = $this->get_thumbs($images, 0, 1);
      $thumbs = array_merge($thumbs, $thumb);
    }
    return $thumbs;
  }

  /**
   * Get thumbnails for images inside a path
   *
   * @param $images       Get thumbnails for these images
   * @param $thumb_size   Size of thumbnail
   * @param $thumbs_start Index of first image to get thumb for
   * @param $thumbs_max   Maximum number of thumbs
   *
   * @return $thumbs      Array of thumbs for $path
   */
  private function get_thumbs($images, $thumbs_start = 0, $thumbs_max = -1)
  {

    $thumbs = array(); // Put all thumbnails into an array.
    $image_count = 0;  // Count the number of valid thumbnails.

    foreach ( $images as $image) {
      if ($image["thumb_exists"]) {
        // Check if we need to fetch the thumbnail for this image.
        if ($image_count < $thumbs_start) {
          continue;
        }
        if ($thumbs_max != -1 && $image_count >= $thumbs_max) {
          break; // Enough thumbnails.
        }
        $thumb = $this->cache_thumb($image);

        // Only pass valid albums with a thumbnail to front-end
        if (!empty($thumb)) {
          $image_info = array();
          $image_info["thumb"] = $thumb;
          $image_info["name"]  = basename($image);
          $image_info["album_name"] = $this->album_name($image);
          array_push($thumbs, $image_info);
          // Increase number of valid thumbnails.
          $image_count++;
        }
      }
    }
    return $thumbs;
  }

  /**
   * Get thumbnail from cache.
   */
  private function cache_thumb($image)
  {
    $file = $this->cache_thumb_name($image);

    if (!file_exists($file)) {
      $this->cache_store_thumb($image);
    }
    $thumb = base64_encode(file_get_contents($file));
    return $thumb;
  }

  /**
   * Store thumbnail in cache.
   */
  private function cache_store_thumb($image)
  {
    $file = $this->cache_thumb_name($image);
    $thumb_information = $this->dropbox->thumbnails($image, $this->thumb_size);
    $thumb = $thumb_information["data"];
    $album_dir = dirname($file);
    if (!file_exists($album_dir)) {
      mkdir($album_dir, 0777, true);
    }
    file_put_contents($file, base64_decode($thumb));
  }

  /**
   * Filename of thumbnail.
   */
  private function cache_thumb_name($image)
  {
    $path = $this->album_name($image);
    $filename = $this->strip_extension($image);
    $extension = ".jpeg";
    return "cache/" . $path . "/" . $filename . $extension;
  }

  /**
   * Get album name of image
   *
   * @param $image  The image
   * @return $path  The path to the image
   */
  private function album_name($image)
  {
    // Get path to image
    $absolute_dir = dirname($image);
    // Get all directories leading to image
    $parts = explode("/", $absolute_dir);
    // Album name is last directory in path
    $last_index = count($parts)-1;
    return $parts[$last_index];
  }

  /**
   * Get contents (entries) of path.
   * (the Dropbox "Photos" album by default).
   *
   * @param $path       The directory path to scan
   * @param $filter     Filter for directories or files
   *                    (can be "dir", "no_dir" or "")
   * @return $contents  Entries of a directory
   */
  private function get_contents($path = BASE_PATH, $filter="")
  {
    // Get info from server.
    $metadata = $this->dropbox->metadata($path);
    $contents = array();

    foreach ($metadata["contents"] as $content) {
      // All contents (files and folders) are accepted by default
      $valid_entry = true;
      if ($filter == "dir") {
        // Only return directories
        $valid_entry = $content["is_dir"];
      } else if ($filter == "no_dir") {
        // Only return files
        $valid_entry = !$content["is_dir"];
      }
      if ($valid_entry) {
        array_push($contents, $content["path"]);
      }
    }
    return $contents;
  }

  /**
   * Strip extension from filename
   */
  private function strip_extension($file)
  {
    $info = pathinfo($file);
    return basename($file,'.'.$info['extension']);
  }

  private function set_thumbsize($thumbsize) {
    $this->thumb_size = $thumbsize;
  }
}
?>

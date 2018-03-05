<?php
if (!defined('BASEPATH')) {
    exit('No direct script access allowed');
}

/**
 * URL_tube Class
 */
class URL_tube
{
    public $return_data;

    /**
     * Constructor
     */
    public function __construct()
    {
        $src = ee()->TMPL->fetch_param('src');

        if ($video_id = $this->getVideoID($src)) {

            //Set video dimensions and selector attributes
            list($w, $h) = $this->getDimensions(ee()->TMPL->fetch_param('width'), ee()->TMPL->fetch_param('height'));
            $sel = $this->makeSelectorString();
            $site = $this->getVideoSite($src);
            $query_string = $this->getQueryString($site);
            $protocol = $this->getProtocol(ee()->TMPL->fetch_param('ssl'), $src);

            //output markup to the template
            if ($site == 'youtube') {

                $this->return_data = "<iframe width='$w' height='$h' $sel src='$protocol://www.youtube.com/embed/$video_id$query_string'
                    frameborder='0' allowfullscreen></iframe>";

            } else if ($site == 'vimeo') {

                $this->return_data = "<iframe src='$protocol://player.vimeo.com/video/$video_id$query_string' width='$w' height='$h'
                    frameborder='0' webkitAllowFullScreen mozallowfullscreen allowFullScreen></iframe>";
            }
        }
    }

    /**
     * Conveneince function for getting Video ID from template
     */
    public function id()
    {
        $s = ee()->TMPL->fetch_param('src');
        return $this->getVideoID($s);
    }

    public function get_video_site()
    {
        $s = ee()->TMPL->fetch_param('src');
        $site = $this->getVideoSite($s);
        return $site;
    }

    /**
     * Generates a thumbnail image for the video
     */
    public function thumbnail()
    {
        $src = ee()->TMPL->fetch_param('src');
        $href_only = ee()->TMPL->fetch_param('href_only');

        $vid = $this->getVideoID($src);
        list($width, $height) = $this->getDimensions(ee()->TMPL->fetch_param('width'), ee()->TMPL->fetch_param('height'));
        $sel = $this->makeSelectorString();

        $site = $this->getVideoSite($src);
        $url = ($site == 'youtube') ? "http://img.youtube.com/vi/$vid/0.jpg" : $this->getVimeoThumbnailUrl($vid, $width);

        if (!$url) {
            return null;
        } else if ($href_only) {
            return $url;
        } else {
            return "<img src='$url' alt='Video Thumbnail' $sel height='$height' width='$width'/>";
        }

    }

    /**
     * Returns the correct thumbnail URL from the Vimeo API, or false on failure
     */
    private function getVimeoThumbnailUrl($video_id, $width)
    {
        $api_response = @file_get_contents("http://vimeo.com/api/v2/video/$video_id.php");
        $video_data = @unserialize(trim($api_response));

        //if the response looks right, decide which size thumbnail to use
        if (isset($video_data[0])) {
            if ($width <= 100) {
                return $video_data[0]['thumbnail_small'];
            } elseif ($width <= 200) {
                return $video_data[0]['thumbnail_medium'];
            }
            return $video_data[0]['thumbnail_large'];
        }
        return false;
    }

    /**
     * Decides what protocol to use (http or https) and returns the protocol portion of the URL
     */
    private function getProtocol($ssl, $src)
    {
        //if the ssl flag is set, go with that
        if ($ssl == "yes" || $ssl == "on") {
            return "https";
        } else if ($ssl == "no" || $ssl == "off") {
            return "http";
        }

        //otherwise look for protocol in the url, falling back on http if it's not found.
        $segs = parse_url($src);
        if ($segs['scheme'] == "http" || $segs['scheme'] == "https") {
            return $segs['scheme'];
        }
        return "http";
    }

    /**
     * Retrieves the host from the video URL
     */
    private function getVideoHost($src)
    {
        $segs = parse_url($src);
        if (!isset($segs['host'])) //Die if there is no host in passed URL.
        {
            return false;
        }

        return $segs['host'];
    }

    /**
     * Gets video site from video URL
     */
    private function getVideoSite($src)
    {
        $youtube_hosts = array('youtube.com', 'www.youtube.com', 'youtu.be');
        $vimeo_hosts = array('vimeo.com', 'www.vimeo.com');

        $host = $this->getVideoHost($src);

        if (in_array($host, $youtube_hosts)) {
            return 'youtube';
        } else if (in_array($host, $vimeo_hosts)) {
            return 'vimeo';
        } else {
            return false;
        }
    }

    /**
     * Fetch the Video ID from any Youtube/Vimeo URL
     */
    private function getVideoID($src)
    {
        $site = $this->getVideoSite($src);
        $segs = parse_url($src);
        if (empty($segs['host'])) //Die if there is no host in passed URL.
        {
            return false;
        }

        $host = $segs['host'];
        $path = $segs['path'];
        $vid = null;

        if ($site == 'youtube') {
            if ($host == 'youtu.be' && $path) {
                //Extract from share URL
                $vid = substr($path, 1);
            } else if (($host == 'youtube.com' || $host == 'www.youtube.com' || $host == 'youtube.googleapis.com')) {

                if (isset($segs['query'])) {
                    //Extract from full URL
                    parse_str($segs['query'], $query);
                    if (isset($query['v'])) {
                        $vid = $query['v'];
                    }
                } elseif (strpos($path, "embed/") !== false) {
                    //Extract from embed URL
                    $embedloc = strpos($path, "embed/");
                    $vid = substr($path, $embedloc + 6);
                }

                if (empty($vid)) {
                    $vid = end(explode('/', $path));
                }
            }

            //Validate and return Video ID
            if ($vid && preg_match('/^[a-zA-Z0-9_\-]{11}$/', $vid, $matches)) {
                return $matches[0];
            } else {
                return false;
            }

        } else if ($site == 'vimeo') {

            $chars = str_split($path);
            $vid = '';

            if (isset($segs['query'])) {
                //Extract from full URL
                parse_str($segs['query'], $query);
                $vid = $query['clip_id'];
            }

            if (empty($vid)) {
                $id_started = false; //flag is set when we start finding numeric characters
                foreach ($chars as $char) {
                    if (preg_match('/^[0-9]{1}$/', $char)) {
                        if ($id_started) {
                            $vid .= $char;
                        } else {
                            $vid = $char;
                            $id_started = true;
                        }
                    } else {
                        $id_started = false;
                    }
                }
            }

            if ($vid && is_numeric($vid)) {
                return $vid;
            } else {
                return false;
            }
        }
    }

    /**
     * Returns a query string of all embed params that were passed through template, to be appended to embed src
     */
    private function getQueryString($site)
    {
        //whitelist all supported attributes
        $valid_attrs = array();
        if ($site == 'youtube') {

            $valid_attrs = array('autohide', 'autoplay', 'cc_load_policy', 'color', 'controls', 'disablekb', 'enablejsapi', 'end', 'fs',
                'iv_load_policy', 'list', 'listType', 'loop', 'modestbranding', 'origin', 'playerapiid', 'playlist', 'rel', 'showinfo', 'start', 'theme', 'vq');
        } elseif ($site == 'vimeo') {

            $valid_attrs = array('title', 'byline', 'portrait', 'color', 'autoplay', 'loop', 'api', 'player_id');
        }

        //loop through supported attributes, appending all the ones actually used to a query string
        $query_string = '?';
        foreach ($valid_attrs as $attr) {
            $value = ee()->TMPL->fetch_param($attr);
            if (strlen($value)) {
                $query_string .= $attr . '=' . $value . '&';
            }
        }
        $query_string = substr($query_string, 0, -1); //remove last character (which will be either '?' or '&')

        return $query_string;
    }

    /**
     * Validate class and id attributes, return them in a string to be used on an html element
     */
    private function makeSelectorString()
    {
        $class = ee()->TMPL->fetch_param('class');
        $id = ee()->TMPL->fetch_param('id');

        $selectors = "";
        if ($id && preg_match("/-?[_a-zA-Z]+[_a-zA-Z0-9-]*/", $id)) {
            $selectors .= "id='$id' ";
        }

        if ($class && preg_match("/-?[_a-zA-Z]+[_a-zA-Z0-9-]*/", $class)) {
            $selectors .= "class='$class' ";
        }

        return $selectors;
    }

    /**
     * Given some combination of set or unset height and width, determine the output dimensions
     */
    private function getDimensions($w, $h)
    {
        if ($h && $w) {
            //Height and width both set
            $h = intval($h);
            $w = intval($w);
        } else if ($h) {
            //Height set, calculate width
            $h = intval($h);
            $w = ceil($h * 16 / 9);
        } else if ($w) {
            //Width set, calculate height
            $w = intval($w);
            $h = ceil($w * 9 / 16);
        } else {
            //Fall back on defaults
            $w = 560;
            $h = 315;
        }
        return array($w, $h);
    }
    public static function usage()
    {
        ob_start();?>

  The Memberlist Plugin simply outputs a
  list of 15 members of your site.

  {exp:memberlist}

  This is an incredibly simple Plugin.


  <?php
$buffer = ob_get_contents();
        ob_end_clean();

        return $buffer;
    }
}

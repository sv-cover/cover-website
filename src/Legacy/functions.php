<?php
if (!defined('IN_SITE'))
    return;

/**
 * Format a string with php-style variables with optional modifiers.
 *
 * Format description:
 *     $var            Will be replaced by the value of $params['var'].
 *     $var|modifier   Will be replaced by the value of modifier($params['var'])
 *
 * Example:
 *     format_string('This is the $day|ordinal day', array('day' => 5))
 *     results in "This is the 5th day"
 *
 * @param string $format the format of the string
 * @param array $params a table of variables that will be replaced
 * @return string a formatted string in which all the variables are replaced
 * as far as they can be found in $params.
 */
function format_string($format, $params)
{
    if (!(is_array($params) || $params instanceof ArrayAccess))
        throw new \InvalidArgumentException('$params has to behave like an array');

    $callback =  function($match) use ($params) {
        $path = explode('[', $match[1]);

        // remove ] from all 1..n components
        for ($i = 1; $i < count($path); ++$i)
            $path[$i] = substr($path[$i], 0, -1);

        // Step 0
        $value = $params;

        foreach ($path as $step) {
            if (isset($value[$step])) {
                $value = $value[$step];
            } else {
                $value = null;
                break;
            }
        }

        // If there is a modifier, apply it
        if (isset($match[2]))
            $value = call_user_func($match[2], $value);

        return $value;
    };

    return preg_replace_callback('/\$([a-z][a-z0-9_]*(?:\[[a-z0-9_]+\])*)(?:\|([a-z_]+))?/i', $callback, $format);
}

// almost dead: only used in framework/member.php:member_full_name
function optional($value)
{
    return !empty($value) ? ' ' . $value : '';
}


// Used in email templates
function markup_format_text($text)
{
    $text = htmlspecialchars($text ?? '', ENT_COMPAT, WEBSITE_ENCODING);
    return $text;
}

// Used in email templates
function markup_format_attribute($text)
{
    return htmlspecialchars($text, ENT_QUOTES, WEBSITE_ENCODING);
}

// only used in DataModelPage::get_summary
function markup_strip($markup)
{
    return preg_replace('/\[[^\[\]\s]*\]/', '', $markup ?? '');
}

/**
 * Parse an email message and substitute variables and constants. The
 * function will first look for email in public/email and will
 * fallback to the default theme if the file could not be found
 * @param string the name of the email file to parse
 * @param array the data to substitute
 *
 * @return string A string with substituted data and constants
 */
function parse_email($email, $data)
{
    if (file_exists('templates/email/' . $email))
        $contents = file_get_contents('templates/email/' . $email);
    else
        throw new RuntimeException("Could not find email template '$email'");

    $contents = preg_replace_callback('/[A-Z]+_[A-Z_]+/', function($match) { return constant($match[0]); }, $contents);

    return format_string($contents, $data);
}

class SimpleEmail
{
    public $subject;

    public $headers;

    public $body;

    public function __construct($subject, $body, $headers)
    {
        $this->subject = $subject;
        $this->body = $body;
        $this->headers = $headers;
    }

    public function send($recipient)
    {
        return mail($recipient, $this->subject, $this->body, $this->headers);
    }
}

function parse_email_object($file, array $variables = array())
{
    $path = 'templates/email/' . $file;

    if (!file_exists($path))
        throw new InvalidArgumentException("Cannot find file '{$file}' in any theme data");

    $file_body = file_get_contents($path);

    if (!$file_body)
        throw new InvalidArgumentException("File '{$path}' is unreadable or empty");

    $subject = null;

    $headers = array();

    $body = '';

    $parsing_headers = true;

    foreach (preg_split("/\r?\n/", $file_body) as $line) {
        if (preg_match("/^([a-z0-9_-]+):\s+(.+?)$/i", $line, $match)) {
            // Remove newlines from header values because they mess up other headers
            // (Better would be to indent them with spaces, but that is probably never really needed.)
            $header_value = preg_replace('/(\r?\n)+/', ' ', format_string($match[2], $variables));

            if (strcasecmp($match[1], 'Subject') === 0)
                $subject = $header_value;
            else
                $headers[] = sprintf("%s: %s", $match[1], $header_value);
        }
        else
            $parsing_headers = false;

        if (!$parsing_headers)
            $body .= format_string($line, $variables) . "\r\n";
    }

    return new SimpleEmail($subject, ltrim($body), implode("\r\n", $headers));
}

function set_domain_cookie($name, $value, $cookie_time = 0)
{
    // Determine the host name for the cookie (try to be as broad as possible so sd.svcover.nl can profit from it)
    if (preg_match('/([^.]+)\.(?:[a-z\.]{2,6})$/i', $_SERVER['HTTP_HOST'], $match))
        $domain = $match[0];
    else if ($_SERVER['HTTP_HOST'] != 'localhost')
        $domain = $_SERVER['HTTP_HOST'];
    else
        $domain = null;

    $domain = preg_replace('/:\d+$/', '', $domain);

    // If the value is empty, expire the cookie
    if ($value === null)
        $cookie_time = 1;

    $options = [
        'expires' => $cookie_time,
        'path' => '/',
        'domain' => $domain,
        'httponly' => true,
    ];

    if (!empty($_SERVER['HTTPS'])) {
        $options['secure'] = true;
        $options['samesite'] = 'None';
    }

    setcookie($name, $value ?? '', $options);

    if ($cookie_time === 0 || $cookie_time > time())
        $_COOKIE[$name] = $value;
    else
        unset($_COOKIE[$name]);
}

/**
 * Implementation of array_search that supports a user-defined compare function.
 * Returns the key or index at which $needle is found in $haystack. If needle
 * is not found, it returns NULL.
 *
 * @var mixed $needle The item searched for
 * @var array $haystack The array to search in (list or hashtable)
 * @var callable $compare_function A compare function that gets $needle and an item
 *      from $haystack and should return true if they are 'equal' or false otherwise.
 * @return mixed the key at which $needle is found. Returns NULL if $needle is not found
 */
function array_usearch($needle, array $haystack, $compare_function)
{
    foreach ($haystack as $i => $item)
        if (call_user_func($compare_function, $needle, $item))
            return $i;

    return null;
}

/**
 * Concatenates multiple path parts together with a directory separator (/) between them.
 *
 * @var string $path_component
 * @var string ...
 * @return string the concatenated path
 */
function path_concat($path_components)
{
    $path_components = func_get_args();

    $path = '';

    foreach ($path_components as $path_component)
    {
        if (strlen($path) === 0)
            $path .= rtrim($path_component, '/');
        else
            $path .= '/' . trim($path_component, '/');
    }

    return $path;
}

function path_subtract($full_path, $basedir)
{
    if (substr($full_path, 0, strlen($basedir)) != $basedir)
        throw new InvalidArgumentException('Full path is not a path inside the given base directory');

    return ltrim(substr($full_path, strlen($basedir)), '/');
}

function crc32_file($path)
{
    return hash_file('CRC32', $path, false);
}

function encode_data_uri($mime_type, $data)
{
    return 'data:' . $mime_type . ';base64,' . base64_encode($data);
}

// only used in DataModelPage::get_summary
function summarize($text, $length)
{
    $text = trim($text);

    if (strlen($text) < $length)
        return $text;

    $summary = substr($text, 0, $length);

    if (!in_array(substr($summary, -1, 1), ['.', ' ', '!', '?']))
        $summary = substr($summary, 0, -1) . '…';

    return $summary;
}

function apply_image_orientation(\Imagick $image, $background_color = '#000')
{
    // Copied from https://stackoverflow.com/a/31943940/770911

    $orientation = $image->getImageOrientation();

    // See https://www.daveperrett.com/articles/2012/07/28/exif-orientation-handling-is-a-ghetto/
    switch ($image->getImageOrientation())
    {
        case \Imagick::ORIENTATION_TOPLEFT:
            break;
        case \Imagick::ORIENTATION_TOPRIGHT:
            $image->flopImage();
            break;
        case \Imagick::ORIENTATION_BOTTOMRIGHT:
            $image->rotateImage($background_color, 180);
            break;
        case \Imagick::ORIENTATION_BOTTOMLEFT:
            $image->flopImage();
            $image->rotateImage($background_color, 180);
            break;
        case \Imagick::ORIENTATION_LEFTTOP:
            $image->flopImage();
            $image->rotateImage($background_color, -90);
            break;
        case \Imagick::ORIENTATION_RIGHTTOP:
            $image->rotateImage($background_color, 90);
            break;
        case \Imagick::ORIENTATION_RIGHTBOTTOM:
            $image->flopImage();
            $image->rotateImage($background_color, 90);
            break;
        case \Imagick::ORIENTATION_LEFTBOTTOM:
            $image->rotateImage($background_color, -90);
            break;
        default: // Invalid orientation
            break;
    }

    $image->setImageOrientation(\Imagick::ORIENTATION_TOPLEFT);
}

function strip_exif_data(\Imagick $image)
{
    // Safe the color profiles because those we want to keep
    $profiles = $image->getImageProfiles('icc', true);

    // Strip all the exif info (including orientation!)
    $image->stripImage();

    // Reset those profiles (if there were any in the first place)
    if ($profiles)
        $image->profileImage('icc', $profiles['icc']);
}

function get_filemanager_url($path, $width=null)
{
    if (empty($path))
        return '';
    $filemanager_root = get_config_value('filemanager_root', 'https://filemanager.svcover.nl');
    $resize_exts = get_config_value('filemanager_image_resize_extensions', ['jpg', 'jpeg', 'png']);
    if (!$width || !in_array(pathinfo($path, PATHINFO_EXTENSION), $resize_exts))
        return sprintf('%s/%s', $filemanager_root, $path);
    return sprintf('%s/images/resize?f=%s&w=%d', $filemanager_root, urlencode($path), $width);
}

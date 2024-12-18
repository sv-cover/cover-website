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

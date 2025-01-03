<?php
if (!defined('IN_SITE'))
    return;

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

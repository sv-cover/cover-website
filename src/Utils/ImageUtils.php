<?php

namespace App\Utils;

final class ImageUtils
{
    public function reorient(\Imagick $image, string $backgroundColor = '#000'): void
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
                $image->rotateImage($backgroundColor, 180);
                break;
            case \Imagick::ORIENTATION_BOTTOMLEFT:
                $image->flopImage();
                $image->rotateImage($backgroundColor, 180);
                break;
            case \Imagick::ORIENTATION_LEFTTOP:
                $image->flopImage();
                $image->rotateImage($backgroundColor, -90);
                break;
            case \Imagick::ORIENTATION_RIGHTTOP:
                $image->rotateImage($backgroundColor, 90);
                break;
            case \Imagick::ORIENTATION_RIGHTBOTTOM:
                $image->flopImage();
                $image->rotateImage($backgroundColor, 90);
                break;
            case \Imagick::ORIENTATION_LEFTBOTTOM:
                $image->rotateImage($backgroundColor, -90);
                break;
            default: // Invalid orientation
                break;
        }

        $image->setImageOrientation(\Imagick::ORIENTATION_TOPLEFT);
    }


    public function stripExif(\Imagick $image): void
    {
        // Safe the color profiles because those we want to keep
        $profiles = $image->getImageProfiles('icc', true);

        // Strip all the exif info (including orientation!)
        $image->stripImage();

        // Reset those profiles (if there were any in the first place)
        if ($profiles)
            $image->profileImage('icc', $profiles['icc']);
    }

}

<?php

namespace App\Utils;

use Symfony\Component\HttpFoundation\Response;

final class ImageUtils
{
    const CACHE_EXPIRES = 24*3600; // 24 hours

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

    /**
     * Serve an image with the correct headers to make caching possible.
     */
    public function getCachedImageResponse(
        string $image,
        ?string $lastModified = null,
        int $expires = self::CACHE_EXPIRES
    ): Response
    {
        $response = new Response($image);

        $response->setPublic();
        $response->setMaxAge($expires);

        // Set more headers
        $type = (new \finfo(\FILEINFO_MIME_TYPE))->buffer($image);
        if ($type !== null)
            $response->headers->set('Content-Type', $type);

        if ($lastModified !== null)
            $response->headers->set('Last-Modified', $lastModified);

        return $response;
    }

    /**
     * Inform the browser nothing has changed since last time.
     */
    public function getNotModifiedResponse(int $expires = self::CACHE_EXPIRES): response
    {
        $response = new Response();
        $response->setPublic();
        $response->setMaxAge($expires);
        return $response;
    }
}

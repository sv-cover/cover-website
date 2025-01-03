<?php

namespace App\Utils;

final class HumanizeUtils
{
    /**
     * A.K.A. implode_human
     * Implode a list while separating it with , (except for the last item
     * for which "and" is used instead of a comma)
     *
     * This can be used inside templates, but Twig's join also supports the same
     * functionality, so that's preferred.
     *
     * @list the list to implode
     *
     * @result a string in the format item1, item2 and item3
     */
    public static function implode(array $list): string
    {
        $len = count($list);

        if ($len === 0)
            return '';
        elseif ($len === 1)
            return reset($list);
        else
            return implode(', ', array_slice($list, 0, $len - 1)) . __(' and ') . end($list);
    }

    public static function fileSize(int $bytes, int $decimals = 2): string
    {
        $size = ['B','kB','MB','GB','TB','PB','EB','ZB','YB'];
        $factor = (int) floor((strlen($bytes) - 1) / 3);
        return sprintf("%.{$decimals}f", $bytes / pow(1024, $factor)) . $size[$factor];
    }

    public static function dateRelative(string|int $time): string
    {
        if (!is_int($time) && !ctype_digit($time))
            $time = strtotime($time);

        $diff = time() - $time;

        if ($diff == 0)
            return __('now');

        else if ($diff > 0)
        {
            $day_diff = floor($diff / 86400);

            if ($day_diff == 0)
            {
                if ($diff < 60) return __('less than a minute ago');
                if ($diff < 120) return __('1 minute ago');
                if ($diff < 3600) return sprintf(__('%d minutes ago'), floor($diff / 60));
                if ($diff < 7200) return __('1 hour ago');
                if ($diff < 86400) return sprintf(__('%d hours ago'), floor($diff / 3600));
            }
            if ($day_diff == 1) return __('Yesterday');
            if ($day_diff < 7) return sprintf(__('%d days ago'), $day_diff);
            // if ($day_diff < 31) return sprintf(__('%d weeks ago'), floor($day_diff / 7));
            // if ($day_diff < 60) return __('last month');
            if ($day_diff < 180) return date('F j', $time);
            return date('F j, Y', $time);
        }
        else
            return date('F j, Y', $time);
    }
}

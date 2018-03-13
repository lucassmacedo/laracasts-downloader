<?php
/**
 * Utilities
 */

namespace App\Utils;

/**
 * Class Utils
 * @package App\Utils
 */
class Utils
{
    /**
     * New line supporting cli or browser.
     *
     * @return string
     */
    public static function newLine()
    {
        if (php_sapi_name() == "cli") {
            return "\n";
        }

        return "<br>";
    }


    /**
     * Counts the lessons from the array.
     *
     * @param $array
     *
     * @return int
     */
    public static function countLessons($array)
    {
        return count($array['mangas']);
    }

    /**
     * Counts the episodes from the array.
     *
     * @param $array
     *
     * @return int
     */
    public static function countEpisodes($array)
    {
        $total = 0;
        foreach ($array['mangas'] as $serie) {
            $total += count($serie);
        }

        return $total;
    }

    /**
     * Compare two arrays and returns the diff array.
     *
     * @param $onlineListArray
     * @param $localListArray
     *
     * @return array
     */
    public static function resolveFaultyLessons($onlineListArray, $localListArray)
    {
        $array = [];
        $array['mangas'] = [];

        foreach ($onlineListArray['mangas'] as $serie => $episode) {
            if (isset($localListArray['mangas'][$episode])) {
                if (count($episode) == count($localListArray['mangas'][$episode])) {
                    continue;
                }
            } else {
                $array['mangas'][$serie] = $episode;
            }
        }

        return $array;
    }

    /**
     * Echo's text in a nice box.
     *
     * @param $text
     */
    public static function box($text)
    {
        echo self::newLine();
        echo "====================================" . self::newLine();
        echo $text . self::newLine();
        echo "====================================" . self::newLine();
    }

    /**
     * Echo's a message.
     *
     * @param $text
     */
    public static function write($text)
    {
        echo "> " . $text . self::newLine();
    }


    /**
     * Echo's a message in a new line.
     *
     * @param $text
     */
    public static function writeln($text)
    {
        echo self::newLine();
        echo "> " . $text . self::newLine();
    }

    /**
     * Convert bytes to precision
     * @param $bytes
     * @param int $precision
     * @return string
     */
    public static function formatBytes($bytes, $precision = 2)
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];

        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);

        $bytes /= (1 << (10 * $pow));

        return round($bytes, $precision) . ' ' . $units[$pow];
    }

    /**
     * Calculate a percentage
     * @param $cur
     * @param $total
     * @return float
     */
    public static function getPercentage($cur, $total)
    {
        return @($cur / $total * 100); //hide warning division by zero
    }
}

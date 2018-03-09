<?php
/**
 * Dom Parser
 */

namespace App\Html;

use App\Exceptions\NoDownloadLinkException;
use Symfony\Component\DomCrawler\Crawler;

/**
 * Class Parser
 * @package App\Html
 */
class Parser
{
    /**
     * Parses the html and adds the lessons the the array.
     *
     * @param $html
     * @param $array
     */
    public static function getAllLessons($html, &$array)
    {
        $parser = new Crawler($html);

        $parser->filter('li.row')->each(function (Crawler $node) use (&$array) {
            $link = $node->children()->attr('href');
            if (preg_match('/' . MANGAS_PATH . '\/(.+)/', $link, $matches)) { // lesson
                $array['mangas'][] = $matches[1];
            }
        });
    }

    /**
     * Parses the html and adds the lessons the the array.
     *
     * @param $html
     * @param $array
     */
    public static function getAllChapters($html, &$array)
    {
        $parser = new Crawler($html);

        $parser->filter('img.img-manga')->each(function (Crawler $node) use (&$array) {
            $array[] = $node->attr('src');
        });
    }

    /**
     * Determines if there is next page, false if not or the link.
     *
     * @param $html
     *
     * @return bool|string
     */
    public static function hasNextPage($html)
    {
        $parser = new Crawler($html);

        $node = $parser->filter('[rel=next]');
        if ($node->count() > 0) {
            return $node->attr('href');
        }

        return FALSE;
    }

    /**
     * Gets the token input.
     *
     * @param $html
     *
     * @return string
     */
    public static function getToken($html)
    {
        $parser = new Crawler($html);

        return $parser->filter("input[name=_token]")->attr('value');
    }

    /**
     * Gets the download link.
     *
     * @param $html
     * @return string
     * @throws NoDownloadLinkException
     */
    public static function getDownloadLink($html)
    {
        return BASE_URL . $html;
    }

    /**
     * Extracts the name of the episode.
     *
     * @param $html
     *
     * @param $path
     * @return string
     */
    public static function getNameOfEpisode($html, $path)
    {
        $parser = new Crawler($html);
        $t = $parser->filter("a[href='/" . $path . "']")->text();

        return trim($t);
    }
}

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
    public static function getAllChapters($html, &$array)
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
    public static function getAllPagesChapters($html, &$array)
    {
        $parser = new Crawler($html);

        $parser->filter('img.img-manga')->each(function (Crawler $node) use (&$array) {
            $array[] = $node->attr('src');
        });
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

}

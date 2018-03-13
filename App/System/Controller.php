<?php
/**
 * System Controller
 */

namespace App\System;

use App\Downloader;
use League\Flysystem\Filesystem;

/**
 * Class Controller
 * @package App\System
 */
class Controller
{
    /**
     * Flysystem lib
     * @var Filesystem
     */
    private $system;

    /**
     * Receives dependencies
     *
     * @param Filesystem $system
     */
    public function __construct(Filesystem $system)
    {
        $this->system = $system;
    }

    /**
     * Gets the array of the local lessons & series.
     *
     * @return array
     */
    public function getAllMangas()
    {
        $array = [];
        $array['mangas'] = $this->getSeries(TRUE);

        Downloader::$totalMangasLessons = count($array['mangas']);

        return $array;
    }

    /**
     * Get the series
     *
     * @param bool $skip
     *
     * @return array
     */
    private function getSeries($skip = FALSE)
    {

        $list = $this->system->listContents(MANGAS_FOLDER, TRUE);

        $array = [];

        foreach ($list as $entry) {
            if ($entry['type'] != 'file') {
                continue;
            } //skip folder, we only want the files

            $serie = substr($entry['dirname'], strlen(MANGAS_FOLDER) + 1);
            $episode = (int)substr($entry['filename'], 0, strpos($entry['filename'], '-'));

            $array[$serie][] = $episode;
        }

        if ($skip) {
            foreach ($this->getSkipSeries() as $skipSerie => $episodes) {
                if (!isset($array[$skipSerie])) {
                    $array[$skipSerie] = $episodes;
                    continue;
                }

                $array[$skipSerie] = array_merge($array[$skipSerie], $episodes);
                $array[$skipSerie] = array_filter(array_unique($array[$skipSerie]));
            }
        }

        return $array;
    }

    /**
     * Create skip file to lessons
     */
    public function writeSkipSeries()
    {
        $file = MANGAS_FOLDER . '/.skip';

        $series = serialize($this->getSeries(TRUE));

        if ($this->system->has($file)) {
            $this->system->delete($file);
        }

        $this->system->write($file, $series);
    }

    /**
     * Get skiped series
     * @return array
     */
    public function getSkipSeries()
    {
        return $this->getSkipedData(MANGAS_FOLDER . '/.skip');
    }

    /**
     * Read skip file
     *
     * @param $pathToSkipFile
     * @return array|mixed
     */
    private function getSkipedData($pathToSkipFile)
    {

        if ($this->system->has($pathToSkipFile)) {
            $content = $this->system->read($pathToSkipFile);

            return unserialize($content);
        }

        return [];
    }


    /**
     * Create series folder if not exists.
     *
     * @param $serie
     */
    public function createSerieFolderIfNotExists($serie)
    {
        $this->createFolderIfNotExists(MANGAS_FOLDER . '/' . $serie);
    }

    /**
     * Create folder if not exists.
     *
     * @param $folder
     */
    public function createFolderIfNotExists($folder)
    {
        if ($this->system->has($folder) === FALSE) {
            $this->system->createDir($folder);
        }
    }
}

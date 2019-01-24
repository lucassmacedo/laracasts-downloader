<?php
/**
 * Http functions
 */

namespace App\Http;

use App\Downloader;
use App\Html\Parser;
use App\Utils\Utils;
use GuzzleHttp\Client;
use GuzzleHttp\Cookie\CookieJar;
use League\Flysystem\Filesystem;
use Ubench;
use App\System\Controller;
use League\CLImate\CLImate;

/**
 * Class Resolver
 * @package App\Http
 */
class Resolver
{
    /**
     * Guzzle client
     * @var Client
     */
    private $client;

    /**
     * Guzzle cookie
     * @var CookieJar
     */
    private $cookie;

    /**
     * Ubench lib
     * @var Ubench
     */
    private $bench;
    private $system;

    /**
     * Retry download on connection fail
     * @var int
     */
    private $retryDownload = FALSE;
    private $climate;

    /**
     * Receives dependencies
     *
     * @param Client $client
     * @param Ubench $bench
     * @param bool $retryDownload
     */
    public function __construct(Client $client, Ubench $bench, $retryDownload = FALSE, Filesystem $system)
    {
        $this->client = $client;
        $this->cookie = new CookieJar();
        $this->bench = $bench;
        $this->retryDownload = $retryDownload;
        $this->system = new Controller($system);
        $this->climate = new CLImate;
    }

    /**
     * Grabs all lessons & mangas from the website.
     */
    public function getAllMangas($mangas)
    {
        $array = [];
        $html = $this->getAllPage($mangas);

        Parser::getAllChapters($html, $array);
        if (empty($array['mangas'])) {
            Utils::writeln('Não foram encontrados capítulos nesta página');
            die();
        }
        
        $array['mangas']  = array_reverse($array['mangas']);
        
        Downloader::$currentLessonNumber = count($array['mangas']);

        return $array;
    }


    /**
     * Gets the html from the all page.
     *
     * @return string
     *
     * @throws \Exception
     */
    private function getAllPage($mangas)
    {
        $response = $this->client->get(MANGAS_PATH . '/' . $mangas[0], ['verify' => FALSE]);

        return $response->getBody()->getContents();
    }

    /**
     * Downloads the lesson.
     *
     * @param $lesson
     * @return bool
     */
    public function downloadChapter($lesson)
    {
        $path = MANGAS_PATH . '/' . $lesson . '/';

        $this->system->createFolderIfNotExists($path);

        $html = $this->getPage($path);
        $array = [];
        Parser::getAllPagesChapters($html, $array);
        if (!empty($array)) {
            $this->bench->start();
            $progress = $this->climate->progress()->total(count($array));
            Utils::writeln(sprintf("Iniciando download: %s . . . .",
                $lesson
            ));
            foreach ($array as $key => $item) {
                $progress->advance();
                $saveTo = BASE_FOLDER . '/' . $path . sprintf("%02d", $key + 1) . '.jpg';
                $this->downloadMangaFromPath($item, $saveTo);
            }
            $this->bench->end();
            Utils::write(sprintf("Tempo: %s, Memória: %s         ",
                $this->bench->getTime(),
                $this->bench->getMemoryUsage()
            ));
        }
    }

    /**
     * Helper to download the video.
     *
     * @param $html
     * @param $saveTo
     * @return bool
     */
    private function downloadMangaFromPath($html, $saveTo)
    {
        $downloadUrl = Parser::getDownloadLink($html);

        file_put_contents($saveTo, file_get_contents($downloadUrl));

        return TRUE;
    }

    /**
     * Helper function to get html of a page
     * @param $path
     * @return string
     */
    private function getPage($path)
    {
        return $this->client
            ->get($path, ['verify' => FALSE])
            ->getBody()
            ->getContents();
    }

}

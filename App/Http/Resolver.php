<?php
/**
 * Http functions
 */

namespace App\Http;

use App\Downloader;
use App\Exceptions\NoDownloadLinkException;
use App\Exceptions\SubscriptionNotActiveException;
use App\Html\Parser;
use App\Utils\Utils;
use GuzzleHttp\Client;
use GuzzleHttp\Cookie\CookieJar;
use GuzzleHttp\Event\ProgressEvent;
use League\Flysystem\Filesystem;
use Ubench;
use App\System\Controller;

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
    }

    /**
     * Grabs all lessons & mangas from the website.
     */
    public function getAllMangas($mangas)
    {
        $array = [];
        $html = $this->getAllPage($mangas);
        Parser::getAllLessons($html, $array);

        Downloader::$currentLessonNumber = count($array['mangas']);

        return $array;
    }

    /**
     * Gets the latest lessons only.
     *
     * @return array
     *
     * @throws \Exception
     */
    public function getLatestLessons()
    {
        $array = [];

        $html = $this->getAllPage();
        Parser::getAllLessons($html, $array);

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
     * Tries to auth.
     *
     * @param $email
     * @param $password
     *
     * @return bool
     * @throws SubscriptionNotActiveException
     */
    public function doAuth($email, $password)
    {
        $response = $this->client->get(LARACASTS_LOGIN_PATH, [
            'cookies' => $this->cookie,
            'verify'  => FALSE
        ]);

        $token = Parser::getToken($response->getBody()->getContents());

        $response = $this->client->post(LARACASTS_POST_LOGIN_PATH, [
            'cookies' => $this->cookie,
            'body'    => [
                'email'    => $email,
                'password' => $password,
                '_token'   => $token,
                'remember' => 1,
            ],
            'verify'  => FALSE
        ]);

        $html = $response->getBody()->getContents();

        if (strpos($html, "Reactivate") !== FALSE) {
            throw new SubscriptionNotActiveException();
        }

        if (strpos($html, "The email must be a valid email address.") !== FALSE) {
            return FALSE;
        }

        // user doesnt provided an email in the .env
        // laracasts redirects to login page again
        if (strpos($html, 'name="password"') !== FALSE) {
            return FALSE;
        }

        return strpos($html, "verify your credentials.") === FALSE;
    }

    /**
     * Download the episode of the serie.
     *
     * @param $serie
     * @param $episode
     * @return bool
     */
    public function downloadSerieEpisode($serie, $episode)
    {
        $path = LARACASTS_SERIES_PATH . '/' . $serie . '/episodes/' . $episode;
        $episodePage = $this->getPage($path);
        $name = $this->getNameOfEpisode($episodePage, $path);
        $number = sprintf("%02d", $episode);
        $saveTo = BASE_FOLDER . '/' . MANGAS_FOLDER . '/' . $serie . '/' . $number . '-' . $name . '.mp4';
        Utils::writeln(sprintf("Download started: %s . . . . Saving on " . MANGAS_FOLDER . '/' . $serie . ' folder.',
            $number . ' - ' . $name
        ));

        return $this->downloadLessonFromPath($episodePage, $saveTo);
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
        Parser::getAllChapters($html, $array);
        if (!empty($array)) {
            $this->bench->start();
            Utils::writeln(sprintf("Iniciando download: %s . . . . Saving on " . MANGAS_FOLDER . ' folder.',
                $lesson
            ));
            foreach ($array as $key => $item) {
                $saveTo = BASE_FOLDER . '/' . $path . sprintf("%02d", $key + 1) . '.jpg';
                $this->downloadMangaFromPath($item, $saveTo);
            }
            $this->bench->end();
            Utils::write(sprintf("Elapsed time: %s, Memory: %s         ",
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
        //try {
        //    $viemoUrl = $this->getRedirectUrl($downloadUrl);
        //    $finalUrl = $this->getRedirectUrl($viemoUrl);
        //} catch (NoDownloadLinkException $e) {
        //    Utils::write(sprintf("Can't download this lesson! :( No download button"));
        //
        //    try {
        //        Utils::write(sprintf("Tring to find a Wistia.net video"));
        //        $Wistia = new Wistia($html, $this->bench);
        //        $finalUrl = $Wistia->getDownloadUrl();
        //    } catch (NoDownloadLinkException $e) {
        //        return FALSE;
        //    }
        //
        //}
        $downloadUrl = Parser::getDownloadLink($html);
        $retries = 0;
        try {
            //file_put_contents($saveTo, file_get_contents($downloadUrl));
            $downloadedBytes = file_exists($saveTo) ? filesize($saveTo) : 0;
            $req = $this->client->createRequest('GET', $downloadUrl, [
                'save_to' => fopen($saveTo, 'a'),
                'verify'  => FALSE,
                'headers' => [
                    'Range' => 'bytes=' . $downloadedBytes . '-'
                ]
            ]);
            if (php_sapi_name() == "cli") { //on cli show progress
                $req->getEmitter()->on('progress', function (ProgressEvent $e) use ($downloadedBytes) {
                    printf("> Total: %d%% Downloaded: %s of %s     \r",
                        Utils::getPercentage($e->downloaded + $downloadedBytes, $e->downloadSize),
                        Utils::formatBytes($e->downloaded + $downloadedBytes),
                        Utils::formatBytes($e->downloadSize));
                });
            }

        } catch (\Exception $e) {
            //if (is_a($e, SubscriptionNotActiveException::class) || !$this->retryDownload || ($this->retryDownload && $retries >= 3)) {
            //    throw $e;
            //}
            //++$retries;
            //Utils::writeln(sprintf("Retry download after connection fail!     "));
            //continue;
        }


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

    /**
     * Helper to get the Location header.
     *
     * @param $url
     *
     * @return string
     */
    private function getRedirectUrl($url)
    {
        $response = $this->client->get($url, [
            'cookies'         => $this->cookie,
            'allow_redirects' => FALSE,
            'verify'          => FALSE
        ]);

        return $response->getHeader('Location');
    }

    /**
     * Gets the name of the serie episode.
     *
     * @param $html
     *
     * @param $path
     * @return string
     */
    private function getNameOfEpisode($html, $path)
    {
        $name = Parser::getNameOfEpisode($html, $path);

        return Utils::parseEpisodeName($name);
    }
}

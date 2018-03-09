<?php
/**
 * Main cycle of the app
 */

namespace App;

use App\Exceptions\LoginException;
use App\Exceptions\SubscriptionNotActiveException;
use App\Http\Resolver;
use App\System\Controller;
use App\Utils\Utils;
use Cocur\Slugify\Slugify;
use GuzzleHttp\Client;
use League\Flysystem\Filesystem;
use Ubench;

/**
 * Class Downloader
 * @package App
 */
class Downloader
{
    /**
     * Http resolver object
     * @var Resolver
     */
    private $client;

    /**
     * System object
     * @var Controller
     */
    private $system;

    /**
     * Ubench lib
     * @var Ubench
     */
    private $bench;

    /**
     * Number of local lessons
     * @var int
     */
    public static $totalMangasLessons;

    /**
     * Current lesson number
     * @var int
     */
    public static $currentLessonNumber;

    private $wantSeries = [];
    private $wantLessons = [];

    /**
     * Receives dependencies
     *
     * @param Client $client
     * @param Filesystem $system
     * @param Ubench $bench
     * @param bool $retryDownload
     */
    public function __construct(Client $client, Filesystem $system, Ubench $bench, $retryDownload = FALSE)
    {
        $this->client = new Resolver($client, $bench, $retryDownload, $system);
        $this->system = new Controller($system);
        $this->bench = $bench;
    }

    /**
     * All the logic
     *
     * @param $options
     */
    public function start($options)
    {
        $counter = [
            'mangas'         => 1,
            'failed_chapter' => 0
        ];

        Utils::box('Starting Collecting the data');

        $this->bench->start();

        if (!$this->_haveOptions()) {
            Utils::box('Você precisa inserir um mangá para baixar');
            die();
        }
        $localLessons = $this->system->getAllMangas();

        $allLessonsOnline = $this->client->getAllMangas($this->wantSeries);

        $this->sortSeries($allLessonsOnline);

        $this->bench->end();

        Utils::box('Iniciando Download');

        //Magic to get what to download
        $diff = Utils::resolveFaultyLessons($allLessonsOnline, $localLessons);

        $new_lessons = Utils::countLessons($diff);

        Utils::write(sprintf("%d novos mangás (Tempo:%s - %s de uso de memória.)",
                $new_lessons,
                $this->bench->getTime(),
                $this->bench->getMemoryUsage())
        );

        ////Download Lessons
        if ($new_lessons > 0) {
            $this->downloadLessons($diff, $counter, $new_lessons);
        }

        Utils::writeln(sprintf("Concluido! Download de : %d capitulos. Falharam: %d",
            $new_lessons - $counter['failed_chapter'],
            $counter['failed_chapter']
        ));

    }

    /**
     * Download Lessons
     * @param $diff
     * @param $counter
     * @param $new_lessons
     */
    public function downloadLessons(&$diff, &$counter, $new_lessons)
    {
        $this->system->createFolderIfNotExists(MANGAS_FOLDER);

        Utils::box('Downloading Manga Chapters');
        foreach ($diff['mangas'] as $lesson) {
            if ($this->client->downloadChapter($lesson) === FALSE) {
                $counter['failed_chapter']++;
            }
            Utils::write(sprintf("Current: %d of %d total. Left: %d",
                $counter['mangas']++,
                $new_lessons,
                $new_lessons - $counter['failed_chapter'] + 1
            ));
        }
    }

    protected function _haveOptions()
    {
        $found = FALSE;

        $short_options = "m:";
        $short_options .= "l:";

        $long_options = [
            "mangas-name:"
        ];
        $options = getopt($short_options, $long_options);


        if (count($options) == 0) {
            Utils::write('No options provided');

            return FALSE;
        }

        $slugify = new Slugify();
        $slugify->addRule("'", '');

        if (isset($options['m']) || isset($options['mangas-name'])) {
            $series = isset($options['m']) ? $options['m'] : $options['mangas-name'];
            if (!is_array($series))
                $series = [$series];

            $this->wantSeries = array_map(function ($serie) use ($slugify) {
                return $slugify->slugify($serie);
            }, $series);


            $found = TRUE;
        }

        Utils::box(sprintf("Verificando: %s", implode(",", $series)));

        return $found;
    }

    /**
     * Download selected Series and lessons
     * @param $allLessonsOnline
     * @return array
     */
    public function onlyDownloadProvidedLessonsAndSeries($allLessonsOnline)
    {
        Utils::box('Checking if series and lessons exists');

        $selectedLessonsOnline = [
            'lessons' => [],
            'mangas'  => []
        ];

        foreach ($this->wantSeries as $series) {
            if (isset($allLessonsOnline['mangas'][$series])) {
                Utils::write('Series "' . $series . '" found!');
                $selectedLessonsOnline['mangas'][$series] = $allLessonsOnline['mangas'][$series];
            } else {
                Utils::write("Series '" . $series . "' not found!");
            }
        }

        return $selectedLessonsOnline;
    }

    public function sortSeries(&$allLessons)
    {
        sort($allLessons['mangas'], SORT_NUMERIC);
    }
}

<?php
/**
 * Main cycle of the app
 */

namespace App;

use App\Http\Resolver;
use App\System\Controller;
use App\Utils\Utils;
use Cocur\Slugify\Slugify;
use GuzzleHttp\Client;
use League\Flysystem\Filesystem;
use Ubench;
use League\CLImate\CLImate;

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
    private $climate;

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
        $this->climate = new CLImate;
    }

    /**
     * All the logic
     *
     * @param $options
     */
    public function start()
    {
        $counter = [
            'mangas'         => 1,
            'failed_chapter' => 0
        ];

        $this->bench->start();

        if (!$this->_haveOptions()) {
            $this->climate->red('Você precisa inserir um mangá para baixar.');
            die();
        }
        $allMangaLocal = $this->system->getAllMangas();

        $allMangaOnline = $this->client->getAllMangas($this->wantSeries);

        $this->sortSeries($allMangaOnline);

        $this->bench->end();
        $this->climate->flank('Iniciando Download')->border('=', 50);

        $diff = Utils::resolveFaultyLessons($allMangaOnline, $allMangaLocal);

        $new_manga = Utils::countLessons($diff);
        $this->climate->br();
        $this->climate->out(sprintf("%d novos mangás (Tempo:%s - %s de uso de memória.)",
                $new_manga,
                $this->bench->getTime(),
                $this->bench->getMemoryUsage())
        )->border('=', 50);

        if ($new_manga > 0) {
            $this->downloadMangaChapters($diff, $counter, $new_manga);
        }
        $this->climate->br();
        $this->climate->out(sprintf("Concluido! Download de : %d capitulos. Falharam: %d",
            $new_manga - $counter['failed_chapter'],
            $counter['failed_chapter']
        ));

    }

    /**
     * Download Manga Chapters
     * @param $diff
     * @param $counter
     * @param $new_manga
     */
    public function downloadMangaChapters(&$diff, &$counter, $new_manga)
    {
        $this->system->createFolderIfNotExists(MANGAS_FOLDER);


        foreach ($diff['mangas'] as $lesson) {
            if ($this->client->downloadChapter($lesson) === FALSE) {
                $counter['failed_chapter']++;
            }
            $this->bench->end();
            $this->climate->flank(sprintf("Atual: %d de %d. Faltando: %d",
                $counter['mangas']++,
                $new_manga,
                $new_manga - $counter['failed_chapter'] + 1
            ))->border('=', 50);
            $this->climate->br();

        }
    }

    protected function _haveOptions()
    {
        $found = FALSE;

        $short_options = "m:";

        $long_options = [
            "mangas-name:"
        ];
        $options = getopt($short_options, $long_options);


        if (count($options) == 0) return FALSE;

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
        $this->climate->br();
        $this->climate->flank(sprintf("Verificando: %s", $series[0]))->border('=', 50);
        $this->climate->br();

        return $found;
    }

    /**
     * Download selected Series and lessons
     * @param $allMangaOnline
     * @return array
     */
    public function onlyDownloadProvidedLessonsAndSeries($allMangaOnline)
    {
        Utils::box('Checking if series and lessons exists');

        $selectedLessonsOnline = [
            'lessons' => [],
            'mangas'  => []
        ];

        foreach ($this->wantSeries as $series) {
            if (isset($allMangaOnline['mangas'][$series])) {
                Utils::write('Series "' . $series . '" found!');
                $selectedLessonsOnline['mangas'][$series] = $allMangaOnline['mangas'][$series];
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

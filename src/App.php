<?php

namespace Bahuma\BahumaAbrechnung;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\MessageFormatter;

use Monolog\Logger;
use Monolog\Level;
use Monolog\Handler\StreamHandler;

use Shuchkin\SimpleXLSXGen;


class App
{
    private string $baseUrl;
    private string $token;

    private int $customFieldBetrag;
    private int $customFieldRechnungsnummerEingangsrechnung;
    private int $customFieldRechnungsnummerAusgangsrechnung;

    private int $documentTypeEingangsrechung;
    private int $documentTypeAusgangsrechung;

    /**
     * @var int[]
     */
    private array $excludedTags;

    private $client;
    private $logger;
    private $correspondentMap = [];
    private $tagMap = [];


    public function __construct()
    {
        $this->baseUrl = $_ENV['PAPERLESS_URL'] . "/api/";
        $this->token = $_ENV['PAPERLESS_TOKEN'];

        $this->customFieldBetrag = intval($_ENV['CUSTOM_FIELD_BETRAG']);
        $this->customFieldRechnungsnummerEingangsrechnung = intval($_ENV['CUSTOM_FIELD_RECHNUNGSNUMMER_EINGANGSRECHNUNG']);
        $this->customFieldRechnungsnummerAusgangsrechnung = intval($_ENV['CUSTOM_FIELD_RECHNUNGSNUMMER_AUSGANGSRECHNUNG']);

        $this->documentTypeEingangsrechung = intval($_ENV['DOCUMENT_TYPE_EINGANGSRECHNUNG']);
        $this->documentTypeAusgangsrechung = intval($_ENV['DOCUMENT_TYPE_AUSGANGSRECHNUNG']);
        $this->excludedTags = array_map(function($value) {return intval($value);}, explode(',', $_ENV['EXCLUDED_TAGS'] || ''));

        $this->logger = new Logger('BahumaAbrechnung');
        $this->logger->pushHandler(new StreamHandler('php://stdout', Level::Debug));

        $handlerStack = HandlerStack::create();
        $handlerStack->push(
            Middleware::log(
                $this->logger,
                new MessageFormatter('{req_headers}')
            )
        );

        $this->client = new Client([
            'base_uri' => $this->baseUrl,
            'headers' => [
                'Authorization' => 'Token ' . $this->token,
                'Accept' => 'application/json',
            ],
            'handler' => $handlerStack,
        ]);
    }

    public function run()
    {
        if (isset($_GET['action'])) {
            switch ($_GET['action']) {
                case "eingangsrechnungen":
                    $this->eingangsrechnungen();
                    break;
                case "ausgangsrechnungen":
                    $this->ausgangsrechnungen();
                    break;
                default:
                    $this->notFound();
                    break;
            }
        } else {
            $this->index();
        }
    }

    public function index()
    {
        print '<!doctype html><html>
<header>
    <title>Bahuma Abrechnung</title>
</header>
<body>
<ul>
<li><a href="?action=eingangsrechnungen&year=' . date('Y') . '">Ausgaben ' . date('Y') . '</a></li>
<li><a href="?action=eingangsrechnungen&year=' . date('Y') - 1 . '">Ausgaben ' . date('Y') - 1 . '</a></li>
<li><a href="?action=eingangsrechnungen&year=' . date('Y') - 2 . '">Ausgaben ' . date('Y') - 2 . '</a></li>
<li><a href="?action=ausgangsrechnungen&year=' . date('Y') . '">Einnahmen ' . date('Y') . '</a></li>
<li><a href="?action=ausgangsrechnungen&year=' . date('Y') - 1 . '">Einnahmen ' . date('Y') - 1 . '</a></li>
<li><a href="?action=ausgangsrechnungen&year=' . date('Y') - 2 . '">Einnahmen ' . date('Y') - 2 . '</a></li>
</ul>
    
</body>
</html>';
    }

    public function notFound()
    {
        print "Action nicht gefunden";
    }

    public function eingangsrechnungen()
    {
        $year = array_key_exists('year', $_GET) ? intval($_GET['year']) : date('Y');

        $startUrl = 'documents/?created__year=' . $year . '&document_type__id=' . $this->documentTypeEingangsrechung . '&ordering=created';

        $documents = $this->getPaged($startUrl);
        $documents = $this->filterDocuments($documents);

        $outputData = [
            ['<center><b><style font-size="24">Bahuma Ausgaben ' . $year . '</style></b></center>', null, null, null, null, null],
            ['Datum', 'Kategorie', 'Notiz', 'Lieferant', 'Rechnungsnummer', 'Betrag'],
        ];

        $outputData[1] = array_map(function ($cell) {
            return "<b>" . $cell . "</b>";
        }, $outputData[1]);

        $sum = 0;

        foreach ($documents as $document) {
            $correspondent = $this->getCorrespondent($document->correspondent);

            if (count($document->tags) > 0) {
                $category = $this->getTag($document->tags[0])->getName();
            } else {
                $category = "";
            }

            $date = new \DateTime($document->created);

            $betrag = $this->getCustomFieldByFieldId($document, $this->customFieldBetrag);
            $betrag = str_replace("EUR", "", $betrag);
            $betrag = doubleval($betrag);

            $outputData[] = [
                $date->format('d.m.Y'),
                $category,
                $document->title,
                $correspondent->getName(),
                $this->getCustomFieldByFieldId($document, $this->customFieldRechnungsnummerEingangsrechnung),
                $betrag . ' €',
            ];

            $sum += $betrag;
        }

        $outputData[] = [null, null, null, null, "<right><b>Summe:</b></right>", '<b>' . $sum . ' €</b>'];

        SimpleXLSXGen::fromArray($outputData)
            ->mergeCells('A1:F1')
            ->downloadAs("ausgaben_" . $year . ".xlsx");
    }

    private function ausgangsrechnungen()
    {
        $year = array_key_exists('year', $_GET) ? intval($_GET['year']) : date('Y');

        $startUrl = 'documents/?created__year=' . $year . '&document_type__id=' . $this->documentTypeAusgangsrechung . '&ordering=created';

        $documents = $this->getPaged($startUrl);
        $documents = $this->filterDocuments($documents);

        $outputData = [
            ['<center><b><style font-size="24">Bahuma Einnahmen ' . $year . '</style></b></center>', null, null, null],
            ['Datum', 'Rechnungsnummer', 'Kunde', 'Betrag'],
        ];

        $outputData[1] = array_map(function ($cell) {
            return "<b>" . $cell . "</b>";
        }, $outputData[1]);

        $sum = 0;

        foreach ($documents as $document) {
            $correspondent = $this->getCorrespondent($document->correspondent);

            $date = new \DateTime($document->created);

            $betrag = $this->getCustomFieldByFieldId($document, $this->customFieldBetrag);
            $betrag = str_replace("EUR", "", $betrag);
            $betrag = doubleval($betrag);

            $outputData[] = [
                $date->format('d.m.Y'),
                $this->getCustomFieldByFieldId($document, $this->customFieldRechnungsnummerAusgangsrechnung),
                $correspondent->getName(),
                $betrag . ' €',
            ];

            $sum += $betrag;
        }

        $outputData[] = [null, null, "<right><b>Summe:</b></right>", '<b>' . $sum . ' €</b>'];

        SimpleXLSXGen::fromArray($outputData)
            ->mergeCells('A1:F1')
            ->downloadAs("einnahmen_" . $year . ".xlsx");
    }

    private function getCustomFieldByFieldId($document, $fieldId)
    {
        foreach ($document->custom_fields as $field) {
            if ($field->field == $fieldId) {
                return $field->value;
            }
        }
        throw new \Exception("Document " . $document->id . " does not have custom field " . $fieldId . " set.");
    }

    private function getPaged($url)
    {
        $this->logger->debug("Making request to " . $url);

        $response = $this->client->get($url);

        $data = json_decode($response->getBody()->getContents());

        if ($data->next !== null) {
            $this->logger->debug('$data->next: ' . $data->next);
            $items = $this->getPaged(str_replace($this->baseUrl, '', str_replace('http://', 'https://', $data->next)));
        } else {
            $items = [];
        }

        return array_merge($items, $data->results);
    }

    /**
     * @throws GuzzleException
     */
    private function getCorrespondent($id): Correspondent
    {
        if (array_key_exists($id, $this->correspondentMap)) {
            return $this->correspondentMap[$id];
        }

        $response = $this->client->get('correspondents/' . $id . '/');

        if ($response->getStatusCode() !== 200) {
            throw new \Exception('Could not load correspondent with id ' . $id);
        }

        $correspondentData = json_decode($response->getBody()->getContents());

        $correspondent = new Correspondent($correspondentData->id, $correspondentData->name);
        $this->correspondentMap[$id] = $correspondent;
        return $correspondent;
    }

    /**
     * @throws GuzzleException
     */
    private function getTag($id): Tag
    {
        if (array_key_exists($id, $this->tagMap)) {
            return $this->tagMap[$id];
        }

        $response = $this->client->get('tags/' . $id . '/');

        if ($response->getStatusCode() !== 200) {
            throw new \Exception('Could not load tag with id ' . $id);
        }

        $tagData = json_decode($response->getBody()->getContents());

        $tag = new Tag($tagData->id, $tagData->name);
        $this->tagMap[$id] = $tag;
        return $tag;
    }

    private function filterDocuments(array $documents): array
    {
        return array_filter($documents, function ($document) {
            foreach ($this->excludedTags as $excludedTag) {
                if (in_array($excludedTag, $document->tags)) {
                    return false;
                }
            }
            return true;
        });
    }
}

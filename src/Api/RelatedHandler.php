<?php

namespace Crm\SegmentModule\Api;

use Crm\ApiModule\Models\Api\ApiHandler;
use Crm\SegmentModule\Models\Criteria\Generator;
use Crm\SegmentModule\Models\Criteria\InvalidCriteriaException;
use Crm\SegmentModule\Models\Params\BaseParam;
use Crm\SegmentModule\Repositories\SegmentsRepository;
use Nette\Application\LinkGenerator;
use Nette\Http\Response;
use Nette\Utils\Json;
use Nette\Utils\JsonException;
use Tomaj\NetteApi\Response\JsonApiResponse;
use Tomaj\NetteApi\Response\ResponseInterface;
use Tracy\Debugger;
use Tracy\ILogger;

class RelatedHandler extends ApiHandler
{
    private $segmentsRepository;

    private $generator;

    public function __construct(
        SegmentsRepository $segmentsRepository,
        LinkGenerator $linkGenerator,
        Generator $generator,
    ) {
        $this->segmentsRepository = $segmentsRepository;
        $this->linkGenerator = $linkGenerator;
        $this->generator = $generator;
    }

    /**
     * @inheritdoc
     */
    public function params(): array
    {
        return [];
    }

    public function handle(array $params): ResponseInterface
    {
        $request = file_get_contents("php://input");
        if (empty($request)) {
            $response = new JsonApiResponse(Response::S400_BAD_REQUEST, ['status' => 'error', 'message' => 'Empty request body, JSON expected']);
            return $response;
        }
        try {
            $params = Json::decode($request, Json::FORCE_ARRAY);
        } catch (JsonException $e) {
            $response = new JsonApiResponse(Response::S400_BAD_REQUEST, ['status' => 'error', 'message' => "Malformed JSON: " . $e->getMessage()]);
            return $response;
        }

        if (!isset($params['table_name'])) {
            $response = new JsonApiResponse(Response::S400_BAD_REQUEST, ['status' => 'error', 'message' => "param missing: table_name"]);
            return $response;
        }
        if (!isset($params['criteria'])) {
            $response = new JsonApiResponse(Response::S400_BAD_REQUEST, ['status' => 'error', 'message' => "param missing: criteria"]);
            return $response;
        }

        try {
            $inputCriteria = $this->generator->extractCriteria($params['table_name'], $params['criteria']);
        } catch (InvalidCriteriaException $e) {
            $response = new JsonApiResponse(Response::S400_BAD_REQUEST, ['status' => 'error', 'message' => $e->getMessage()]);
            return $response;
        }

        $segments = $this->segmentsRepository->all()->where(['version' => 2, 'table_name' => $params['table_name']]);
        $result = [];
        foreach ($segments as $segment) {
            try {
                $criteria = Json::decode($segment->criteria, Json::FORCE_ARRAY);
            } catch (JsonException $e) {
                Debugger::log("Invalid JSON structure in segment [{$segment->id}]", ILogger::ERROR);
                continue;
            }

            $segmentCriteria = $this->generator->extractCriteria($params['table_name'], $criteria);

            if ($this->isRelated($inputCriteria, $segmentCriteria)) {
                $result[] = [
                    'id' => $segment->id,
                    'name' => $segment->name,
                    'code' => $segment->code,
                    'created_at' => $segment->created_at->format('c'),
                    'url' => $this->linkGenerator->link('Segment:StoredSegments:show', ['id' => $segment->id]),
                ];
            }

            if (count($result) > 4) {
                break;
            }
        }

        $response = new JsonApiResponse(Response::S200_OK, ['segments' => $result]);
        return $response;
    }

    private function isRelated(array $inputCriteria, array $possibleCriteria)
    {
        foreach ($inputCriteria as $criteria) {
            $found = false;
            foreach ($possibleCriteria as $possible) {
                if ($possible['key'] === $criteria['key']) {
                    /** @var BaseParam $possibleParam */
                    $possibleParam = $possible['param'];
                    /** @var BaseParam $criteriaParam */
                    $criteriaParam = $criteria['param'];

                    if (get_class($possibleParam) === get_class($criteriaParam)) {
                        if ($possibleParam->equals($criteriaParam)) {
                            $found = true;
                            break;
                        }
                    }
                }
            }

            if (!$found) {
                return false;
            }
        }
        return true;
    }
}

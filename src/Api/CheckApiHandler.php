<?php

namespace Crm\SegmentModule\Api;

use Crm\ApiModule\Models\Api\ApiHandler;
use Crm\SegmentModule\Models\SegmentFactoryInterface;
use Crm\SegmentModule\Models\SegmentInterface;
use Crm\UsersModule\Repository\UsersRepository;
use Nette\Http\Response;
use Nette\UnexpectedValueException;
use Tomaj\NetteApi\Params\GetInputParam;
use Tomaj\NetteApi\Response\JsonApiResponse;
use Tomaj\NetteApi\Response\ResponseInterface;

class CheckApiHandler extends ApiHandler
{
    public function __construct(
        private SegmentFactoryInterface $segmentFactory,
        private UsersRepository $usersRepository
    ) {
        parent::__construct();
    }

    /**
     * @inheritdoc
     */
    public function params(): array
    {
        return [
            (new GetInputParam('code'))->setRequired(),
            (new GetInputParam('resolver_type'))->setRequired()->setAvailableValues(['id', 'email']),
            (new GetInputParam('resolver_value'))->setRequired(),
        ];
    }

    /**
     * @throws \Exception
     */
    public function handle(array $params): ResponseInterface
    {
        try {
            $segment = $this->segmentFactory->buildSegment($params['code']);
        } catch (UnexpectedValueException $e) {
            $response = new JsonApiResponse(Response::S404_NOT_FOUND, ['status' => 'error', 'message' => 'Segment does not exist']);
            return $response;
        }

        switch ($params['resolver_type']) {
            case 'email':
                $in = $this->checkEmail($segment, $params['resolver_value']);
                break;
            case 'id':
                $id = intval($params['resolver_value']);
                if (strval($id) != $params['resolver_value']) {
                    $in = false;
                    break;
                }
                $in = $this->checkId($segment, $id);
                break;
            default:
                throw new \Exception('InputParam value validator was supposed to filter invalid values');
        }

        $response = new JsonApiResponse(Response::S200_OK, ['status' => 'ok', 'check' => $in]);

        return $response;
    }

    /**
     * checkId verifies whether user with given ID is member of provided segment.
     */
    private function checkId(SegmentInterface $segment, int $id): bool
    {
        $user = $this->usersRepository->find($id);
        if (!$user) {
            return false;
        }
        return $segment->isIn('id', $id);
    }

    /**
     * checkEmail verifies whether user with given email is member of provided segment.
     */
    private function checkEmail(SegmentInterface $segment, string $email): bool
    {
        $user = $this->usersRepository->findBy('email', $email);
        if (!$user) {
            return false;
        }
        return $segment->isIn('email', $email);
    }
}

<?php

namespace Crm\SegmentModule\Api;

use Crm\ApiModule\Api\ApiHandler;
use Crm\ApiModule\Api\JsonResponse;
use Crm\ApiModule\Params\InputParam;
use Crm\ApiModule\Params\ParamsProcessor;
use Crm\ApiModule\Response\ApiResponseInterface;
use Crm\SegmentModule\SegmentFactoryInterface;
use Crm\SegmentModule\SegmentInterface;
use Crm\UsersModule\Repository\UsersRepository;
use Nette\Http\Response;
use Nette\UnexpectedValueException;

class CheckApiHandler extends ApiHandler
{
    private $segmentFactory;

    private $usersRepository;

    public function __construct(SegmentFactoryInterface $segmentFactory, UsersRepository $usersRepository)
    {
        $this->segmentFactory = $segmentFactory;
        $this->usersRepository = $usersRepository;
    }

    /**
     * @inheritdoc
     */
    public function params(): array
    {
        return [
            new InputParam(InputParam::TYPE_GET, 'code', InputParam::REQUIRED),
            new InputParam(InputParam::TYPE_GET, 'resolver_type', InputParam::REQUIRED, ['id', 'email']),
            new InputParam(InputParam::TYPE_GET, 'resolver_value', InputParam::REQUIRED),
        ];
    }

    /**
     * @param ApiAuthorizationInterface $authorization
     * @return \Nette\Application\Response
     * @throws \Exception
     */
    public function handle(array $params): ApiResponseInterface
    {
        $paramsProcessor = new ParamsProcessor($this->params());
        if ($paramsProcessor->hasError()) {
            $response = new JsonResponse(['status' => 'error', 'message' => 'Invalid params']);
            $response->setHttpCode(Response::S400_BAD_REQUEST);
            return $response;
        }
        $params = $paramsProcessor->getValues();

        try {
            $segment = $this->segmentFactory->buildSegment($params['code']);
        } catch (UnexpectedValueException $e) {
            $response = new JsonResponse(['status' => 'error', 'message' => 'Segment does not exist']);
            $response->setHttpCode(Response::S404_NOT_FOUND);
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
                $in = $this->checkId($segment, $params['resolver_value']);
                break;
            default:
                throw new \Exception('InputParam value validator was supposed to filter invalid values');
        }

        $response = new JsonResponse(['status' => 'ok', 'check' => $in]);
        $response->setHttpCode(Response::S200_OK);

        return $response;
    }

    /**
     * checkId verifies whether user with given ID is member of provided segment.
     *
     * @param Segment $segment
     * @param $id
     * @return bool
     */
    private function checkId(SegmentInterface $segment, $id)
    {
        $user = $this->usersRepository->find($id);
        if (!$user) {
            return false;
        }
        return $segment->isIn('id', $id);
    }

    /**
     * checkEmail verifies whether user with given email is member of provided segment.
     *
     * @param Segment $segment
     * @param $email
     * @return bool
     */
    private function checkEmail(SegmentInterface $segment, $email)
    {
        $user = $this->usersRepository->findBy('email', $email);
        if (!$user) {
            return false;
        }
        return $segment->isIn('email', $email);
    }
}

<?php

namespace damidevelopment\apiutils;

use Yii;
use yii\base\Module;
use yii\web\Application;
use yii\web\Response;
use yii\filters\ContentNegotiator;


/**
 * @author Jakub Hrášek
 */
class ApiModule extends Module
{
    /**
     * @var array the configuration for creating the serializer that formats the response data.
     */
    public $serializer = [
        'class' => Serializer::class
    ];

    /**
     * @var array the configuration for creating authenticator
     */
    public $authenticator = [
        'class' => \yii\filters\auth\HttpHeaderAuth::class,
        'header' => 'Access-Token'
    ];

    /**
     * @var string the class name of the [[identity]] object.
     */
    public $identityClass;

    public $acceptLanguages = [];

    public $errorHandler = ErrorHandler::class;

    /**
     * {@inheritdoc}
     */
    public function bootstrap($app)
    {
        $app->on(Application::EVENT_BEFORE_REQUEST, function ($event) {
            /** @var Application $app */
            $app = $event->sender;

            /** @var \yii\web\Request $request */
            $request = $app->getRequest();

            list($route, ) = $app->get('urlManager')->parseRequest($request);

            $id = $this->getUniqueId();

            // change app behavior only for requests to this module
            if (\yii\helpers\StringHelper::startsWith($route, $id) === false) {
                return;
            }

            $app->container->set('errorHandler', $this->errorHandler);

            // disable csrf cookie
            $request->enableCsrfCookie = false;

            // set web user stateless
            $webUser = $app->getUser();
            $webUser->enableAutoLogin = false;
            $webUser->enableSession = false;

            // change web user identity class for this module
            if ($this->identityClass !== null) {
                $webUser->identityClass = $this->identityClass;
            }

            // trace response data
            $app->getResponse()->on(Response::EVENT_AFTER_SEND, function ($event) {
                /** @var Response $response */
                $response = $event->sender;
                $data = \yii\helpers\Json::encode($response->data, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
                Yii::getLogger()->log($data, \yii\log\Logger::LEVEL_TRACE, 'API response payload');
            });
        });

        parent::bootstrap($app);
    }

    /**
     * {@inheritdoc}
     */
    public function behaviors()
    {
        return [
            'contentNegotiator' => [
                'class' => ContentNegotiator::class,
                'formatParam' => null,
                'languageParam' => null,
                'formats' => [
                    'application/json' => Response::FORMAT_JSON,
                ],
                'languages' => $this->acceptLanguages,
            ],
            'authenticator' => $this->authenticator,
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function afterAction($action, $result)
    {
        $result = parent::afterAction($action, $result);
        return $this->serializeData($result);
    }

    /**
     * Serializes the specified data.
     * The default implementation will create a serializer based on the configuration given by [[serializer]].
     * It then uses the serializer to serialize the given data.
     * @param mixed $data the data to be serialized
     * @return mixed the serialized data.
     */
    protected function serializeData($data)
    {
        return Yii::createObject($this->serializer)->serialize($data);
    }
}

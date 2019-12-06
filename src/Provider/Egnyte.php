<?php

declare(strict_types=1);

namespace cjensenius\OAuth2\Client\Provider;

use League\OAuth2\Client\Provider\AbstractProvider;
use League\OAuth2\Client\Provider\Exception\IdentityProviderException;
use League\OAuth2\Client\Token\AccessToken;
use League\OAuth2\Client\Tool\BearerAuthorizationTrait;
use Psr\Http\Message\ResponseInterface;

class Egnyte extends AbstractProvider
{
    use BearerAuthorizationTrait;

    public const PATH_TOKEN = '/puboauth/token';
    public const PATH_USERINFO = '/pubapi/v1/userinfo';
    public const DEFAULT_EXPIRES_IN = 1296000;

    /**
     * @param array $options
     * @param array $collaborators
     */
    public function __construct(array $options = [], array $collaborators = [])
    {
        $this->assertRequiredOptions($options);

        $possible   = $this->getConfigurableOptions();
        $configured = array_intersect_key($options, array_flip($possible));

        foreach ($configured as $key => $value) {
            $this->$key = $value;
        }

        // Remove all options that are only used locally
        $options = array_diff_key($options, $configured);

        parent::__construct($options, $collaborators);
    }

        /**
     * Returns all options that can be configured.
     *
     * @return array
     */
    protected function getConfigurableOptions()
    {
        return array_merge($this->getRequiredOptions(), []);
    }

    /**
     * Returns all options that are required.
     *
     * @return array
     */
    protected function getRequiredOptions()
    {
        return [
            'domain',
            'clientId',
            'redirectUri'
        ];
    }

    /**
     * Verifies that all required options have been passed.
     *
     * @param  array $options
     * @return void
     * @throws Exception
     */
    private function assertRequiredOptions(array $options)
    {
        $missing = array_diff_key(array_flip($this->getRequiredOptions()), $options);

        if (!empty($missing)) {
            throw new \Exception(
                'Required options not defined: ' . implode(', ', array_keys($missing))
            );
        }
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \League\OAuth2\Client\Provider\AbstractProvider::getBaseAccessTokenUrl()
     */
    public function getBaseAccessTokenUrl(array $params)
    {
        return 'https://' . $this->domain . '.egnyte.com' . self::PATH_TOKEN;
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \League\OAuth2\Client\Provider\AbstractProvider::getBaseAuthorizationUrl()
     */
    public function getBaseAuthorizationUrl()
    {
        return 'https://' . $this->domain . '.egnyte.com' . self::PATH_TOKEN;
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \League\OAuth2\Client\Provider\AbstractProvider::getDefaultScopes()
     */
    protected function getDefaultScopes()
    {
        return ['Egnyte.filesystem'];
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \League\OAuth2\Client\Provider\AbstractProvider::checkResponse()
     */
    protected function checkResponse(ResponseInterface $response, $data)
    {
        if (isset($data['error'])) {
            $message = $data['error_description'] ?? $data['error'];
            throw new IdentityProviderException($message, $response->getStatusCode(), $response);
        }
    }

    /**
     *
     * {@inheritDoc}
     * @see \League\OAuth2\Client\Provider\AbstractProvider::getResourceOwnerDetailsUrl()
     */
    public function getResourceOwnerDetailsUrl(AccessToken $token)
    {
        return 'https://' . $this->domain . '.egnyte.com' . self::PATH_USERINFO;
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \League\OAuth2\Client\Provider\AbstractProvider::createResourceOwner()
     */
    protected function createResourceOwner(array $response, AccessToken $token)
    {
        return new EgnyteResourceOwner($response);
    }

    /**
     * Prepares an parsed access token response for a grant.
     *
     * Custom mapping of expiration, etc should be done here. Always call the
     * parent method when overloading this method.
     *
     * @param  mixed $result
     * @return array
     */
    protected function prepareAccessTokenResponse(array $result)
    {
        $response = parent::prepareAccessTokenResponse($result);
        if ($response['expires_in'] == -1) {
            $response['expires_in'] = self::DEFAULT_EXPIRES_IN;
        }
        return $response;
    }
}

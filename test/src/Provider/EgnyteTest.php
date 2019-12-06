<?php
namespace cjensenius\OAuth2\Client\Test\Provider;

use League\OAuth2\Client\Tool\QueryBuilderTrait;
use Mockery as m;
use PHPUnit\Framework\TestCase;

class EgnyteTest extends TestCase
{
    use QueryBuilderTrait;

    protected $provider;

    protected function setUp(): void
    {
        $this->provider = new \cjensenius\OAuth2\Client\Provider\Egnyte([
            'domain' => 'domain',
            'clientId' => 'mock_client_id',
            'clientSecret' => 'mock_secret',
            'redirectUri' => 'none',
        ]);
    }

    public function tearDown(): void
    {
        m::close();
        parent::tearDown();
    }

    public function testAuthorizationUrl()
    {
        $url = $this->provider->getAuthorizationUrl();
        $uri = parse_url($url);
        parse_str($uri['query'], $query);

        $this->assertArrayHasKey('client_id', $query);
        $this->assertArrayHasKey('redirect_uri', $query);
        $this->assertArrayHasKey('state', $query);
        $this->assertArrayHasKey('scope', $query);
        $this->assertArrayHasKey('response_type', $query);
        $this->assertArrayHasKey('approval_prompt', $query);
        $this->assertNotNull($this->provider->getState());
    }


    public function testScopes()
    {
        $scopeSeparator = ',';
        $options = ['scope' => [uniqid(), uniqid()]];
        $query = ['scope' => implode($scopeSeparator, $options['scope'])];
        $url = $this->provider->getAuthorizationUrl($options);
        $encodedScope = $this->buildQueryString($query);
        $this->assertStringContainsString($encodedScope, $url);

    }

    public function testMissingArguments()
    {
        $this->expectException("Exception");
        $this->provider = new \cjensenius\OAuth2\Client\Provider\Egnyte([
        ]);
    }

    public function testGetAuthorizationUrl()
    {
        $url = $this->provider->getAuthorizationUrl();
        $uri = parse_url($url);

        $this->assertEquals('/puboauth/token', $uri['path']);
    }

    public function testGetBaseAccessTokenUrl()
    {
        $params = [];

        $url = $this->provider->getBaseAccessTokenUrl($params);
        $uri = parse_url($url);

        $this->assertEquals('/puboauth/token', $uri['path']);
    }

    public function testGetAccessToken()
    {
        $response = m::mock('Psr\Http\Message\ResponseInterface');
        $response->shouldReceive('getBody')->andReturn('{"access_token":"mock_access_token", "scope":"repo,gist", "token_type":"bearer"}');
        $response->shouldReceive('getHeader')->andReturn(['content-type' => 'json']);
        $response->shouldReceive('getStatusCode')->andReturn(200);

        $client = m::mock('GuzzleHttp\ClientInterface');
        $client->shouldReceive('send')->times(1)->andReturn($response);
        $this->provider->setHttpClient($client);
        
        $token = $this->provider->getAccessToken('password', [
            'username' => 'fakeuser',
            'password' => 'fakepassword',
            'baseUrl' => 'test.egnyte.com'
        ]);

        $this->assertEquals('mock_access_token', $token->getToken());
        $this->assertNull($token->getExpires());
        $this->assertNull($token->getRefreshToken());
        $this->assertNull($token->getResourceOwnerId());
    }

    public function testEgnyteEnterpriseDomainUrls()
    {
        $this->provider->expectedDomain = 'https://domain.egnyte.com';


        $response = m::mock('Psr\Http\Message\ResponseInterface');
        $response->shouldReceive('getBody')->times(1)->andReturn('access_token=mock_access_token&expires=3600&refresh_token=mock_refresh_token&otherKey={1234}');
        $response->shouldReceive('getHeader')->andReturn(['content-type' => 'application/x-www-form-urlencoded']);
        $response->shouldReceive('getStatusCode')->andReturn(200);

        $client = m::mock('GuzzleHttp\ClientInterface');
        $client->shouldReceive('send')->times(1)->andReturn($response);
        $this->provider->setHttpClient($client);

        $token = $this->provider->getAccessToken('authorization_code', ['code' => 'mock_authorization_code']);

        $this->assertEquals($this->provider->expectedDomain.'/puboauth/token', $this->provider->getBaseAuthorizationUrl());
        $this->assertEquals($this->provider->expectedDomain.'/puboauth/token', $this->provider->getBaseAccessTokenUrl([]));
        $this->assertEquals($this->provider->expectedDomain.'/pubapi/v1/userinfo', $this->provider->getResourceOwnerDetailsUrl($token));
    }

    public function testNegativeExpiresIn()
    {
        $response = m::mock('Psr\Http\Message\ResponseInterface');
        $response->shouldReceive('getBody')->times(1)->andReturn('access_token=mock_access_token&expires_in=-1&refresh_token=mock_refresh_token&otherKey={1234}');
        $response->shouldReceive('getHeader')->andReturn(['content-type' => 'application/x-www-form-urlencoded']);
        $response->shouldReceive('getStatusCode')->andReturn(200);

        $client = m::mock('GuzzleHttp\ClientInterface');
        $client->shouldReceive('send')->times(1)->andReturn($response);
        $this->provider->setHttpClient($client);

        $token = $this->provider->getAccessToken('authorization_code', ['code' => 'mock_authorization_code']);
        $this->assertEquals(false, $token->hasExpired());
    }

    public function testUserData()
    {
        $userId = rand(1000,9999);
        $name = uniqid();
        $nickname = uniqid();
        $email = uniqid();

        $postResponse = m::mock('Psr\Http\Message\ResponseInterface');
        $postResponse->shouldReceive('getBody')->andReturn('access_token=mock_access_token&expires=3600&refresh_token=mock_refresh_token&otherKey={1234}');
        $postResponse->shouldReceive('getHeader')->andReturn(['content-type' => 'application/x-www-form-urlencoded']);
        $postResponse->shouldReceive('getStatusCode')->andReturn(200);

        $userResponse = m::mock('Psr\Http\Message\ResponseInterface');
        $userResponse->shouldReceive('getBody')->andReturn('{"id": '.$userId.', "username": "'.$nickname.'", "name": "'.$name.'", "email": "'.$email.'"}');
        $userResponse->shouldReceive('getHeader')->andReturn(['content-type' => 'json']);
        $userResponse->shouldReceive('getStatusCode')->andReturn(200);

        $client = m::mock('GuzzleHttp\ClientInterface');
        $client->shouldReceive('send')
            ->times(2)
            ->andReturn($postResponse, $userResponse);
        $this->provider->setHttpClient($client);
        $token = $this->provider->getAccessToken('password', [
            'username' => 'fakeuser',
            'password' => 'fakepassword',
            'baseUrl' => 'test.egnyte.com'
        ]);
        $user = $this->provider->getResourceOwner($token);

        $this->assertEquals($userId, $user->getId());
        $this->assertEquals($userId, $user->toArray()['id']);
        $this->assertEquals($nickname, $user->getNickname());
        $this->assertEquals($nickname, $user->toArray()['username']);
    }

    public function testExceptionThrownWhenErrorObjectReceived()
    {
        $this->expectException("InvalidArgumentException");
        $status = rand(400,600);
        $postResponse = m::mock('Psr\Http\Message\ResponseInterface');
        $postResponse->shouldReceive('getBody')->andReturn('{"message": "Validation Failed","errors": [{"resource": "Issue","field": "title","code": "missing_field"}]}');
        $postResponse->shouldReceive('getHeader')->andReturn(['content-type' => 'json']);
        $postResponse->shouldReceive('getStatusCode')->andReturn($status);

        $client = m::mock('GuzzleHttp\ClientInterface');
        $client->shouldReceive('send')
            ->times(1)
            ->andReturn($postResponse);
        $this->provider->setHttpClient($client);
        $token = $this->provider->getAccessToken('password', [
            'username' => 'fakeuser',
            'password' => 'fakepassword',
            'baseUrl' => 'test.egnyte.com'
        ]);
    }

    public function testExceptionThrownWhenOAuthErrorReceived()
    {
        $this->expectException("League\OAuth2\Client\Provider\Exception\IdentityProviderException");
        $status = 200;
        $postResponse = m::mock('Psr\Http\Message\ResponseInterface');
        $postResponse->shouldReceive('getBody')->andReturn('{"error": "bad_verification_code","error_description": "The code passed is incorrect or expired.","error_uri": "https://developer.github.com/v3/oauth/#bad-verification-code"}');
        $postResponse->shouldReceive('getHeader')->andReturn(['content-type' => 'json']);
        $postResponse->shouldReceive('getStatusCode')->andReturn($status);

        $client = m::mock('GuzzleHttp\ClientInterface');
        $client->shouldReceive('send')
            ->times(1)
            ->andReturn($postResponse);
        $this->provider->setHttpClient($client);
        $token = $this->provider->getAccessToken('password', [
            'username' => 'fakeuser',
            'password' => 'fakepassword',
            'baseUrl' => 'test.egnyte.com'
        ]);
    }
}

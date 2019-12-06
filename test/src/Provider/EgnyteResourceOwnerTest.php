<?php 
namespace cjensenius\OAuth2\Client\Test\Provider;

use Mockery as m;
use PHPUnit\Framework\TestCase;

class EgnyteResourceOwnerTest extends TestCase
{
    public function testNicknameIsUsername()
    {
        $expectedValue = uniqid();
        $user = new \cjensenius\OAuth2\Client\Provider\EgnyteResourceOwner([
            'username' => $expectedValue
        ]);
        $value = $user->getNickname();

        $this->assertEquals($expectedValue, $value);
    }
}

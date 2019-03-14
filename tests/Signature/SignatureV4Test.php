<?php
namespace JdcloudSign\Test\Signature;

use JdcloudSign\Credentials\Credentials;
use JdcloudSign\Signature\SignatureV4;
use GuzzleHttp\Psr7;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\NoSeekStream;

use PHPUnit\Framework\TestCase;

/**
 * @covers Jdcloud\Signature\SignatureV4
 */
class SignatureV4Test extends TestCase
{
    const DEFAULT_KEY = '35DDDCFFB86CF2D494F0F3B6B0B3EF68';
    const DEFAULT_SECRET = '93C107EF1F3A0C46C6329C04F561A29E';
    const DEFAULT_DATETIME = 'Mon, 09 Sep 2011 23:36:00 GMT';

    public function setup()
    {
        $_SERVER['jdcloud_time'] = strtotime('December 5, 2013 00:00:00 UTC');
    }

    public function testReturnsRegionAndService()
    {
        $s = new SignatureV4('foo', 'bar');
        $this->assertEquals('foo', $this->readAttribute($s, 'service'));
        $this->assertEquals('bar', $this->readAttribute($s, 'region'));
    }

    public function testAddsSecurityTokenIfPresent()
    {
        $s = new SignatureV4('foo', 'bar');
        $c = new Credentials('a', 'b', 'AddMe!');
        $r = new Request('GET', 'http://httpbin.org');
        $signed = $s->signRequest($r, $c);
        $this->assertEquals('AddMe!', $signed->getHeaderLine('x-jdcloud-Security-Token'));
    }

    public function testSignsRequestsWithMultiValuedHeaders()
    {
        $s = new SignatureV4('foo', 'bar');
        $r = new Request('GET', 'http://httpbin.org', ['x-jdcloud-Foo' => ['baz', '  bar ']]);
        $methA = new \ReflectionMethod($s, 'parseRequest');
        $methA->setAccessible(true);
        $reqArray = $methA->invoke($s, $r);
        $methB = new \ReflectionMethod($s, 'createContext');
        $methB->setAccessible(true);
        $result = $methB->invoke($s, $reqArray, '123');
        $this->assertEquals('host;x-jdcloud-foo', $result['headers']);
        $this->assertEquals("GET\n/\n\nhost:httpbin.org\nx-jdcloud-foo:bar,baz\n\nhost;x-jdcloud-foo\n123", $result['creq']);
    }

    public function testUsesExistingSha256HashIfPresent()
    {
        $sig = new SignatureV4('foo', 'bar');
        $req = new Request('PUT', 'http://foo.com', [
            'x-jdcloud-content-sha256' => '123'
        ]);
        $method = new \ReflectionMethod($sig, 'getPayload');
        $method->setAccessible(true);
        $this->assertSame('123', $method->invoke($sig, $req));
    }

    public function testMaintainsCappedCache()
    {
        $sig = new SignatureV4('foo', 'bar');
        // Hack the class so that it thinks it needs 3 more entries to be full
        $p = new \ReflectionProperty($sig, 'cacheSize');
        $p->setAccessible(true);
        $p->setValue($sig, 47);

        $request = new Request('GET', 'http://www.example.com');
        $credentials = new Credentials('fizz', 'buzz');
        $sig->signRequest($request, $credentials);
        $this->assertCount(1, $this->readAttribute($sig, 'cache'));

        $credentials = new Credentials('fizz', 'baz');
        $sig->signRequest($request, $credentials);
        $this->assertCount(2, $this->readAttribute($sig, 'cache'));

        $credentials = new Credentials('fizz', 'paz');
        $sig->signRequest($request, $credentials);
        $this->assertCount(3, $this->readAttribute($sig, 'cache'));

        $credentials = new Credentials('fizz', 'foobar');
        $sig->signRequest($request, $credentials);
        $this->assertCount(1, $this->readAttribute($sig, 'cache'));
    }

    private function getFixtures()
    {
        $request = new Request('GET', 'http://foo.com');
        $credentials = new Credentials('foo', 'bar');
        $signature = new SignatureV4('service', 'region');

        return array($request, $credentials, $signature);
    }

    public function testAddsSecurityTokenIfPresentInPresigned()
    {
        $_SERVER['override_v4_time'] = true;
        list($request, $credentials, $signature) = $this->getFixtures();
        $credentials = new Credentials('foo', 'bar', '123');
        $url = (string) $signature->presign($request, $credentials, 1386720000)->getUri();
        $this->assertContains('x-jdcloud-security-token=123', $url);
    }

    public function testUsesStartDateFromDateTimeIfPresent()
    {
        $options = ['start_time' => new \DateTime('December 5, 2013 00:00:00 UTC')];
        unset($_SERVER['aws_time']);

        list($request, $credentials, $signature) = $this->getFixtures();
        $credentials = new Credentials('foo', 'bar', '123');
        $url = (string) $signature->presign($request, $credentials, 1386720000, $options)->getUri();
        $this->assertContains('x-jdcloud-date=20131205T000000Z', $url);
    }

    public function testUsesStartDateFromUnixTimestampIfPresent()
    {
        $options = ['start_time' => strtotime('December 5, 2013 00:00:00 UTC')];
        unset($_SERVER['jdcloud_time']);

        list($request, $credentials, $signature) = $this->getFixtures();
        $credentials = new Credentials('foo', 'bar', '123');
        $url = (string) $signature->presign($request, $credentials, 1386720000, $options)->getUri();
        $this->assertContains('x-jdcloud-date=20131205T000000Z', $url);
    }

    public function testUsesStartDateFromStrtotimeIfPresent()
    {
        $options = ['start_time' => 'December 5, 2013 00:00:00 UTC'];
        unset($_SERVER['jdcloud_time']);

        list($request, $credentials, $signature) = $this->getFixtures();
        $credentials = new Credentials('foo', 'bar', '123');
        $url = (string) $signature->presign($request, $credentials, 1386720000, $options)->getUri();
        $this->assertContains('x-jdcloud-date=20131205T000000Z', $url);
    }


    public function testPresignerDowncasesSignedHeaderNames()
    {
        $_SERVER['override_v4_time'] = true;
        list($request, $credentials, $signature) = $this->getFixtures();
        $credentials = new Credentials('foo', 'bar', '123');
        $query = Psr7\parse_query(
            $signature->presign($request, $credentials, 1386720000)
                ->getUri()
                ->getQuery()
        );
        $this->assertArrayHasKey('x-jdcloud-signedHeaders', $query);
        $this->assertSame(
            strtolower($query['x-jdcloud-signedHeaders']),
            $query['x-jdcloud-signedHeaders']
        );
    }

    public function testConvertsPostToGet()
    {
        $request = new Request(
            'POST',
            'http://foo.com',
            ['Content-Type' => 'application/x-www-form-urlencoded'],
            'foo=bar&baz=bam'
        );
        $request = SignatureV4::convertPostToGet($request);
        $this->assertEquals('GET', $request->getMethod());
        $this->assertEquals('foo=bar&baz=bam', $request->getUri()->getQuery());
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testEnsuresMethodIsPost()
    {
        $request = new Request('PUT', 'http://foo.com');
        SignatureV4::convertPostToGet($request);
    }

    public function testSignSpecificHeaders()
    {
        $sig = new SignatureV4('foo', 'bar');
        $creds = new Credentials('a', 'b');
        $req = new Request('PUT', 'http://foo.com', [
            'x-jdcloud-date' => 'today',
            'host' => 'foo.com',
            'x-jdcloud-foo' => '123',
            'content-md5' => 'bogus'
        ]);
        $signed = $sig->signRequest($req, $creds);
        $this->assertContains('content-md5;host;x-jdcloud-algorithm;x-jdcloud-date;x-jdcloud-foo', $signed->getHeaderLine('Authorization'));
    }

    /**
     * @expectedException \RuntimeException
     */
    public function testEnsuresContentSha256CanBeCalculated()
    {
        list($request, $credentials, $signature) = $this->getFixtures();
        $request = $request->withBody(new NoSeekStream(Psr7\stream_for('foo')));
        $signature->signRequest($request, $credentials);
    }

    /**
     * @expectedException \RuntimeException
     */
    public function testEnsuresContentSha256CanBeCalculatedWhenSeekFails()
    {
        list($request, $credentials, $signature) = $this->getFixtures();
        $stream = Psr7\FnStream::decorate(Psr7\stream_for('foo'), [
            'seek' => function () {
                throw new \Exception('Could not seek');
            }
        ]);
        $request = $request->withBody($stream);
        $signature->signRequest($request, $credentials);
    }

    public function testUnsignedPayloadProvider()
    {
        return [
            // simple post.
            [
                "POST /v1/regions/cn-north-1/instances content-type:application/json\r\nHost: openapi-fullnat-test.jdcloud.com\r\nx-jdcloud-date: 20180305T004143Z\r\nx-jdcloud-nonce:2a564157-c2c5-4695-aaa4-91e487ea3976\r\n\r\n",
                "POST /v1/regions/cn-north-1/instances content-type:application/json\r\nHost: openapi-fullnat-test.jdcloud.com\r\nx-jdcloud-date: 20180305T004143Z\r\nx-jdcloud-nonce:2a564157-c2c5-4695-aaa4-91e487ea3976\r\nx-jdcloud-Content-Sha256: UNSIGNED-PAYLOAD\r\nAuthorization: JDCLOUD2-HMAC-SHA256 Credential=35DDDCFFB86CF2D494F0F3B6B0B3EF68/20180305/cn-north-1/vm/jdcloud2_request, SignedHeaders=content-type;host;x-jdcloud-date;x-jdcloud-nonce, Signature=28d89bc07353e475f84e90d2991e512de29c58475da4c42f2a6439100f450b12\r\n\r\n",
                "POST\n/v1/regions/cn-north-1/instances\n\nhost:openapi-fullnat-test.jdcloud.com\nx-jdcloud-date:20180305T004143Z\nx-jdcloud-nonce:2a564157-c2c5-4695-aaa4-91e487ea3976\n\nhost;x-jdcloud-date;x-jdcloud-nonce\ne3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855"
            ],
        ];
    }

    /**
     * @dataProvider testUnsignedPayloadProvider
     */
    public function testSignRequestUnsignedPayload($req, $sreq, $creq)
    {
        $_SERVER['jdcloud_time'] = '20180305T004143Z';
        $credentials = new Credentials(self::DEFAULT_KEY, self::DEFAULT_SECRET);
        $signature = new SignatureV4('host', 'cn-north-1');
        $request = Psr7\parse_request($req);
        $contextFn = new \ReflectionMethod($signature, 'createContext');
        $contextFn->setAccessible(true);
        $parseFn = new \ReflectionMethod($signature, 'parseRequest');
        $parseFn->setAccessible(true);
        $parsed = $parseFn->invoke($signature, $request);
        $payloadFn = new \ReflectionMethod($signature, 'getPayload');
        $payloadFn->setAccessible(true);
        $payload = $payloadFn->invoke($signature, $request);
        $this->assertEquals('UNSIGNED-PAYLOAD',$payload);
        $ctx = $contextFn->invoke($signature, $parsed, $payload);
        $this->assertEquals($creq, $ctx['creq']);
        $this->assertSame($sreq, Psr7\str($signature->signRequest($request, $credentials)));
    }

    public function testProvider()
    {
        return [
            // simple get.
            [
                "GET /v1/regions/cn-north-1/instances content-type:application/json\r\nHost: openapi-fullnat-test.jdcloud.com\r\nx-jdcloud-date: 20180305T004143Z\r\nx-jdcloud-nonce:2a564157-c2c5-4695-aaa4-91e487ea3976\r\n\r\n",
                "GET /v1/regions/cn-north-1/instances content-type:application/json\r\nHost: openapi-fullnat-test.jdcloud.com\r\nx-jdcloud-date: 20180305T004143Z\r\nx-jdcloud-nonce:2a564157-c2c5-4695-aaa4-91e487ea3976\r\nAuthorization: JDCLOUD2-HMAC-SHA256 Credential=35DDDCFFB86CF2D494F0F3B6B0B3EF68/20180305/cn-north-1/vm/jdcloud2_request, SignedHeaders=content-type;host;x-jdcloud-date;x-jdcloud-nonce, Signature=28d89bc07353e475f84e90d2991e512de29c58475da4c42f2a6439100f450b12\r\n\r\n",
                "GET\n/v1/regions/cn-north-1/instances\n\nhost:openapi-fullnat-test.jdcloud.com\nx-jdcloud-date:20180305T004143Z\nx-jdcloud-nonce:2a564157-c2c5-4695-aaa4-91e487ea3976\n\nhost;x-jdcloud-date;x-jdcloud-nonce\ne3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855"
            ],
        ];
    }

    /**
     * @dataProvider testProvider
     */
    public function testSignsRequests($req, $sreq, $creq)
    {
        $_SERVER['jdcloud_time'] = '20180305T004143Z';
        $credentials = new Credentials(self::DEFAULT_KEY, self::DEFAULT_SECRET);
        $signature = new SignatureV4('host', 'cn-north-1');
        $request = Psr7\parse_request($req);
        $contextFn = new \ReflectionMethod($signature, 'createContext');
        $contextFn->setAccessible(true);
        $parseFn = new \ReflectionMethod($signature, 'parseRequest');
        $parseFn->setAccessible(true);
        $parsed = $parseFn->invoke($signature, $request);
        $payloadFn = new \ReflectionMethod($signature, 'getPayload');
        $payloadFn->setAccessible(true);
        $payload = $payloadFn->invoke($signature, $request);
        $ctx = $contextFn->invoke($signature, $parsed, $payload);
        var_dump($creq);
        var_dump($ctx['creq']);
        $this->assertEquals($creq, $ctx['creq']);
        var_dump($sreq);
//         var_dump($request);
        var_dump(Psr7\str($signature->signRequest($request, $credentials)));
        $this->assertSame($sreq, Psr7\str($signature->signRequest($request, $credentials)));
    }
}

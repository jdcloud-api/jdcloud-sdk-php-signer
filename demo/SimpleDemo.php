<?php
namespace JdcloudSign\Signature;

require '../vendor/autoload.php';

use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Request;
use JdcloudSign\Credentials\Credentials;
use JdcloudSign\Signature\SignatureV4;

print("start demo");
$demo = new SimpleDemo();
print("-------- start get ----------");
$demo->doGet();
print("-------- start post ----------");
$demo->doPost();
print("-------- start post big body ----------");
$demo->doPostBigBody();
//  $demo->doTest();

class SimpleDemo {
    
    public function doGet() {
        $credentials = new Credentials('F1F513F897FAA88AED2889DAAA0E8D59', 'A2EEC56F0E7FAAA8B1D420FBECFC3CD5');
        $signature = new SignatureV4('testApiGroup', 'cn-north-1');
        $request = new Request('GET', 'http://xqokj9u7k483.cn-north-1.jdcloud-api.net/iamget?p=p&name=test',
            [
                'x-my-header' => 'test',
            ]
            );
        $signedRequest = $signature->signRequest($request, $credentials);
//         var_dump($signedRequest);
        $client = new Client();
        
        try{
            $response = $client->send($signedRequest, [
                'timeout' => 20,
            ]);
            $body = $response->getBody();
            
            var_dump($response->getStatusCode());
            var_dump($response->getHeaders());

            $stringBody = (string) $body;
            var_dump($stringBody);
        }catch (\RuntimeException $e) {
            var_dump($e->getMessage());
        }
    }
    
    
    public function doPost() {
        $credentials = new Credentials('F1F513F897FAA88AED2889DAAA0E8D59', 'A2EEC56F0E7FAAA8B1D420FBECFC3CD5');
        $signature = new SignatureV4('testApiGroup', 'cn-north-1');
        $request = new Request('POST', 
            'http://xqokj9u7k483.cn-north-1.jdcloud-api.net/iampost?p1=/j&p2=/%6a&p3=/%6A&o=%&u=u&o2=гд&o1=%25',
            [
                'x-my-header' => ' \ttest\r'                
            ], 'body data'
            );
        $signedRequest = $signature->signRequest($request, $credentials);
        $client = new Client();
        
        try{
            $response = $client->send($signedRequest, [
                'timeout' => 20,
            ]);
            $body = $response->getBody();
            
            var_dump($response->getStatusCode());
            var_dump($response->getHeaders());
            
            $stringBody = (string) $body;
            var_dump($stringBody);
        }catch (\RuntimeException $e) {
            var_dump($e->getMessage());
        }
    }
    
    
    public function doPostBigBody() {
        $credentials = new Credentials('F1F513F897FAA88AED2889DAAA0E8D59', 'A2EEC56F0E7FAAA8B1D420FBECFC3CD5');
        $signature = new SignatureV4('testApiGroup', 'cn-north-1');
        $request = new Request('POST', 'http://xqokj9u7k483.cn-north-1.jdcloud-api.net/iampost',
            [
                'x-my-header' => 'test',
                'x-jdcloud-Content-Sha256' => 'UNSIGNED-PAYLOAD'
            ],                
            'assume I am big body, thus i donot want body to sign, I pass header x-jdcloud-Content-Sha256: UNSIGNED-PAYLOAD'
            
            );
        $signedRequest = $signature->signRequest($request, $credentials);
//         var_dump($signedRequest);
        $client = new Client();
        
        try{
            $response = $client->send($signedRequest, [
                'timeout' => 20,
            ]);
            $body = $response->getBody();
            
            var_dump($response->getStatusCode());
            var_dump($response->getHeaders());
            
            $stringBody = (string) $body;
               var_dump($stringBody);
        }catch (\RuntimeException $e) {
            var_dump($e->getMessage());
        }
    }
    
    
    public function doTest() {
        $credentials = new Credentials('TESTAK', 'TESTSK');
        $signature = new SignatureV4('test', 'cn-north-1');
        $request = new Request('POST',
            'http://test.jdcloud-api.com/v1/resource:action?p1=p1&p0=p0&o=%&u=u',
            [
                'x-jdcloud-date' => '20190214T104514Z',
                'x-jdcloud-nonce' => 'testnonce',
                'x-my-header' => 'test',
                'x-my-header_blank' => ' blank'
            ], 'body data'
            );
        $signedRequest = $signature->signRequest($request, $credentials);
        $client = new Client();
        
        try{
            $response = $client->send($signedRequest, [
                'timeout' => 20,
            ]);
            $body = $response->getBody();
            
            var_dump($response->getStatusCode());
            var_dump($response->getHeaders());
            
            $stringBody = (string) $body;
            var_dump($stringBody);
        }catch (\RuntimeException $e) {
            var_dump($e->getMessage());
        }
    }
}


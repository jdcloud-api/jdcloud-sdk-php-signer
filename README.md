# 简介 #
  该项目实现京东云API网关签名的生成，适用于API网关产品，也可用于OpenAPI的签名生成。 
  

# 环境准备 #
 1.京东云Php SDK适用于Php 5.5及以上。

 2.如果用于OpenAPI的签名，需要在京东云用户中心账户管理下的[AccessKey管理页面](https://uc.jdcloud.com/accesskey/index)申请accesskey和secretKey密钥对（简称AK/SK）;如果用于API网关的签名，可根据情况使用云用户密钥对或者API网关[签名密钥](https://docs.jdcloud.com/cn/api-gateway/create-auth)。


# SDK使用方法 #
建议使用Composer安装京东云Php签名工具： 

首先在composer.json添加

	"require" : {
		"php" : ">=5.5",
		"jdcloud-api/apigateway-signer" : ">=0.1",
	}
    

然后使用Composer安装

    php composer.phar install

或

    composer install 

您还可以下载sdk源代码自行使用。

 

SDK使用中的任何问题，欢迎您在Github SDK使用问题反馈页面交流。



## 调用示例 ##
demo中包含了简单的调用示例。

	    $credentials = new Credentials('ak', 'sk');
	    $signature = new SignatureV4('testApiGroup', 'cn-north-1');
        $request = new Request('POST', 'http://xqokj9u7k483.cn-north-1.jdcloud-api.net/iampost?param=param',
            [
                'x-my-header' => 'test'
            ], 
            'body data'
            );
 	    $signedRequest = $signature->signRequest($request, $credentials);
	    $client = new Client();
	    $response = $client->send($signedRequest, [
                'timeout' => 20,
	    ]);

### 注意事项
 - 调用签名工具前，需要对请求和路径参数进行urlencode
 - 请求中需要传递签名工具自动添加的几个x-jdcloud-xxx的请求头

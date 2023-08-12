<?php

declare(strict_types=1);

namespace Behatch\Context\Extra;

use Behat\Behat\Context\Context;
use Behat\Gherkin\Node\PyStringNode;
use Behat\Gherkin\Node\TableNode;
use Firebase\JWT\JWT;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\RequestException;
use PHPUnit_Framework_Assert as Assertions;

class JwtApiContext implements Context
{

    /**
     * @var string
     */
    protected $authorization;

    /**
     * @var ClientInterface
     */
    protected $client;

    /**
     * @var array
     */
    protected $headers = array();

    /**
     * @var \GuzzleHttp\Message\RequestInterface
     */
    protected $request;

    /**
     * @var \GuzzleHttp\Message\ResponseInterface
     */
    protected $response;

    protected $placeHolders = array();
    
    /**
     * @var array
     */
    protected $config = [
        'base_url' => 'http://mockserver.test/',
        'secret_key' => '-----BEGIN PRIVATE KEY-----
MIICeAIBADANBgkqhkiG9w0BAQEFAASCAmIwggJeAgEAAoGBANZmfQIhbKriV3xo
Mqn4/fK5A95cart2S1/fSYJ2PPfW0oQnYqXQa173RPc2Q3l81IIzaOK1uddsT7uh
3LWmcYzmSjxi+0T5nHVbdvczu4pQ80l39l9//jui5pI2It+K7aLl5UxzpsChO4Hv
ElUqujrtKadOkDTRv5+G1ME0r3bvAgMBAAECgYEAj6l5FlZjRFYKHTiMykwnjd7f
cr4mXpqzgvhRf3PPApsV0Ku7dDQl3ip+MdBQgjVdCCl+nHr8nhbbjnS1OZrf9jnZ
nkBEpWHrP89GsDNksOCq3zCmmZ+5R5jFfsT/VBdUhGmysLJiJROTR5py//3O3D7U
lIpqRQvXf+fvdLAaxyECQQDqVMxbcDZjAaAjynr6d39EudQBEfhEbJBYzpclCMlI
PSelxdpRyhKq1wS/O44s6Bu6/2p9LZ1qLEM3g5t9nRxnAkEA6jngUcWTiMj4QArJ
muH2/HB9qeD/Rx4zaOQKuOBkIM3pIATNZ8/hhVfj59Qa0+azL/ffqsniC7u53by7
/hQ8OQJAGUd4nEyosVmViwbm6WpGwoVBh7QGkmsbz1jKGWavQCnIwytq9/PSu7di
fbbRCasogq3XMRXgq3mG7tA10AFI9QJBAJdyOqfEz4MnJtUJ5Jc/uho5hhc8gvLy
BR2yLXiipjtLyIvKbyHLmS9Fx/fS/lG7HmtKo5VjmcQqaqCD8y3y2YkCQQDKYs6b
WASHoZznKraL4iX8Mp+f4i4htJYayvjQ+UyVRlLRSnMbk88Sylcrhwb3KOn+n4XA
axeuKY2038HpZeqr
-----END PRIVATE KEY-----',
        'header_name' => 'X-Access-Token',
        'encoded_field_name' => 'name',
        'token_prefix' => '',
        'ttl' => 86400,
    ];

//    /**
//     * Initializes initializer.
//     *
//     * @param ClientInterface $client
//     * @param array $config
//     */
//    public function __construct(ClientInterface $client, $config)
//    {
//        $this->client = $client;
//        $this->config = $config;
//    }

    public function setConfig(array $config)
    {
        $this->config = $config;
    }

    /**
     * Adds JWT Authentication header in the request.
     *
     * @param string $username
     *
     * @Then /^I am authenticating with jwt token as "([^"]*)"$/
     */
    public function iAmAuthenticatingWithJWT($username)
    {
        $this->removeHeader('Authorization');

        $key = JWT::encode(
            [
                $this->config['encoded_field_name'] => $username,
                'exp' => time()+$this->config['ttl']
            ]
            , $this->config['secret_key'],
            'ES384'
        );

        $this->addHeader($this->config['header_name'], $this->config['token_prefix'].$key);
    }

    /**
     * Validate Jwt token
     *
     * @param string $token_field_name
     *
     * @Then /^(?:the )?response should contain jwt token in field "([^"]*)"$/
     */
    public function responseShouldContainJwtToken($token_field_name)
    {
        $response = $this->response->json();

        Assertions::assertArrayHasKey($token_field_name, $response);
        $tks = explode('.', $response[$token_field_name]);
        Assertions::assertEquals(3 , count($tks));

        list($headb64, $bodyb64, $cryptob64) = $tks;

        $sig = \JWT::urlsafeB64Decode($cryptob64);
        $header = \JWT::jsonDecode(\JWT::urlsafeB64Decode($headb64));

        Assertions::assertTrue(\JWT::verify("$headb64.$bodyb64", $sig, $this->config['secret_key'], $header->alg));
    }

    /**
     * Validate Jwt token data
     *
     * @param string $token_field_name
     * @param PyStringNode $jsonString
     *
     * @Then /^(?:the )?response should contain jwt token in field "([^"]*)" with data:$/
     */
    public function responseShouldContainJwtTokenInFieldWithData($token_field_name, PyStringNode $jsonString)
    {
        $expected = json_decode($this->replacePlaceHolder($jsonString->getRaw()), true);

        $response = $this->response->json();
        Assertions::assertArrayHasKey($token_field_name, $response);

        $actual = \JWT::decode($response[$token_field_name], $this->config['secret_key']);

        foreach ($expected as $key => $needle) {
            Assertions::assertObjectHasAttribute($key, $actual);
            Assertions::assertEquals($expected[$key], $actual->{$key});
        }
    }

    /**
     * {@inheritdoc}
     */
    public function setClient(ClientInterface $client)
    {
        $this->client = $client;
    }

    /**
     * Adds Basic Authentication header to next request.
     *
     * @param string $username
     * @param string $password
     *
     * @Given /^I am authenticating as "([^"]*)" with "([^"]*)" password$/
     */
    public function iAmAuthenticatingAs($username, $password)
    {
        $this->removeHeader('Authorization');
        $this->authorization = base64_encode($username . ':' . $password);
        $this->addHeader('Authorization', 'Basic ' . $this->authorization);
    }

    /**
     * Sets a HTTP Header.
     *
     * @param string $name  header name
     * @param string $value header value
     *
     * @Given /^DISABLEDI set header "([^"]*)" with value "([^"]*)"$/
     */
    public function iSetHeaderWithValue($name, $value)
    {
        $this->addHeader($name, $value);
    }

    /**
     * Sends HTTP request to specific relative URL.
     *
     * @param string $method request method
     * @param string $url    relative url
     *
     * @When /^DISABLED (?:I )?send a ([A-Z]+) request to "([^"]+)"$/
     */
    public function iSendARequest($method, $url)
    {
        $url = $this->prepareUrl($url);
        $this->request = $this->getClient()->createRequest($method, $url);
        if (!empty($this->headers)) {
            $this->request->addHeaders($this->headers);
        }

        $this->sendRequest();
    }

    /**
     * Sends HTTP request to specific URL with field values from Table.
     *
     * @param string    $method request method
     * @param string    $url    relative url
     * @param TableNode $post   table of post values
     *
     * @When /^DISABLED(?:I )?send a ([A-Z]+) request to "([^"]+)" with values:$/
     */
    public function iSendARequestWithValues($method, $url, TableNode $post)
    {
        $url = $this->prepareUrl($url);
        $fields = array();

        foreach ($post->getRowsHash() as $key => $val) {
            $fields[$key] = $this->replacePlaceHolder($val);
        }

        $bodyOption = array(
            'body' => json_encode($fields),
        );
        $this->request = $this->getClient()->createRequest($method, $url, $bodyOption);
        if (!empty($this->headers)) {
            $this->request->addHeaders($this->headers);
        }

        $this->sendRequest();
    }

    /**
     * Sends HTTP request to specific URL with raw body from PyString.
     *
     * @param string       $method request method
     * @param string       $url    relative url
     * @param PyStringNode $string request body
     *
     * @When /^DISABLED(?:I )?send a ([A-Z]+) request to "([^"]+)" with body:$/
     */
    public function iSendARequestWithBody($method, $url, PyStringNode $string)
    {
        $url = $this->prepareUrl($url);
        $string = $this->replacePlaceHolder(trim($string));

        $this->request = $this->getClient()->createRequest(
            $method,
            $url,
            array(
                'headers' => $this->getHeaders(),
                'body' => $string,
            )
        );
        $this->sendRequest();
    }

    /**
     * Sends HTTP request to specific URL with form data from PyString.
     *
     * @param string       $method request method
     * @param string       $url    relative url
     * @param PyStringNode $body   request body
     *
     * @When /^DISABLED(?:I )?send a ([A-Z]+) request to "([^"]+)" with form data:$/
     */
    public function iSendARequestWithFormData($method, $url, PyStringNode $body)
    {
        $url = $this->prepareUrl($url);
        $body = $this->replacePlaceHolder(trim($body));

        $fields = array();
        parse_str(implode('&', explode("\n", $body)), $fields);
        $this->request = $this->getClient()->createRequest($method, $url);
        /** @var \GuzzleHttp\Post\PostBodyInterface $requestBody */
        $requestBody = $this->request->getBody();
        foreach ($fields as $key => $value) {
            $requestBody->setField($key, $value);
        }

        $this->sendRequest();
    }

    /**
     * Checks that response has specific status code.
     *
     * @param string $code status code
     *
     * @Then /^DISABLED(?:the )?response code should be (\d+)$/
     */
    public function theResponseCodeShouldBe($code)
    {
        $expected = intval($code);
        $actual = intval($this->response->getStatusCode());
        Assertions::assertSame($expected, $actual);
    }

    /**
     * Checks that response body contains specific text.
     *
     * @param string $text
     *
     * @Then /^DISABLED(?:the )?response should contain "([^"]*)"$/
     */
    public function theResponseShouldContain($text)
    {
        $expectedRegexp = '/' . preg_quote($text) . '/i';
        $actual = (string) $this->response->getBody();
        Assertions::assertRegExp($expectedRegexp, $actual);
    }

    /**
     * Checks that response body doesn't contains specific text.
     *
     * @param string $text
     *
     * @Then /^DISABLED(?:the )?response should not contain "([^"]*)"$/
     */
    public function theResponseShouldNotContain($text)
    {
        $expectedRegexp = '/' . preg_quote($text) . '/';
        $actual = (string) $this->response->getBody();
        Assertions::assertNotRegExp($expectedRegexp, $actual);
    }

    /**
     * Checks that response body contains JSON from PyString.
     *
     * Do not check that the response body /only/ contains the JSON from PyString,
     *
     * @param PyStringNode $jsonString
     *
     * @throws \RuntimeException
     *
     * @Then /^DISABLED(?:the )?response should contain json:$/
     */
    public function theResponseShouldContainJson(PyStringNode $jsonString)
    {
        $etalon = json_decode($this->replacePlaceHolder($jsonString->getRaw()), true);
        $actual = $this->response->json();

        if (null === $etalon) {
            throw new \RuntimeException(
                "Can not convert etalon to json:\n" . $this->replacePlaceHolder($jsonString->getRaw())
            );
        }

        Assertions::assertGreaterThanOrEqual(count($etalon), count($actual));
        foreach ($etalon as $key => $needle) {
            Assertions::assertArrayHasKey($key, $actual);
            Assertions::assertEquals($etalon[$key], $actual[$key]);
        }
    }

    /**
     * Prints last response body.
     *
     * @Then print response
     */
    public function printResponse()
    {
        $request = $this->request;
        $response = $this->response;

        echo sprintf(
            "%s %s => %d:\n%s",
            $request->getMethod(),
            $request->getUrl(),
            $response->getStatusCode(),
            $response->getBody()
        );
    }

    /**
     * Prepare URL by replacing placeholders and trimming slashes.
     *
     * @param string $url
     *
     * @return string
     */
    protected function prepareUrl($url)
    {
        return ltrim($this->replacePlaceHolder($url), '/');
    }

    /**
     * Sets place holder for replacement.
     *
     * you can specify placeholders, which will
     * be replaced in URL, request or response body.
     *
     * @param string $key   token name
     * @param string $value replace value
     */
    public function setPlaceHolder($key, $value)
    {
        $this->placeHolders[$key] = $value;
    }

    /**
     * Replaces placeholders in provided text.
     *
     * @param string $string
     *
     * @return string
     */
    protected function replacePlaceHolder($string)
    {
        foreach ($this->placeHolders as $key => $val) {
            $string = str_replace($key, $val, $string);
        }

        return $string;
    }

    /**
     * Returns headers, that will be used to send requests.
     *
     * @return array
     */
    protected function getHeaders()
    {
        return $this->headers;
    }

    /**
     * Adds header
     *
     * @param string $name
     * @param string $value
     */
    protected function addHeader($name, $value)
    {
        if (isset($this->headers[$name])) {
            if (!is_array($this->headers[$name])) {
                $this->headers[$name] = array($this->headers[$name]);
            }

            $this->headers[$name][] = $value;
        } else {
            $this->headers[$name] = $value;
        }
    }

    /**
     * Removes a header identified by $headerName
     *
     * @param string $headerName
     */
    protected function removeHeader($headerName)
    {
        if (array_key_exists($headerName, $this->headers)) {
            unset($this->headers[$headerName]);
        }
    }

    protected function sendRequest()
    {
        try {
            $this->response = $this->getClient()->send($this->request);
        } catch (RequestException $e) {
            $this->response = $e->getResponse();

            if (null === $this->response) {
                throw $e;
            }
        }
    }

    protected function getClient()
    {
        if (null === $this->client) {
            throw new \RuntimeException('Client has not been set in WebApiContext');
        }

        return $this->client;
    }
}
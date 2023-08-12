<?php

namespace Behatch\Context\Extra;

use App\Entity\User;
use Behat\Behat\Context\Context;
use Behat\Behat\Hook\Scope\BeforeScenarioScope;
use Behat\Gherkin\Node\PyStringNode;
use Behatch\Context\JsonContext;
use Behatch\Context\RestContext;
use Doctrine\Bundle\DoctrineBundle\Registry;
use Doctrine\Persistence\ObjectManager;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTManager;
use \PHPUnit\Framework\Assert as Assertions;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

class JWTAuthContext implements Context
{
    private ObjectManager $manager;
    private JWTManager $jwtManager;
    private RestContext $restContext;
    private JsonContext $JsonContext;
    private TokenStorageInterface $tokenStorage;
    /**
     * @var User|mixed|object
     */
    private mixed $user;

    protected $placeHolders = array();

    /** @BeforeScenario */
    public function gatherContexts(BeforeScenarioScope $scope)
    {
        $this->restContext = $scope->getEnvironment()->getContext(RestContext::class);
        $this->JsonContext = $scope->getEnvironment()->getContext(JsonContext::class);
    }

    /**
     * @param string|null $fixturesBasePath
     */
    public function __construct(
        Registry        $registry,
        JWTManager $jwtManager,
        TokenStorageInterface $tokenStorage
    ) {
        $this->manager = $registry->getManager();
        $this->jwtManager = $jwtManager;
        $this->tokenStorage = $tokenStorage;
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
        //$user = $this->manager->getRepository(User::class)->findOneBy(['username' => $username]);
        $users = $this->manager
            ->getRepository(User::class)
            ->findAll();
        $this->user = $users[0];
        $this->token = $this->jwtManager->create($this->user);
        $this->restContext->iAddHeaderEqualTo('Authorization', "Bearer $this->token");
    }

    /**
     * Validate Jwt token
     *
     * @param string $token_field_name
     *
     * @Then /^(?:the )?response should contain jwt token in field "([^"]*)"$/
     */
    public function responseShouldContainJwtToken(string $token_field_name)
    {
        $response = $this->JsonContext->getJson();
        $response = ((array)$response->getContent());

        Assertions::assertArrayHasKey($token_field_name, $response);
    }

    /**
     * Validate Jwt token data
     *
     * @param string $token_field_name
     * @param PyStringNode $jsonString
     *
     * @Then /^(?:the )?response should contain jwt token in field "([^"]*)" with data:$/
     */
    public function responseShouldContainJwtTokenInFieldWithData(string $token_field_name, PyStringNode $jsonString)
    {
        $this->responseShouldContainJwtToken($token_field_name);
        $string = $jsonString->getRaw();
        $replacePlaceHolder = $this->replacePlaceHolder($string);
        $expected = json_decode($replacePlaceHolder, true);

        $response = $this->JsonContext->getJson();
        $response = ((array)$response->getContent());
        Assertions::assertArrayHasKey($token_field_name, $response);
        $actual = $this->jwtManager->parse($response['token']);

        foreach ($expected as $key => $needle) {
            Assertions::assertArrayHasKey($key, $expected);
            Assertions::assertArrayHasKey($key, $actual);
            Assertions::assertEquals($expected[$key], $actual[$key]);
        }
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
     * BeforeScenario
     * @login with user :user
     *
     * @see https://symfony.com/doc/current/security/entity_provider.html#creating-your-first-user
     */
    public function login(BeforeScenarioScope $scope)
    {
        $user = new User();
        $user->setUsername('admin');
        $user->setPassword('ATestPassword');
        $user->setEmail('test@test.com');
        $this->manager->persist($user);
        $this->manager->flush();
        $token = $this->jwtManager->create($user);
        $this->restContext = $scope->getEnvironment()->getContext(RestContext::class);
        $this->restContext->iAddHeaderEqualTo('Authorization', "Bearer $token");
    }
    /**
     * @AfterScenario
     * @logout
     */
    public function logout() {
        $this->restContext->iAddHeaderEqualTo('Authorization', '');
    }
}
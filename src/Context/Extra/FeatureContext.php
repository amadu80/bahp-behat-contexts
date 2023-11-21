<?php

declare(strict_types=1);

namespace Behatch\Context\Extra;

use Behat\Behat\Context\Context;
use Behat\Behat\Hook\Scope\AfterScenarioScope;
use Behat\Behat\Hook\Scope\BeforeScenarioScope;
use Behat\Behat\Tester\Exception\PendingException;
use Behat\Gherkin\Node\PyStringNode;
use Behat\Mink\Exception\ResponseTextException;
use Doctrine\DBAL\Connection;

/**
 * Defines application features from the specific context.
 */
class FeatureContext implements Context
{
    /**
     * @var Connection
     */
    private $connection;

    /**
     * @param Connection $connection
     */
    public function __construct_disabled(Connection $connection)
    {
        $this->connection = $connection;
    }

    /**
     * @BeforeScenario
     */
    public function setUp(BeforeScenarioScope $scope)
    {
        //$this->connection->beginTransaction();
    }

    /**
     * @AfterScenario
     */
    public function tearDown(AfterScenarioScope $scope)
    {
        //$this->connection->rollBack();
    }

    public function spin($lambda, $wait = 60)
    {
        for ($i = 0; $i < $wait; $i++) {
            try {
                if ($lambda($this)) {
                    return true;
                }
            } catch (\Exception $e) {
                // do nothing
            }

            sleep(1);
        }

        $backtrace = debug_backtrace();

        throw new \Exception(
            "Timeout thrown by " . $backtrace[1]['class'] . "::" . $backtrace[1]['function'] . "()\n" .
            $backtrace[1]['file'] . ", line " . $backtrace[1]['line']
        );
    }

    /**
     * @When /^I hover over the element "([^"]*)"$/
     */
    public function iHoverOverTheElement($locator)
    {
        $session = $this->getSession(); // get the mink session
        $element = $session->getPage()->find('css', $locator); // runs the actual query and returns the element

        // errors must not pass silently
        if (null === $element) {
            throw new \InvalidArgumentException(sprintf('Could not evaluate CSS selector: "%s"', $locator));
        }

        // ok, let's hover it
        $element->mouseOver();
    }

    /**
     * @When /^wait for the element "([^"]*)"$/
     */
    public function waitForTheElementToAppearToBeLoaded()
    {
        $this->getSession()->wait(10000, "document.readyState === 'complete'");
    }

    /**
     * @When I wait for element :element to appear
     * @Then I should see element :element appear
     * @param $selector
     * @throws \Exception
     */
    public function iWaitForElementToAppear($selector)
    {
        $this->spin(function (\Foodity\Behat\FeatureContext $context) use ($selector) {
            try {
                $context->assertElementOnPage($selector);
                return true;
            } catch (ResponseTextException $e) {
                // NOOP
            }
            return false;
        });
    }

    /**
     * @When I wait for text :text to appear
     * @Then I should see text :text appear
     * @param $text
     * @throws \Exception
     */
    public function iWaitForTextToAppear($text)
    {
        $this->spin(function (FeatureContext $context) use ($text) {
            try {
                $context->assertPageContainsText($text);
                return true;
            } catch (ResponseTextException $e) {
                // NOOP
            }
            return false;
        });
    }

    /**
     * @When I wait for text :text to disappear
     * @Then I should see text :text disappear
     * @param $text
     * @throws \Exception
     */
    public function iWaitForTextToDisappear($text)
    {
        $this->spin(function (FeatureContext $context) use ($text) {
            try {
                $context->assertPageContainsText($text);
            } catch (ResponseTextException $e) {
                return true;
            }
            return false;
        });
    }

    /**
     * @Then Show page content for debug
     */
    public function showPageContentForDebug()
    {
        echo $this->getSession()->getPage()->getText();
    }

    /**
     * @Given User with id :id never login
     */
    public function userWithIdNeverLogin($id)
    {
        $this->connection->exec(sprintf('UPDATE user_account SET last_login = NULL WHERE id = %s', $id));
    }

    /** @Given show todo message: */
    public function todoMessage(PyStringNode $text)
    {
        throw new PendingException(sprintf('Todo: "%s"', $text));
    }
}

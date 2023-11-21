<?php

namespace Behatch\Context\Extra;

use Behat\Behat\Context\Context;
use Behat\MinkExtension\Context\MinkContext;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpKernel\Profiler\Profiler;

/**
 * Defines application features from the specific context.
 */
class ProfilerContext implements Context
{
    /**
     * @var Profiler
     */
    private $profiler;

    /**
     * @var MinkContext
     */
    private $mink;

    /**
     * ProfilerContext constructor.
     * @param Profiler $profiler
     * @param MinkContext $mink
     */
    public function __construct(Profiler $profiler)
    {
        $this->profiler = $profiler;
        #$this->mink = $mink;
    }

    /**
     * @param string $email
     * @Then I receive an activation email :email
     */
    public function iReceiveAnActivationEmail(string $emailAddress)
    {
        $profile = $this->profiler;
        $mailCollector = $profile->get('mailer');

        assertGreaterThanOrEqual(1, $mailCollector->getMessageCount());
        $collectedMessages = $mailCollector->getMessages();
        assertCount(1, $collectedMessages);
        $message = $collectedMessages[0];

        assertInstanceOf('Swift_Message', $message);
        assertEquals($subject, trim($message->getSubject()));
        $messageFrom = $message->getFrom();
        $fromEmailVal = key($messageFrom);
        assertEquals($FromEmail, $fromEmailVal);
        assertEquals($fromName, $messageFrom[$fromEmailVal]);
        assertEquals('scm-test@constant.co', trim(key($message->getTo())));
        assertRegExp($bodyPattern->getRaw(), trim($message->getBody()));


        // Load the debug profile
        // Must be a POST request within the last 30 seconds
        $start = date('Y-m-d H:i:s', time() - 10);
        $end = date('Y-m-d H:i:s');

        $tokens = $this->profiler->find('','',1, 'POST', $start, $end);
        $profiler = $this->profiler->loadProfile($tokens[0]['token']);

        // Get the swiftmail collector
        $collector = $profiler->getCollector('swiftmailer');

        // Get all the available messages
        $messages = $collector->getMessages();

        // Check we have only have one message
        WebTestCase::assertEquals(1, count($messages));

        // Get the message
        $message = reset($messages);

        // Email is in the X-Swift-To header in test mode as we are not allowing email
        // delivery in test mode and dev is setting single swiftmailer delivery_address option
        WebTestCase::assertEquals($emailAddress, reset(array_keys($message->getHeaders()->get('X-Swift-To')->getFieldBodyModel())));
        WebTestCase::assertContains('Activate Your Account', $message->getSubject());
        WebTestCase::assertRegExp('/<a href="(.*?)".*?>Activate my account<\/a>/', $message->getBody());
    }
}

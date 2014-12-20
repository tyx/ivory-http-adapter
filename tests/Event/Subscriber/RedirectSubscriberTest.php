<?php

/*
 * This file is part of the Ivory Http Adapter package.
 *
 * (c) Eric GELOEN <geloen.eric@gmail.com>
 *
 * For the full copyright and license information, please read the LICENSE
 * file that was distributed with this source code.
 */

namespace Ivory\Tests\HttpAdapter\Event\Subscriber;

use Ivory\HttpAdapter\Event\Events;
use Ivory\HttpAdapter\Event\Subscriber\RedirectSubscriber;

/**
 * Redirect subscriber test.
 *
 * @author GeLo <geloen.eric@gmail.com>
 */
class RedirectSubscriberTest extends AbstractSubscriberTest
{
    /** @var \Ivory\HttpAdapter\Event\Subscriber\RedirectSubscriber */
    private $redirectSubscriber;

    /** @var \Ivory\HttpAdapter\Event\Redirect\RedirectInterface|\PHPUnit_Framework_MockObject_MockObject */
    private $redirect;

    /**
     * {@inheritdoc}
     */
    protected function setUp()
    {
        $this->redirectSubscriber = new RedirectSubscriber(
            $this->redirect = $this->getMock('Ivory\HttpAdapter\Event\Redirect\RedirectInterface')
        );
    }

    /**
     * {@inheritdoc}
     */
    protected function tearDown()
    {
        unset($this->redirect);
        unset($this->redirectSubscriber);
    }

    public function testDefaultState()
    {
        $this->redirectSubscriber = new RedirectSubscriber();

        $this->assertInstanceOf('Ivory\HttpAdapter\Event\Redirect\Redirect', $this->redirectSubscriber->getRedirect());
    }

    public function testInitialState()
    {
        $this->assertSame($this->redirect, $this->redirectSubscriber->getRedirect());
    }

    public function testSubscribedEvents()
    {
        $events = RedirectSubscriber::getSubscribedEvents();

        $this->assertArrayHasKey(Events::POST_SEND, $events);
        $this->assertSame(array('onPostSend', 0), $events[Events::POST_SEND]);
    }

    public function testPostSendEventWithRedirectResponse()
    {
        $httpAdapter = $this->createHttpAdapterMock();
        $httpAdapter
            ->expects($this->once())
            ->method('sendRequest')
            ->with($this->identicalTo($redirectRequest = $this->createRequestMock()))
            ->will($this->returnValue($redirectResponse = $this->createResponseMock()));

        $this->redirect
            ->expects($this->once())
            ->method('createRedirectRequest')
            ->with(
                $this->identicalTo($response = $this->createResponseMock()),
                $this->identicalTo($request = $this->createRequestMock()),
                $this->identicalTo($httpAdapter)
            )
            ->will($this->returnValue($redirectRequest));

        $this->redirectSubscriber->onPostSend($event = $this->createPostSendEvent($httpAdapter, $request, $response));

        $this->assertSame($redirectResponse, $event->getResponse());
    }

    public function testPostSendEventWithRedirectResponseThrowException()
    {
        $httpAdapter = $this->createHttpAdapterMock();
        $httpAdapter
            ->expects($this->once())
            ->method('sendRequest')
            ->with($this->identicalTo($redirectRequest = $this->createRequestMock()))
            ->will($this->throwException($exception = $this->createExceptionMock()));

        $this->redirect
            ->expects($this->once())
            ->method('createRedirectRequest')
            ->with(
                $this->identicalTo($response = $this->createResponseMock()),
                $this->identicalTo($request = $this->createRequestMock()),
                $this->identicalTo($httpAdapter)
            )
            ->will($this->returnValue($redirectRequest));

        $this->redirectSubscriber->onPostSend($event = $this->createPostSendEvent($httpAdapter, $request, $response));

        $this->assertTrue($event->hasException());
        $this->assertSame($exception, $event->getException());
    }

    public function testPostSendEventWithoutRedirectResponse()
    {
        $this->redirect
            ->expects($this->once())
            ->method('createRedirectRequest')
            ->with(
                $this->identicalTo($response = $this->createResponseMock()),
                $this->identicalTo($request = $this->createRequestMock()),
                $this->identicalTo($httpAdapter = $this->createHttpAdapterMock())
            )
            ->will($this->returnValue(false));

        $this->redirect
            ->expects($this->once())
            ->method('prepareResponse')
            ->with($this->identicalTo($response), $this->identicalTo($request));

        $this->redirectSubscriber->onPostSend($this->createPostSendEvent($httpAdapter, $request, $response));
    }

    public function testPostSendEventWithMaxRedirectReachedThrowException()
    {
        $this->redirect
            ->expects($this->once())
            ->method('createRedirectRequest')
            ->with(
                $this->identicalTo($response = $this->createResponseMock()),
                $this->identicalTo($request = $this->createRequestMock()),
                $this->identicalTo($httpAdapter = $this->createHttpAdapterMock())
            )
            ->will($this->throwException($exception = $this->createExceptionMock()));

        $this->redirectSubscriber->onPostSend($event = $this->createPostSendEvent($httpAdapter, $request, $response));

        $this->assertSame($exception, $event->getException());
    }

    /**
     * {@inheritdoc}
     */
    protected function createConfigurationMock()
    {
        $configuration = parent::createConfigurationMock();
        $configuration
            ->expects($this->any())
            ->method('getMessageFactory')
            ->will($this->returnValue($this->getMock('Ivory\HttpAdapter\Message\MessageFactoryInterface')));

        return $configuration;
    }
}

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
use Ivory\HttpAdapter\Message\InternalRequestInterface;

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

        $this->assertArrayHasKey(Events::REQUEST_SENT, $events);
        $this->assertSame(array('onRequestSent', 0), $events[Events::REQUEST_SENT]);

        $this->assertArrayHasKey(Events::MULTI_REQUEST_SENT, $events);
        $this->assertSame(array('onMultiRequestSent', 0), $events[Events::MULTI_REQUEST_SENT]);
    }

    public function testRequestSentEventWithRedirectResponse()
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

        $this->redirectSubscriber->onRequestSent($event = $this->createRequestSentEvent($httpAdapter, $request, $response));

        $this->assertSame($redirectResponse, $event->getResponse());
    }

    public function testRequestSentEventWithRedirectResponseThrowException()
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

        $this->redirectSubscriber->onRequestSent($event = $this->createRequestSentEvent($httpAdapter, $request, $response));

        $this->assertTrue($event->hasException());
        $this->assertSame($exception, $event->getException());
    }

    public function testRequestSentEventWithoutRedirectResponse()
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
            ->with($this->identicalTo($response), $this->identicalTo($request))
            ->will($this->returnValue($preparedResponse = $this->createResponseMock()));

        $this->redirectSubscriber->onRequestSent($event = $this->createRequestSentEvent($httpAdapter, $request, $response));

        $this->assertSame($preparedResponse, $event->getResponse());
    }

    public function testRequestSentEventWithMaxRedirectReachedThrowException()
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

        $this->redirectSubscriber->onRequestSent($event = $this->createRequestSentEvent($httpAdapter, $request, $response));

        $this->assertSame($exception, $event->getException());
    }

    public function testMultiRequestSentEventWithRedirectResponse()
    {
        $responses = array(
            $response1 = $this->createResponseMock($request1 = $this->createRequestMock()),
            $response2 = $this->createResponseMock($request2 = $this->createRequestMock()),
        );

        $redirectRequests = array(
            $redirectRequest1 = $this->createRequestMock(),
            $redirectRequest2 = $this->createRequestMock(),
        );

        $redirectResponses = array($this->createResponseMock(), $this->createResponseMock());

        $httpAdapter = $this->createHttpAdapterMock();
        $httpAdapter
            ->expects($this->once())
            ->method('sendRequests')
            ->with($this->identicalTo($redirectRequests))
            ->will($this->returnValue($redirectResponses));

        $this->redirect
            ->expects($this->exactly(count($responses)))
            ->method('createRedirectRequest')
            ->will($this->returnValueMap(array(
                array($response1, $request1, $httpAdapter, $redirectRequest1),
                array($response2, $request2, $httpAdapter, $redirectRequest2),
            )));

        $this->redirectSubscriber->onMultiRequestSent($event = $this->createMultiRequestSentEvent($httpAdapter, $responses));

        $this->assertSame($redirectResponses, $event->getResponses());
        $this->assertFalse($event->hasExceptions());
    }

    public function testMultiRequestSentEventWithRedirectResponseThrowException()
    {
        $responses = array(
            $response1 = $this->createResponseMock($request1 = $this->createRequestMock()),
            $response2 = $this->createResponseMock($request2 = $this->createRequestMock()),
        );

        $redirectRequests = array(
            $redirectRequest1 = $this->createRequestMock(),
            $redirectRequest2 = $this->createRequestMock(),
        );

        $exceptions = array($this->createExceptionMock(), $this->createExceptionMock());

        $httpAdapter = $this->createHttpAdapterMock();
        $httpAdapter
            ->expects($this->once())
            ->method('sendRequests')
            ->with($this->identicalTo($redirectRequests))
            ->will($this->throwException($exception = $this->createMultiExceptionMock($exceptions)));

        $this->redirect
            ->expects($this->exactly(count($responses)))
            ->method('createRedirectRequest')
            ->will($this->returnValueMap(array(
                array($response1, $request1, $httpAdapter, $redirectRequest1),
                array($response2, $request2, $httpAdapter, $redirectRequest2),
            )));

        $this->redirectSubscriber->onMultiRequestSent($event = $this->createMultiRequestSentEvent($httpAdapter, $responses));

        $this->assertFalse($event->hasResponses());
        $this->assertTrue($event->hasExceptions());
        $this->assertSame($exceptions, $event->getExceptions());
    }

    public function testMultiRequestSentEventWithoutRedirectResponse()
    {
        $httpAdapter = $this->createHttpAdapterMock();
        $responses = array(
            $response1 = $this->createResponseMock($request1 = $this->createRequestMock()),
            $response2 = $this->createResponseMock($request2 = $this->createRequestMock()),
        );

        $this->redirect
            ->expects($this->exactly(count($responses)))
            ->method('createRedirectRequest')
            ->will($this->returnValueMap(array(
                array($response1, $request1, $httpAdapter, false),
                array($response2, $request2, $httpAdapter, false),
            )));

        $this->redirect
            ->expects($this->exactly(count($responses)))
            ->method('prepareResponse')
            ->will($this->returnValueMap(array(
                array($response1, $request1, $preparedResponse1 = $this->createResponseMock()),
                array($response2, $request2, $preparedResponse2 = $this->createResponseMock()),
            )));

        $this->redirectSubscriber->onMultiRequestSent($event = $this->createMultiRequestSentEvent($httpAdapter, $responses));

        $this->assertFalse($event->hasExceptions());
        $this->assertTrue($event->hasResponses());
        $this->assertSame(array($preparedResponse1, $preparedResponse2), $event->getResponses());
    }

    public function testMultiRequestSentEventWithMaxRedirectReachedThrowException()
    {
        $httpAdapter = $this->createHttpAdapterMock();
        $responses = array(
            $this->createResponseMock($request1 = $this->createRequestMock()),
            $this->createResponseMock($request2 = $this->createRequestMock()),
        );

        $this->redirect
            ->expects($this->exactly(count($responses)))
            ->method('createRedirectRequest')
            ->will($this->throwException($exception = $this->createExceptionMock()));

        $this->redirectSubscriber->onMultiRequestSent($event = $this->createMultiRequestSentEvent($httpAdapter, $responses));

        $this->assertFalse($event->hasResponses());
        $this->assertTrue($event->hasExceptions());
        $this->assertSame(array($exception, $exception), $event->getExceptions());
    }

    /**
     * {@inheritdoc}
     */
    protected function createResponseMock(InternalRequestInterface $internalRequest = null)
    {
        $response = parent::createResponseMock();
        $response
            ->expects($this->any())
            ->method('getParameter')
            ->with($this->identicalTo('request'))
            ->will($this->returnValue($internalRequest));

        return $response;
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

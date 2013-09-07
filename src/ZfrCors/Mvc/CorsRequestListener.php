<?php
/*
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS
 * "AS IS" AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT
 * LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR
 * A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT
 * OWNER OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL,
 * SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT
 * LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE,
 * DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY
 * THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
 * (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE
 * OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
 *
 * This software consists of voluntary contributions made by many individuals
 * and is licensed under the MIT license.
 */

namespace ZfrCors\Mvc;

use Zend\EventManager\AbstractListenerAggregate;
use Zend\EventManager\EventManagerInterface;
use Zend\Http\Response as HttpResponse;
use Zend\Http\Request as HttpRequest;
use Zend\Mvc\MvcEvent;
use ZfrCors\Exception\DisallowedOriginException;
use ZfrCors\Service\CorsService;

/**
 * CorsRequestListener
 *
 * @license MIT
 * @author  Florent Blaison <florent.blaison@gmail.com>
 */
class CorsRequestListener extends AbstractListenerAggregate
{
    /**
     * @var CorsService
     */
    protected $corsService;

    /**
     * @param CorsService $corsService
     */
    public function __construct(CorsService $corsService)
    {
        $this->corsService = $corsService;
    }

    /**
     * {@inheritDoc}
     */
    public function attach(EventManagerInterface $events)
    {
        $this->listeners[] = $events->attach(MvcEvent::EVENT_ROUTE, array($this, 'onCorsRequest'), -10);
    }

    /**
     * Handle a CORS request (either preflight or normal CORS request)
     *
     * @param  MvcEvent $event
     * @return mixed
     */
    public function onCorsRequest(MvcEvent $event)
    {
        /** @var $request HttpRequest */
        $request  = $event->getRequest();
        /** @var $response HttpResponse */
        $response = $event->getResponse();

        if (!$request instanceof HttpRequest || !$this->corsService->isCorsRequest($request)) {
            return;
        }

        // First, the preflight request
        if ($this->corsService->isPreflightRequest($request)) {
            return $this->corsService->createPreflightCorsResponse($request);
        }

        // Otherwise, it is the second step of the CORS request, and we let ZF continue
        // processing the response
        try {
            $response = $this->corsService->populateCorsResponse($request, $response);
            $event->setResponse($response);
        } catch (DisallowedOriginException $exception) {
            $response = new HttpResponse(); // Clear response for security

            $response->setStatusCode(403)
                     ->setReasonPhrase($exception->getMessage());

            return $response;
        }
    }
}
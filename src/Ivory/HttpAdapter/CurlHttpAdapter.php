<?php

/*
 * This file is part of the Ivory Http Adapter package.
 *
 * (c) Eric GELOEN <geloen.eric@gmail.com>
 *
 * For the full copyright and license information, please read the LICENSE
 * file that was distributed with this source code.
 */

namespace Ivory\HttpAdapter;

use Ivory\HttpAdapter\Message\InternalRequestInterface;
use Ivory\HttpAdapter\Message\RequestInterface;
use Ivory\HttpAdapter\Normalizer\BodyNormalizer;
use Ivory\HttpAdapter\Normalizer\HeadersNormalizer;
use Ivory\HttpAdapter\Parser\ProtocolVersionParser;
use Ivory\HttpAdapter\Parser\ReasonPhraseParser;
use Ivory\HttpAdapter\Parser\StatusCodeParser;

/**
 * Curl http adapter.
 *
 * @author GeLo <geloen.eric@gmail.com>
 */
class CurlHttpAdapter extends AbstractCurlHttpAdapter
{
    /**
     * Creates a curl http adapter.
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * {@inheritdoc}
     */
    public function getName()
    {
        return 'curl';
    }

    /**
     * {@inheritdoc}
     */
    protected function doSend(InternalRequestInterface $internalRequest)
    {
        $curl = curl_init();

        curl_setopt($curl, CURLOPT_URL, $internalRequest->getUrl());
        curl_setopt($curl, CURLOPT_HTTP_VERSION, $this->prepareProtocolVersion($internalRequest));
        curl_setopt($curl, CURLOPT_HEADER, true);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);

        curl_setopt(
            $curl,
            CURLOPT_HTTPHEADER,
            $this->prepareHeaders($internalRequest, false, $internalRequest->hasStringDatas())
        );

        if (defined('CURLOPT_TIMEOUT_MS')) {
            curl_setopt($curl, CURLOPT_TIMEOUT_MS, $this->timeout * 1000);
        } else { // @codeCoverageIgnoreStart
            curl_setopt($curl, CURLOPT_TIMEOUT, $this->timeout);
        } // @codeCoverageIgnoreEnd

        curl_setopt($curl, CURLOPT_FOLLOWLOCATION, $this->hasMaxRedirects());

        if ($this->hasMaxRedirects()) {
            curl_setopt($curl, CURLOPT_MAXREDIRS, $this->maxRedirects);
        }

        if ($internalRequest->hasFiles() && $this->isSafeUpload()) {
            curl_setopt($curl, CURLOPT_SAFE_UPLOAD, true);
        }

        switch ($internalRequest->getMethod()) {
            case RequestInterface::METHOD_HEAD:
                curl_setopt($curl, CURLOPT_CUSTOMREQUEST, $internalRequest->getMethod());
                curl_setopt($curl, CURLOPT_NOBODY, true);
                break;

            case RequestInterface::METHOD_POST:
                curl_setopt($curl, CURLOPT_POST, true);
                curl_setopt($curl, CURLOPT_POSTFIELDS, $this->prepareContent($internalRequest));
                break;

            case RequestInterface::METHOD_PUT:
            case RequestInterface::METHOD_PATCH:
            case RequestInterface::METHOD_DELETE:
            case RequestInterface::METHOD_OPTIONS:
                curl_setopt($curl, CURLOPT_CUSTOMREQUEST, $internalRequest->getMethod());
                curl_setopt($curl, CURLOPT_POSTFIELDS, $this->prepareContent($internalRequest));
                break;
        }

        if (($response = curl_exec($curl)) === false) {
            $error = curl_error($curl);
            curl_close($curl);

            throw HttpAdapterException::cannotFetchUrl($internalRequest->getUrl(), $this->getName(), $error);
        }

        $headersSize = curl_getinfo($curl, CURLINFO_HEADER_SIZE);
        $effectiveUrl = curl_getinfo($curl, CURLINFO_EFFECTIVE_URL);

        curl_close($curl);

        $headers = substr($response, 0, $headersSize);
        $body = substr($response, $headersSize);

        return $this->createResponse(
            ProtocolVersionParser::parse($headers),
            StatusCodeParser::parse($headers),
            ReasonPhraseParser::parse($headers),
            HeadersNormalizer::normalize($headers),
            BodyNormalizer::normalize($body, $internalRequest->getMethod()),
            $effectiveUrl
        );
    }
}

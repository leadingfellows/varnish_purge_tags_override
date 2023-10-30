<?php

namespace Drupal\varnish_purge_tags_override\StackMiddleware;

use Drupal\varnish_purge_tags_override\VarnishPurgeTagsOverrideHelperInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\HttpKernelInterface;

class VarnishPurgeTagsOverrideMiddleware implements HttpKernelInterface {
    /**
     * The wrapped HTTP kernel.
     *
     * @var \Symfony\Component\HttpKernel\HttpKernelInterface
     */
    protected $httpKernel;

    /**
     * The varnish_purge_tags_override helper service.
     *
     * @var \Drupal\varnish_purge_tags_override\VarnishPurgeTagsOverrideHelperInterface
     */
    protected $varnishPurgeTagsOverrideHelper;

    /**
     * Constructs a RemoveHttpHeadersMiddleware object.
     *
     * @param \Symfony\Component\HttpKernel\HttpKernelInterface $httpKernel
     *   The decorated kernel.
     * @param \Drupal\varnish_purge_tags_override\VarnishPurgeTagsOverrideHelperInterface $varnishPurgeTagsOverrideHelper
     *   The config manager service.
     */
    public function __construct(HttpKernelInterface $httpKernel, VarnishPurgeTagsOverrideHelperInterface $varnishPurgeTagsOverrideHelper) {
        $this->httpKernel = $httpKernel;
        $this->varnishPurgeTagsOverrideHelper = $varnishPurgeTagsOverrideHelper;
    }

    /**
     * {@inheritdoc}
     */
    public function handle(Request $request, $type = self::MAIN_REQUEST, $catch = TRUE): Response {
        // Only allow page caching on master request.
        $response = $this->httpKernel->handle($request, $type, $catch);
        if ($type === static::MAIN_REQUEST && $this->varnishPurgeTagsOverrideHelper->check($request, $response)) {
            $this->varnishPurgeTagsOverrideHelper->process($response);
        }

        return $response;
    }



}
<?php

namespace Drupal\varnish_purge_tags_override;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

interface VarnishPurgeTagsOverrideHelperInterface {
    /**
     * The default allowed extensions.
     */
    public const DEFAULT_MAXIMUM_HEADER_VALUE_SIZE = 8000;

    /**
     * Return maximum_header_value_size from configuration
     *
     * @return int
     */
    public function getMaximumHeaderValueSize() : int;

    /**
     * @return string|null
     */
    public function getPages() : ?string;


    /**
     * @param Request $request
     * @param Response $response
     * @return bool
     */
    public function check(Request $request, Response $response) : bool;


    /**
     * @param Response $response
     * @return void
     */
    public function process(Response $response);

}
<?php

namespace Drupal\varnish_purge_tags_override;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Path\CurrentPathStack;
use Drupal\Core\Path\PathMatcherInterface;
use Drupal\path_alias\AliasManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;

class VarnishPurgeTagsOverrideHelper implements VarnishPurgeTagsOverrideHelperInterface
{

    /**
     * The config factory service.
     *
     * @var \Drupal\Core\Config\ConfigFactoryInterface
     */
    protected $configFactory;

    /**
     * An alias manager to find the alias for the current system path.
     *
     * @var \Drupal\path_alias\AliasManagerInterface
     */
    protected $aliasManager;

    /**
     * The path matcher.
     *
     * @var \Drupal\Core\Path\PathMatcherInterface
     */
    protected $pathMatcher;

    /**
     * The request stack.
     *
     * @var \Symfony\Component\HttpFoundation\RequestStack
     */
    protected $requestStack;

    /**
     * The current path.
     *
     * @var \Drupal\Core\Path\CurrentPathStack
     */
    protected $currentPath;


    /**
     * FileRepository constructor.
     *
     * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
     *   The config factory service.
     * @param \Drupal\path_alias\AliasManagerInterface $alias_manager
     *   An alias manager to find the alias for the current system path.
     * @param \Drupal\Core\Path\PathMatcherInterface $path_matcher
     *   The path matcher service.
     * @param \Symfony\Component\HttpFoundation\RequestStack $request_stack
     *   The request stack.
     * @param \Drupal\Core\Path\CurrentPathStack $current_path
     *   The current path.
     */
    public function __construct(ConfigFactoryInterface $configFactory, AliasManagerInterface $alias_manager, PathMatcherInterface $path_matcher, RequestStack $request_stack, CurrentPathStack $current_path)
    {
        $this->configFactory = $configFactory;
        $this->aliasManager = $alias_manager;
        $this->pathMatcher = $path_matcher;
        $this->requestStack = $request_stack;
        $this->currentPath = $current_path;
    }

    /**
     * @inheritDoc
     */
    public function getMaximumHeaderValueSize() : int {
        $maximum_header_value_size = $this->configFactory->get('varnish_purge_tags_override.settings')->get('maximum_header_value_size');
        if ($maximum_header_value_size === null || $maximum_header_value_size <= 0) {
            $maximum_header_value_size = self::DEFAULT_MAXIMUM_HEADER_VALUE_SIZE;
        }

        return $maximum_header_value_size;
    }

    /**
     * @inheritDoc
     */
    public function getPages() : ?string {
        return $this->configFactory->get('varnish_purge_tags_override.settings')->get('pages') ?? "";
    }

    /**
     * @inheritDoc
     */
    public function check(Request $request, Response $response) : bool
    {

        if (!$response->headers->has("Cache-Tags")) {
            return FALSE;
        }
        $cacheTagsHeader = $response->headers->get("Cache-Tags") ?? "";
        $maximum_header_value_size = $this->getMaximumHeaderValueSize();
        if (strlen($cacheTagsHeader) < $maximum_header_value_size) {
            return FALSE;
        }

        // Convert path to lowercase. This allows comparison of the same path
        // with different case. Ex: /Page, /page, /PAGE.
        $pages = mb_strtolower($this->getPages() ?? "");
        if (!$pages) {
            return TRUE;
        }
        // Compare the lowercase path alias (if any) and internal path.
        $path = $this->currentPath->getPath($request);
        // Do not trim a trailing slash if that is the complete path.
        $path = $path === '/' ? $path : rtrim($path, '/');
        $path_alias = mb_strtolower($this->aliasManager->getAliasByPath($path));

        return $this->pathMatcher->matchPath($path_alias, $pages) || (($path != $path_alias) && $this->pathMatcher->matchPath($path, $pages));
    }

    /**
     * @inheritDoc
     */
    public function process(Response $response) {
        $cacheTagsHeader = $response->headers->get("Cache-Tags");
        if (!$cacheTagsHeader) {
            return;
        }
        while (str_contains($cacheTagsHeader, "  ")) {
            $cacheTagsHeader = str_replace("  ", " ", $cacheTagsHeader);
        }
        $cacheTagsHeader = trim($cacheTagsHeader);
        $explodedTags = explode(" ", $cacheTagsHeader);
        $keptTags = [];
        $foundEntityTypes = [];
        foreach($explodedTags as $tag) {
            $splittedTag = explode(":", $tag);
            switch($splittedTag[0]) {
                case "node":
                    $foundEntityTypes["node"] = true;
                    break;
                case "paragraph":
                    $foundEntityTypes["paragraph"] = true;
                    break;
                case "taxonomy_term":
                    $foundEntityTypes["taxonomy_term"] = true;
                    break;
                default:
                    $keptTags[$tag] = $tag;
                    break;
            }
        }
        if (count($foundEntityTypes) === 0) {
            return;
        }
        foreach($foundEntityTypes as $entityType => $value) {
            $tag = sprintf("%s_list", $entityType);
            $keptTags[$tag] = $tag;
        }
        $keptTags["config:varnish_purge_tags_override.settings"] = "config:varnish_purge_tags_override.settings";
        $cacheTagsHeader = implode(" ", $keptTags);
        $response->headers->set("Cache-Tags", $cacheTagsHeader);
    }

}
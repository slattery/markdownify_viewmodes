<?php

namespace Drupal\markdownify_viewmodes\EventSubscriber;

use Drupal\Core\Language\LanguageManagerInterface;
//use Drupal\Core\PathAlias\PathAliasRepositoryInterface;
use Drupal\path_alias\AliasRepositoryInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Adds a canonical Link header to Markdown responses.
 */
class MarkdownCanonicalSubscriber implements EventSubscriberInterface {

  protected $pathAliasRepository;
  protected $languageManager;

  public function __construct(
    AliasRepositoryInterface $path_alias_repository,
    LanguageManagerInterface $language_manager
  ) {
    $this->pathAliasRepository = $path_alias_repository;
    $this->languageManager = $language_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    return [
      KernelEvents::RESPONSE => ['onResponse', 10],
    ];
  }

  /**
   * Sets the canonical link header.
   */
  public function onResponse(ResponseEvent $event) {
    $response = $event->getResponse();

    // Check Content-Type.
    $contentType = $response->headers->get('Content-Type', '');
    if (strpos($contentType, 'text/markdown') === false) {
      return;
    }

    $request = $event->getRequest();

    if ($node = $request->attributes->get('node')) {
      $node_id = is_numeric($node) ? $node : $node->id();
      $system_path = '/node/' . $node_id;
      
      $lang_code = $this->languageManager->getCurrentLanguage()->getId();
      $alias_data = $this->pathAliasRepository->lookupBySystemPath($system_path, $lang_code);

      $path = $alias_data ? $alias_data['alias'] : $system_path;
      $canonical_url = $request->getSchemeAndHttpHost() . $path;

      $response->headers->set('Link', '<' . $canonical_url . '>; rel="canonical"');
    }
  }
}
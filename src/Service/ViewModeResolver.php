<?php

declare(strict_types=1);

namespace Drupal\markdownify_viewmodes\Service;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\EntityDisplayRepositoryInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Psr\Log\LoggerInterface;

/**
 * Service for resolving the appropriate view mode for markdownify rendering.
 */
class ViewModeResolver {

  /**
   * The config factory.
   */
  protected ConfigFactoryInterface $configFactory;

  /**
   * The entity type manager.
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * The entity display repository.
   */
  protected EntityDisplayRepositoryInterface $entityDisplayRepository;

  /**
   * The logger channel.
   */
  protected LoggerInterface $logger;

  /**
   * Constructs a ViewModeResolver object.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Entity\EntityDisplayRepositoryInterface $entity_display_repository
   *   The entity display repository.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger_factory
   *   The logger channel factory.
   */
  public function __construct(
    ConfigFactoryInterface $config_factory,
    EntityTypeManagerInterface $entity_type_manager,
    EntityDisplayRepositoryInterface $entity_display_repository,
    LoggerChannelFactoryInterface $logger_factory,
  ) {
    $this->configFactory = $config_factory;
    $this->entityTypeManager = $entity_type_manager;
    $this->entityDisplayRepository = $entity_display_repository;
    $this->logger = $logger_factory->get('markdownify_viewmodes');
  }

  /**
   * Resolves the view mode for a given entity.
   *
   * Checks in order:
   * 1. Bundle-specific third-party setting (if override enabled)
   * 2. Global third-party setting on markdownify.settings
   * 3. Fallback to 'full'
   * 4. Validates the view mode exists
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity to resolve the view mode for.
   *
   * @return string
   *   The resolved view mode name.
   */
  public function getViewMode(EntityInterface $entity): string {
    $entity_type_id = $entity->getEntityTypeId();
    $bundle = $entity->bundle();

    // Check bundle-specific override first.
    $bundle_view_mode = $this->getBundleViewMode($entity_type_id, $bundle);
    if ($bundle_view_mode !== NULL) {
      if ($this->validateViewMode($entity_type_id, $bundle, $bundle_view_mode)) {
        return $bundle_view_mode;
      }
      else {
        $this->logger->warning(
          'Invalid view mode "@mode" configured for @entity_type/@bundle',
          [
            '@mode' => $bundle_view_mode,
            '@entity_type' => $entity_type_id,
            '@bundle' => $bundle,
          ]
        );
      }
    }

    // Check entity-type-specific default.
    $entity_type_view_mode = $this->getEntityTypeDefaultViewMode($entity_type_id);
    if ($entity_type_view_mode !== NULL && $entity_type_view_mode !== 'full') {
      if ($this->validateViewMode($entity_type_id, $bundle, $entity_type_view_mode)) {
        return $entity_type_view_mode;
      }
      else {
        $this->logger->warning(
          'Invalid entity-type default view mode "@mode" configured for @entity_type',
          [
            '@mode' => $entity_type_view_mode,
            '@entity_type' => $entity_type_id,
          ]
        );
      }
    }

    // Fallback to full.
    return 'full';
  }

  /**
   * Gets the bundle-specific view mode override if enabled.
   *
   * @param string $entity_type_id
   *   The entity type ID.
   * @param string $bundle
   *   The bundle name.
   *
   * @return string|null
   *   The bundle view mode if override is enabled, NULL otherwise.
   */
  public function getBundleViewMode(string $entity_type_id, string $bundle): ?string {
    try {
      $bundle_entity = $this->getBundleEntity($entity_type_id, $bundle);
      if ($bundle_entity === NULL) {
        return NULL;
      }

      // Check if override is enabled.
      $enable_override = $bundle_entity->getThirdPartySetting(
        'markdownify_viewmodes',
        'enable_override',
        FALSE
      );

      if (!$enable_override) {
        return NULL;
      }

      return $bundle_entity->getThirdPartySetting(
        'markdownify_viewmodes',
        'view_mode'
      );
    }
    catch (\Exception $e) {
      $this->logger->error(
        'Error loading bundle entity for @entity_type/@bundle: @message',
        [
          '@entity_type' => $entity_type_id,
          '@bundle' => $bundle,
          '@message' => $e->getMessage(),
        ]
      );
      return NULL;
    }
  }

  /**
   * Gets the entity-type-specific default view mode.
   *
   * @param string $entity_type_id
   *   The entity type ID.
   *
   * @return string|null
   *   The configured entity-type default view mode, NULL if not set.
   */
  public function getEntityTypeDefaultViewMode(string $entity_type_id): ?string {
    $config = $this->configFactory->get('markdownify.settings');
    return $config->get("third_party_settings.markdownify_viewmodes.entity_types.{$entity_type_id}");
  }

  /**
   * Validates that a view mode exists for an entity type and bundle.
   *
   * @param string $entity_type_id
   *   The entity type ID.
   * @param string $bundle
   *   The bundle name.
   * @param string $view_mode
   *   The view mode to validate.
   *
   * @return bool
   *   TRUE if the view mode is valid, FALSE otherwise.
   */
  public function validateViewMode(string $entity_type_id, string $bundle, string $view_mode): bool {
    try {
      $view_modes = $this->entityDisplayRepository->getViewModeOptionsByBundle(
        $entity_type_id,
        $bundle
      );
      return isset($view_modes[$view_mode]);
    }
    catch (\Exception $e) {
      $this->logger->error(
        'Error validating view mode for @entity_type/@bundle: @message',
        [
          '@entity_type' => $entity_type_id,
          '@bundle' => $bundle,
          '@message' => $e->getMessage(),
        ]
      );
      return FALSE;
    }
  }

  /**
   * Gets the bundle entity for a given entity type and bundle.
   *
   * @param string $entity_type_id
   *   The entity type ID.
   * @param string $bundle
   *   The bundle name.
   *
   * @return \Drupal\Core\Entity\EntityInterface|null
   *   The bundle entity, or NULL if it can't be loaded.
   */
  protected function getBundleEntity(string $entity_type_id, string $bundle): ?EntityInterface {
    try {
      $entity_type = $this->entityTypeManager->getDefinition($entity_type_id);
      $bundle_entity_type = $entity_type->getBundleEntityType();

      if ($bundle_entity_type === NULL) {
        return NULL;
      }

      $storage = $this->entityTypeManager->getStorage($bundle_entity_type);
      return $storage->load($bundle);
    }
    catch (\Exception $e) {
      return NULL;
    }
  }

}

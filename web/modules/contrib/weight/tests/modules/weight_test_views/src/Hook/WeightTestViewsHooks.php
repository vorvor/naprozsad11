<?php

namespace Drupal\weight_test_views\Hook;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\State\StateInterface;

/**
 * Hook implementations for weight_test_views.
 */
class WeightTestViewsHooks {

  public function __construct(
    protected StateInterface $state,
  ) {}

  /**
   * Implements hook_entity_field_access().
   */
  #[Hook('entity_field_access')]
  public function entityFieldAccess($operation, FieldDefinitionInterface $field_definition, AccountInterface $account, ?FieldItemListInterface $items = NULL): AccessResultInterface {
    return AccessResult::forbiddenIf($operation === 'edit' && $field_definition->getName() === 'field_weight' && $this->state->get('weight_test_views.forbid_field_edit', FALSE));
  }

}

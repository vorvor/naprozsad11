<?php

namespace Drupal\weight\Hook;

use Drupal\Component\Utility\DeprecationHelper;
use Drupal\field\FieldStorageConfigInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\views\FieldViewsDataProvider;

/**
 * Hook implementations for weight.
 */
class WeightViewsHooks {
  use StringTranslationTrait;

  public function __construct(
    // NULL when Views is off or before 11.2, where the service doesn't exist.
    protected readonly ?FieldViewsDataProvider $fieldViewsDataProvider = NULL,
  ) {}

  /**
   * Implements hook_field_views_data().
   */
  #[Hook('field_views_data')]
  public function fieldViewsData(FieldStorageConfigInterface $field_storage) {
    // @phpstan-ignore function.notFound
    $data = DeprecationHelper::backwardsCompatibleCall(\Drupal::VERSION, '11.2.0', fn() => $this->fieldViewsDataProvider->defaultFieldImplementation($field_storage), fn() => views_field_default_views_data($field_storage));
    $field_name = $field_storage->getName();
    foreach ($data as &$table_data) {
      if (isset($table_data[$field_name])) {
        $table_data[$field_name . '_value']['field'] = $table_data[$field_name]['field'];
        $title = $this->t('@title Selector (@field)', [
          '@title' => $table_data[$field_name]['title'],
          '@field' => $field_name,
        ]);
        $short_title = $this->t('@title Selector', [
          '@title' => $table_data[$field_name]['title'],
        ]);
        $table_data[$field_name]['title'] = $title;
        $table_data[$field_name]['title short'] = $short_title;
        $table_data[$field_name]['field']['id'] = 'weight_selector';
      }
    }
    return $data;
  }

}

<?php

declare(strict_types=1);

namespace Drupal\Tests\weight\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the Views data for weight fields.
 */
#[Group('weight')]
#[RunTestsInSeparateProcesses]
final class WeightViewsDataTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'entity_test',
    'field',
    'user',
    'views',
    'weight',
  ];

  /**
   * Tests the field gets the weight selector handler.
   */
  public function testSelectorField(): void {
    $this->installEntitySchema('entity_test');
    FieldStorageConfig::create([
      'field_name' => 'field_weight',
      'entity_type' => 'entity_test',
      'type' => 'weight',
    ])->save();
    FieldConfig::create([
      'field_name' => 'field_weight',
      'entity_type' => 'entity_test',
      'bundle' => 'entity_test',
    ])->save();

    $data = $this->container->get('views.views_data')->get('entity_test__field_weight');

    $this->assertSame('weight_selector', $data['field_weight']['field']['id']);
    $this->assertSame('field', $data['field_weight_value']['field']['id']);
  }

}

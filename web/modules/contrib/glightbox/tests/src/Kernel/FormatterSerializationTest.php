<?php

namespace Drupal\Tests\glightbox\Kernel;

use Drupal\Component\Serialization\PhpSerialize;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests serialization of GLightbox field formatter dependencies.
 *
 * @group glightbox
 */
#[Group('glightbox')]
#[RunTestsInSeparateProcesses]
class FormatterSerializationTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'field',
    'file',
    'image',
    'breakpoint',
    'responsive_image',
    'glightbox',
  ];

  /**
   * Tests that formatter services are restored after serialization.
   *
   * @dataProvider formatterIds
   */
  #[DataProvider('formatterIds')]
  public function testFormatterSerialization(string $formatter_id): void {
    $container = $this->container;
    $formatter_manager = $container->get('plugin.manager.field.formatter');
    $formatter = $formatter_manager->createInstance($formatter_id, [
      'field_definition' => BaseFieldDefinition::create('image'),
      'settings' => $formatter_manager->getDefaultSettings($formatter_id),
      'label' => 'hidden',
      'view_mode' => 'default',
      'third_party_settings' => [],
    ]);

    $unserialized = PhpSerialize::decode(PhpSerialize::encode($formatter));
    foreach (['moduleHandler' => 'module_handler', 'libraryDiscovery' => 'library.discovery'] as $property => $service_id) {
      $this->assertSame(
        $container->get($service_id),
        (new \ReflectionProperty($unserialized, $property))->getValue($unserialized)
      );
    }
  }

  /**
   * Provides the formatter plugin IDs under test.
   */
  public static function formatterIds(): array {
    return [
      ['glightbox'],
      ['glightbox_responsive'],
    ];
  }

}

<?php

namespace Drupal\Tests\glightbox\Kernel;

use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FormatterInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Form\FormState;
use Drupal\Core\Render\BubbleableMetadata;
use Drupal\entity_test\Entity\EntityTest;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\file\Entity\File;
use Drupal\glightbox\Hook\GlightboxHooks;
use Drupal\glightbox\Service\GLightboxFormatterService;
use Drupal\glightbox_inline\Hook\GlightboxInlineHooks;
use Drupal\image\Entity\ImageStyle;
use Drupal\KernelTests\KernelTestBase;
use Drupal\responsive_image\Entity\ResponsiveImageStyle;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests image formatter and hook service dependencies.
 *
 * @group glightbox
 */
#[RunTestsInSeparateProcesses]
class FormatterDependenciesTest extends KernelTestBase {

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
    'responsive_image_test_module',
    'entity_test',
    'glightbox',
    'glightbox_inline',
  ];

  /**
   * Tests image style choices, responsive mappings and summaries.
   *
   * @dataProvider formatterCases
   */
  #[DataProvider('formatterCases')]
  public function testStyleSettings(string $formatter_id, bool $with_styles): void {
    if ($with_styles) {
      $this->createStyles();
    }
    $formatter = $this->createFormatter($formatter_id, BaseFieldDefinition::create('image'));
    $form = $formatter->settingsForm([], new FormState());
    $expected = $with_styles
      ? ['fixture' => 'Fixture image']
      : ['' => 'No defined styles'];
    $this->assertSame($expected, array_map('strval', $form['glightbox_image_style']['#options']));
    if ($formatter_id === 'glightbox_responsive') {
      $this->assertSame(
        $with_styles ? ['fixture_responsive' => 'Fixture responsive image'] : [],
        array_map('strval', $form['glightbox_responsive_node_style']['#options']),
      );
    }
    else {
      $this->assertSame('Hide (do not display image)', (string) $form['glightbox_node_style']['#options']['hide']);
    }
    $summary = implode("\n", array_map('strval', $formatter->settingsSummary()));
    $this->assertStringContainsString($with_styles ? 'Fixture image' : 'Original image', $summary);
  }

  /**
   * Tests style configuration dependencies and rendered cache tags.
   *
   * @dataProvider formatterIds
   */
  #[DataProvider('formatterIds')]
  public function testStyleDependenciesAndCacheTags(string $formatter_id): void {
    $style = $this->createStyles();
    $entity = $this->createImageEntity();
    $formatter = $this->createFormatter($formatter_id, $entity->getFieldDefinition('field_image'));
    $dependencies = $formatter->calculateDependencies();
    $this->assertContains('image.style.fixture', $dependencies['config']);
    if ($formatter_id === 'glightbox_responsive') {
      $this->assertContains('responsive_image.styles.fixture_responsive', $dependencies['config']);
    }
    $items = $entity->get('field_image');
    $formatter->prepareView([$items]);
    $build = $formatter->viewElements($items, 'en');
    $this->assertNotEmpty($build[0]);
    foreach ($style->getCacheTags() as $tag) {
      $this->assertContains($tag, $build[0]['#cache']['tags']);
    }
    if ($formatter_id === 'glightbox_responsive') {
      $this->assertContains('config:responsive_image.styles.fixture_responsive', $build[0]['#cache']['tags']);
    }
  }

  /**
   * Tests replacing a removed image style in formatter settings.
   */
  public function testImageStyleReplacement(): void {
    $style = $this->createStyles();
    ImageStyle::create(['name' => 'replacement', 'label' => 'Replacement image'])->save();
    $this->container->get('entity_type.manager')->getStorage('image_style')->setReplacementId('fixture', 'replacement');
    $formatter = $this->createFormatter('glightbox', BaseFieldDefinition::create('image'));
    $this->assertTrue($formatter->onDependencyRemoval([
      'config' => ['image.style.fixture' => $style],
    ]));
    foreach (['glightbox_node_style', 'glightbox_node_style_first', 'glightbox_image_style'] as $setting) {
      $this->assertSame('replacement', $formatter->getSetting($setting));
    }
  }

  /**
   * Tests service creation, inline attachments and field token rendering.
   */
  public function testHookAndCaptionServices(): void {
    $entity = $this->createImageEntity();
    $page = [];
    $this->container->get(GlightboxInlineHooks::class)->pageAttachments($page);
    $this->assertContains('glightbox/init', $page['#attached']['library']);
    $this->assertContains('glightbox_inline/glightbox_inline', $page['#attached']['library']);
    $token = '[paragraph_container:name]';
    $replacements = $this->container->get(GlightboxHooks::class)->tokens(
      'paragraph_container',
      ['name' => $token],
      ['paragraph_container' => $entity],
      [],
      new BubbleableMetadata(),
    );
    $this->assertStringContainsString('Fixture entity', (string) $replacements[$token]);
    $variables = [
      'item' => $entity->get('field_image')->first(),
      'entity' => $entity,
      'settings' => ['glightbox_caption' => 'title'],
    ];
    $this->assertSame('Fixture title', $this->container->get('glightbox.formatter')->getCaption($variables));
  }

  /**
   * Tests title and alt captions loaded from optional File Entity fields.
   */
  public function testFileEntityCaptions(): void {
    $entity = $this->createImageEntity();
    foreach (['field_image_title_text', 'field_image_alt_text'] as $field_name) {
      FieldStorageConfig::create([
        'entity_type' => 'file',
        'field_name' => $field_name,
        'type' => 'string',
      ])->save();
      FieldConfig::create([
        'entity_type' => 'file',
        'field_name' => $field_name,
        'bundle' => 'file',
      ])->save();
    }
    $item = $entity->get('field_image')->first();
    $this->container->get('entity_type.manager')->getStorage('file')->resetCache([$item->target_id]);
    $file = File::load($item->target_id);
    $file->set('field_image_title_text', 'File entity title');
    $file->set('field_image_alt_text', 'File entity alt');
    $file->save();
    $item->title = '';
    $item->alt = '';
    $module_handler = $this->createMock(ModuleHandlerInterface::class);
    $module_handler->method('moduleExists')->willReturnCallback(static fn($module) => $module === 'file_entity');
    $service = new GLightboxFormatterService(
      $this->container->get('token'),
      $module_handler,
      $this->container->get('config.factory'),
      $this->container->get('entity_type.manager'),
    );
    foreach (['title' => 'File entity title', 'alt' => 'File entity alt'] as $caption_type => $expected) {
      $variables = ['item' => $item, 'entity' => $entity, 'settings' => ['glightbox_caption' => $caption_type]];
      $this->assertSame($expected, $service->getCaption($variables));
    }
  }

  /**
   * Creates mapped and unmapped responsive styles and their image style.
   *
   * @return \Drupal\image\Entity\ImageStyle
   *   The image style used by the responsive mapping.
   */
  protected function createStyles(): ImageStyle {
    $style = ImageStyle::create(['name' => 'fixture', 'label' => 'Fixture image']);
    $style->save();
    ResponsiveImageStyle::create([
      'id' => 'fixture_responsive',
      'label' => 'Fixture responsive image',
      'breakpoint_group' => 'responsive_image_test_module',
      'fallback_image_style' => 'fixture',
      'image_style_mappings' => [
        [
          'breakpoint_id' => 'responsive_image_test_module.mobile',
          'multiplier' => '1x',
          'image_mapping_type' => 'image_style',
          'image_mapping' => 'fixture',
        ],
      ],
    ])->save();
    ResponsiveImageStyle::create([
      'id' => 'unmapped',
      'label' => 'Unmapped responsive image',
      'breakpoint_group' => 'responsive_image_test_module',
    ])->save();
    return $style;
  }

  /**
   * Creates an entity referencing an image file.
   *
   * @return \Drupal\entity_test\Entity\EntityTest
   *   The entity containing the image field.
   */
  protected function createImageEntity(): EntityTest {
    $this->installEntitySchema('user');
    $this->installEntitySchema('file');
    $this->installEntitySchema('entity_test');
    FieldStorageConfig::create([
      'entity_type' => 'entity_test',
      'field_name' => 'field_image',
      'type' => 'image',
    ])->save();
    FieldConfig::create([
      'entity_type' => 'entity_test',
      'field_name' => 'field_image',
      'bundle' => 'entity_test',
    ])->save();
    $file = File::create(['uri' => 'public://fixture.png', 'status' => 1]);
    $file->save();
    return EntityTest::create([
      'name' => 'Fixture entity',
      'field_image' => ['target_id' => $file->id(), 'alt' => 'Fixture image', 'title' => 'Fixture title'],
    ]);
  }

  /**
   * Creates a formatter with representative image style settings.
   *
   * @param string $formatter_id
   *   The formatter plugin ID.
   * @param \Drupal\Core\Field\FieldDefinitionInterface $field_definition
   *   The image field definition.
   *
   * @return \Drupal\Core\Field\FormatterInterface
   *   The configured formatter.
   */
  protected function createFormatter(string $formatter_id, FieldDefinitionInterface $field_definition): FormatterInterface {
    $manager = $this->container->get('plugin.manager.field.formatter');
    $settings = $manager->getDefaultSettings($formatter_id);
    $settings['glightbox_node_style'] = 'fixture';
    $settings['glightbox_node_style_first'] = 'fixture';
    $settings['glightbox_image_style'] = 'fixture';
    $settings['glightbox_responsive_node_style'] = 'fixture_responsive';
    $settings['glightbox_responsive_image_style'] = 'fixture_responsive';
    return $manager->createInstance($formatter_id, [
      'field_definition' => $field_definition,
      'settings' => $settings,
      'label' => 'hidden',
      'view_mode' => 'default',
      'third_party_settings' => [],
    ]);
  }

  /**
   * Provides formatter IDs and image style availability.
   */
  public static function formatterCases(): array {
    return [
      ['glightbox', TRUE],
      ['glightbox', FALSE],
      ['glightbox_responsive', TRUE],
      ['glightbox_responsive', FALSE],
    ];
  }

  /**
   * Provides formatter plugin IDs.
   */
  public static function formatterIds(): array {
    return [['glightbox'], ['glightbox_responsive']];
  }

}

<?php

namespace Drupal\Tests\weight\Functional;

use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\Tests\BrowserTestBase;
use Drupal\user\Entity\Role;
use Drupal\user\RoleInterface;
use Drupal\views\Tests\ViewTestData;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests that the weight selector only saves entities the user may edit.
 */
#[Group('weight')]
#[RunTestsInSeparateProcesses]
class WeightSelectorAccessTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'node',
    'views',
    'weight',
    'weight_test_views',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  public static $testViews = ['test_weight'];

  /**
   * The original weight of each test node, keyed by title.
   */
  protected const WEIGHTS = [
    'Article 1' => -20,
    'Article 2' => -19,
    'Article 3' => -18,
    'Article 4' => -17,
  ];

  /**
   * The test nodes, keyed by title.
   *
   * @var \Drupal\node\NodeInterface[]
   */
  protected $nodes = [];

  /**
   * The owner of Article 1 and Article 2.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $owner;

  /**
   * The owner of Article 3 and Article 4.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $otherOwner;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->drupalCreateContentType(['type' => 'article']);
    FieldStorageConfig::create([
      'field_name' => 'field_weight',
      'entity_type' => 'node',
      'type' => 'weight',
    ])->save();
    FieldConfig::create([
      'field_name' => 'field_weight',
      'entity_type' => 'node',
      'bundle' => 'article',
    ])->save();

    $this->owner = $this->drupalCreateUser(['access content', 'edit own article content']);
    $this->otherOwner = $this->drupalCreateUser();
    foreach (static::WEIGHTS as $title => $weight) {
      $this->nodes[$title] = $this->drupalCreateNode([
        'type' => 'article',
        'title' => $title,
        'field_weight' => $weight,
        'uid' => in_array($title, ['Article 1', 'Article 2'], TRUE) ? $this->owner->id() : $this->otherOwner->id(),
      ]);
    }

    ViewTestData::createTestViews(static::class, ['weight_test_views']);
    Role::load(RoleInterface::ANONYMOUS_ID)->grantPermission('access content')->save();
  }

  /**
   * Tests weight selector access.
   */
  public function testWeightSelectorAccess(): void {
    $this->assertAnonymousDenied();
    $this->assertNoUpdateAccessDenied();
    $this->assertNoFieldEditAccessDenied();
    $this->assertUpdateAccessAllowed();
    $this->assertAccessCheckedPerRow();
    $this->assertRowsMatchedBySubmittedEntity();
    $this->assertTamperedEntityDenied();
    // Last, because the translation adds a row to the view.
    $this->assertAccessCheckedPerTranslation();
  }

  /**
   * Asserts that an anonymous user cannot change weights.
   */
  protected function assertAnonymousDenied(): void {
    $this->resetWeights();
    $this->submitAllWeights(5);
    $this->assertWeights(static::WEIGHTS);
    $this->assertDenied(array_keys(static::WEIGHTS));
    $this->assertSession()->pageTextNotContains('Your changes have been saved.');
  }

  /**
   * Asserts that a user who can view but not update cannot change weights.
   */
  protected function assertNoUpdateAccessDenied(): void {
    $this->resetWeights();
    // Unlike anonymous, this form carries a CSRF token.
    $this->drupalLogin($this->drupalCreateUser(['access content']));
    $this->submitAllWeights(5);
    $this->assertWeights(static::WEIGHTS);
    $this->assertDenied(array_keys(static::WEIGHTS));
    $this->assertSession()->pageTextNotContains('Your changes have been saved.');
  }

  /**
   * Asserts that entity update access is not enough without field access.
   */
  protected function assertNoFieldEditAccessDenied(): void {
    $this->resetWeights();
    \Drupal::state()->set('weight_test_views.forbid_field_edit', TRUE);
    $this->drupalLogin($this->drupalCreateUser(['access content', 'edit any article content']));
    $this->submitAllWeights(5);
    $this->assertWeights(static::WEIGHTS);
    $this->assertDenied(array_keys(static::WEIGHTS));
    \Drupal::state()->delete('weight_test_views.forbid_field_edit');
  }

  /**
   * Asserts that a user with update access can change all weights.
   */
  protected function assertUpdateAccessAllowed(): void {
    $this->resetWeights();
    $this->drupalLogin($this->drupalCreateUser(['access content', 'edit any article content']));
    $this->submitAllWeights(5);
    $this->assertWeights(['Article 1' => 5, 'Article 2' => 5, 'Article 3' => 5, 'Article 4' => 5]);
    $this->assertSession()->pageTextContains('Your changes have been saved.');
    $this->assertSession()->pageTextNotContains('No access to update the weight');
  }

  /**
   * Asserts that access is checked per row, not for the view as a whole.
   */
  protected function assertAccessCheckedPerRow(): void {
    $this->resetWeights();
    $this->drupalLogin($this->owner);
    $this->submitAllWeights(5);
    $this->assertWeights(['Article 1' => 5, 'Article 2' => 5, 'Article 3' => -18, 'Article 4' => -17]);
    $this->assertDenied(['Article 3', 'Article 4']);
    $this->assertSession()->pageTextNotContains('No access to update the weight of the Content Article 1.');
    $this->assertSession()->pageTextNotContains('No access to update the weight of the Content Article 2.');
  }

  /**
   * Asserts weights land on the rows the user saw, even if the view changed.
   */
  protected function assertRowsMatchedBySubmittedEntity(): void {
    $this->resetWeights();
    $this->drupalLogin($this->drupalCreateUser(['access content', 'edit any article content']));
    $this->drupalGet('test-weight');
    $edit = [];
    foreach ($this->getSession()->getPage()->findAll('css', 'tbody tr') as $row) {
      $title = $row->find('css', 'td')->getText();
      $edit[$row->find('css', 'select.weight-selector')->getAttribute('name')] = (int) substr($title, -1);
    }

    // Shift the rows between page load and submit.
    $newcomer = $this->drupalCreateNode([
      'type' => 'article',
      'title' => 'Newcomer',
      'field_weight' => -1,
      'created' => \Drupal::time()->getRequestTime() + 100,
    ]);
    $this->loadNode('Article 4')->setUnpublished()->save();
    $this->submitForm($edit, 'Save');

    $this->assertWeights(['Article 1' => 1, 'Article 2' => 2, 'Article 3' => 3, 'Article 4' => 4]);
    $this->assertSame(-1, (int) \Drupal::entityTypeManager()->getStorage('node')->loadUnchanged($newcomer->id())->get('field_weight')->value);

    $newcomer->delete();
    $this->loadNode('Article 4')->setPublished()->save();
  }

  /**
   * Asserts that a row pointed at another entity is still access checked.
   */
  protected function assertTamperedEntityDenied(): void {
    $this->resetWeights();
    $this->drupalLogin($this->owner);
    $this->drupalGet('test-weight');
    $edit = [];
    foreach ($this->getSession()->getPage()->findAll('css', 'select.weight-selector') as $select) {
      $edit[$select->getAttribute('name')] = 5;
    }
    foreach ($this->getSession()->getPage()->findAll('css', 'input[name$="[entity_id]"]') as $input) {
      $input->setValue($this->nodes['Article 3']->id());
    }
    $this->submitForm($edit, 'Save');
    $this->assertWeights(static::WEIGHTS);
    $this->assertDenied(['Article 3']);
  }

  /**
   * Asserts that access is checked on the translation the row displays.
   */
  protected function assertAccessCheckedPerTranslation(): void {
    $this->resetWeights();
    ConfigurableLanguage::createFromLangcode('fr')->save();
    $this->rebuildContainer();
    // Ownership is per translation. Reload, as the node caches its languages.
    \Drupal::entityTypeManager()->getStorage('node')->load($this->nodes['Article 1']->id())->addTranslation('fr', [
      'title' => 'Article 1 (fr)',
      'field_weight' => -16,
      'uid' => $this->otherOwner->id(),
    ])->save();

    $this->drupalLogin($this->owner);
    $this->submitAllWeights(5);
    $this->assertWeights(['Article 1' => 5, 'Article 2' => 5, 'Article 3' => -18, 'Article 4' => -17]);
    $this->assertSame(-16, (int) $this->loadNode('Article 1')->getTranslation('fr')->get('field_weight')->value);
    $this->assertDenied(['Article 1 (fr)', 'Article 3', 'Article 4']);
  }

  /**
   * Restores the original weight of each test node.
   */
  protected function resetWeights(): void {
    foreach (static::WEIGHTS as $title => $weight) {
      $this->loadNode($title)->set('field_weight', $weight)->save();
    }
  }

  /**
   * Submits the same weight on every row, so row order doesn't matter.
   *
   * @param int $weight
   *   The weight to submit for every row.
   */
  protected function submitAllWeights(int $weight): void {
    $this->drupalGet('test-weight');
    $edit = [];
    foreach ($this->getSession()->getPage()->findAll('css', 'select.weight-selector') as $select) {
      $edit[$select->getAttribute('name')] = $weight;
    }
    $this->assertNotEmpty($edit);
    $this->submitForm($edit, 'Save');
  }

  /**
   * Asserts the stored weight of each test node.
   *
   * @param int[] $expected
   *   The expected weights, keyed by node title.
   */
  protected function assertWeights(array $expected): void {
    $actual = [];
    foreach (array_keys($this->nodes) as $title) {
      $actual[$title] = (int) $this->loadNode($title)->get('field_weight')->value;
    }
    $this->assertSame($expected, $actual);
  }

  /**
   * Asserts an access denied message for each given row.
   *
   * @param string[] $labels
   *   The labels of the rows the user may not update.
   */
  protected function assertDenied(array $labels): void {
    foreach ($labels as $label) {
      $this->assertSession()->pageTextContains("No access to update the weight of the Content $label.");
    }
  }

  /**
   * Loads a test node, bypassing the static cache.
   *
   * @param string $title
   *   The title of the test node.
   *
   * @return \Drupal\node\NodeInterface
   *   The node.
   */
  protected function loadNode(string $title) {
    return \Drupal::entityTypeManager()->getStorage('node')->loadUnchanged($this->nodes[$title]->id());
  }

}

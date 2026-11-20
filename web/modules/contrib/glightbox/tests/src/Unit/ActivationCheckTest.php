<?php

namespace Drupal\Tests\glightbox\Unit;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\glightbox\ActivationCheck;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Tests activation flag lookup in a request.
 *
 * @group glightbox
 */
#[Group('glightbox')]
class ActivationCheckTest extends UnitTestCase {

  /**
   * Tests whether the flag in request bags controls activation.
   *
   * @dataProvider activationCases
   */
  #[DataProvider('activationCases')]
  public function testIsActive(
    array $attributes,
    array $query,
    array $post,
    bool $expected,
  ): void {
    $config = $this->createMock(ConfigFactoryInterface::class);
    $config->method('get')
      ->with('glightbox.settings')
      ->willReturn($this->createMock(ImmutableConfig::class));
    $request = new Request($query, $post, $attributes);
    $request_stack = new RequestStack();
    $request_stack->push($request);

    $activation_check = new ActivationCheck($config, $request_stack);

    $this->assertSame($expected, $activation_check->isActive());
  }

  /**
   * Provides request bag combinations and their activation result.
   */
  public static function activationCases(): array {
    return [
      'flag absent' => [[], [], [], TRUE],
      'disabled in attributes' => [['glightbox' => 'no'], [], [], FALSE],
      'disabled in query' => [[], ['glightbox' => 'no'], [], FALSE],
      'disabled in post' => [[], [], ['glightbox' => 'no'], FALSE],
      'another value is active' => [['glightbox' => 'yes'], [], [], TRUE],
      'attributes override query with active value' => [
        ['glightbox' => 'yes'],
        ['glightbox' => 'no'],
        [],
        TRUE,
      ],
      'attributes override query with disabled value' => [
        ['glightbox' => 'no'],
        ['glightbox' => 'yes'],
        [],
        FALSE,
      ],
      'query overrides post with active value' => [
        [],
        ['glightbox' => 'yes'],
        ['glightbox' => 'no'],
        TRUE,
      ],
      'query overrides post with disabled value' => [
        [],
        ['glightbox' => 'no'],
        ['glightbox' => 'yes'],
        FALSE,
      ],
      'null attribute overrides query' => [
        ['glightbox' => NULL],
        ['glightbox' => 'no'],
        [],
        TRUE,
      ],
      'null query overrides post' => [
        [],
        ['glightbox' => NULL],
        ['glightbox' => 'no'],
        TRUE,
      ],
      'array value is active' => [
        ['glightbox' => ['no']],
        [],
        [],
        TRUE,
      ],
      'array query value is active' => [
        [],
        ['glightbox' => ['no']],
        [],
        TRUE,
      ],
      'array post value is active' => [
        [],
        [],
        ['glightbox' => ['no']],
        TRUE,
      ],
    ];
  }

}

<?php

namespace Drupal\glightbox;

use Drupal\Core\Config\ConfigFactoryInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Implementation of ActivationCheckInterface.
 */
class ActivationCheck implements ActivationCheckInterface {

  /**
   * The glightbox settings.
   *
   * @var \Drupal\Core\Config\ImmutableConfig
   */
  protected $settings;

  /**
   * The request stack.
   *
   * @var \Symfony\Component\HttpFoundation\Request
   */
  protected $request;

  /**
   * Create an instance of ActivationCheck.
   */
  public function __construct(ConfigFactoryInterface $config, RequestStack $request) {
    $this->settings = $config->get('glightbox.settings');
    $this->request = $request->getCurrentRequest();
  }

  /**
   * {@inheritdoc}
   */
  public function isActive() {
    foreach ([
      $this->request->attributes,
      $this->request->query,
      $this->request->request,
    ] as $parameters) {
      if ($parameters->has('glightbox')) {
        return $parameters->all()['glightbox'] !== 'no';
      }
    }

    return TRUE;
  }

}

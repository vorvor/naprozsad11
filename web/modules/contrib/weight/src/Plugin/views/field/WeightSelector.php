<?php

namespace Drupal\weight\Plugin\views\field;

use Drupal\Component\Render\MarkupInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\views\Attribute\ViewsField;
use Drupal\views\Plugin\views\field\FieldPluginBase;
use Drupal\views\Render\ViewsRenderPipelineMarkup;
use Drupal\views\ResultRow;
use Drupal\weight\Plugin\Field\FieldWidget\WeightSelectorWidget;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Field handler to present a weight selector element.
 *
 * @ingroup views_field_handlers
 */
#[ViewsField('weight_selector')]
class WeightSelector extends FieldPluginBase {
  /**
   * Symfony\Component\HttpFoundation\RequestStack definition.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected $requestStack;

  /**
   * The messenger.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected $messenger;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, RequestStack $request_stack, MessengerInterface $messenger, EntityTypeManagerInterface $entity_type_manager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->requestStack = $request_stack;
    $this->messenger = $messenger;
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('request_stack'),
      $container->get('messenger'),
      $container->get('entity_type.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  protected function defineOptions(): array {
    $options = parent::defineOptions();

    $options['range'] = ['default' => 20];

    return $options;
  }

  /**
   * {@inheritdoc}
   */
  public function buildOptionsForm(&$form, FormStateInterface $form_state) {
    parent::buildOptionsForm($form, $form_state);

    $form['range'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Range'),
      '#description' => $this->t('The range of weights available to select. For
        example, a range of 20 will allow you to select a weight between -20
        and 20.'),
      '#default_value' => $this->options['range'],
      '#size' => 5,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function render(ResultRow $values): MarkupInterface {
    return ViewsRenderPipelineMarkup::create('<!--form-item-' . $this->options['id'] . '--' . $this->view->row_index . '-->');
  }

  /**
   * {@inheritdoc}
   */
  public function viewsForm(array &$form, FormStateInterface $form_state) {
    // The view is empty, abort.
    if (empty($this->view->result)) {
      return;
    }

    $form[$this->options['id']] = [
      '#tree' => TRUE,
    ];

    $options = WeightSelectorWidget::rangeOptions($this->options['range']);

    // At this point the query already run, so we can access the results.
    foreach ($this->view->result as $row_index => $row) {
      $entity = $this->getEntity($row);
      $field_langcode = $entity->getEntityTypeId() . '__' . $this->field . '_langcode';

      $form[$this->options['id']][$row_index]['weight'] = [
        '#type' => 'select',
        '#options' => $options,
        '#default_value' => $this->getValue($row),
        '#attributes' => ['class' => ['weight-selector']],
      ];

      // The view results may change before submit, so rows carry their entity.
      $form[$this->options['id']][$row_index]['entity_id'] = [
        '#type' => 'hidden',
        '#default_value' => $entity->id(),
      ];

      $form[$this->options['id']][$row_index]['langcode'] = [
        '#type' => 'hidden',
        '#default_value' => $row->{$field_langcode} ?? '',
      ];
    }

    $form['views_field'] = [
      '#type' => 'value',
      '#value' => $this->field,
    ];

    $form['#action'] = $this->requestStack->getCurrentRequest()->getRequestUri();
  }

  /**
   * {@inheritdoc}
   */
  public function viewsFormSubmit(array &$form, FormStateInterface $form_state) {
    $field_name = $form_state->getValue('views_field');
    if (!$field_name) {
      return;
    }
    $rows = $form_state->getValue($field_name);
    $storage = $this->entityTypeManager->getStorage($this->getEntityType());

    foreach ($rows as $row) {
      $entity = $storage->load($row['entity_id']);
      if ($entity && $row['langcode']) {
        $entity = $entity->hasTranslation($row['langcode']) ? $entity->getTranslation($row['langcode']) : NULL;
      }
      if ($entity && $entity->hasField($field_name)) {
        if (!$entity->access('update') || !$entity->get($field_name)->access('edit')) {
          $this->messenger->addError($this->t('No access to update the weight of the @entity_type_label %entity_label.', [
            '@entity_type_label' => $entity->getEntityType()->getLabel(),
            '%entity_label' => $entity->label(),
          ]));
          continue;
        }
        $entity->set($field_name, $row['weight']);
        $entity->save();
        $this->messenger->addMessage($this->t('Your changes have been saved.'));
      }
    }
  }

}

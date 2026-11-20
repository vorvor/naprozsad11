<?php

namespace Drupal\weight\Hook;

use Drupal\Core\Render\Element\Table;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Hook implementations for weight.
 */
class WeightHooks {
  use StringTranslationTrait;

  /**
   * Implements hook_help().
   */
  #[Hook('help')]
  public function help($route_name, RouteMatchInterface $route_match) {
    switch ($route_name) {
      case 'help.page.weight':
        $output = '';
        $output .= '<h3>' . $this->t('About') . '</h3>';
        $output .= '<p>' . $this->t('This module provides a weight field that can be added to any fieldable entity.') . '</p>';
        $output .= '<p>' . $this->t('The weight field can be used to provide customized sorting.') . '</p>';
        $output .= '<h3>' . $this->t('How to use') . '</h3>';
        $output .= '<p>' . $this->t('Now the weight field is available to be used in any entity. So just as example, you can add the "weight" field in a content type:') . '</p>';
        $output .= '<ul>';
        $output .= '<li>' . $this->t('Access: "Structure" » "Content types" » <CONTENT-TYPE> » "Manage fields"') . '</li>';
        $output .= '<li>' . $this->t('Go to "Add field" and select "General" » "Weight"') . '</li>';
        $output .= '<li>' . $this->t('Put a label and save.') . '</li>';
        $output .= '<li>' . $this->t('Now you can select a "Range" value that will be range of weights available to select. For example, a range of 20 will allow you to select a weight between -20 and 20.') . '</li>';
        $output .= '<li>' . $this->t('Save.') . '</li>';
        $output .= '<li>' . $this->t('Now you can add a node using this content type:') . '</li>';
        $output .= '<li>' . $this->t('Go to "Content" » "Add content" » <CONTENT-TYPE>') . '</li>';
        $output .= '<li>' . $this->t('Now you can see the "weight" and the range to select') . '</li>';
        $output .= '</ul>';
        return $output;

      default:
    }
  }

  /**
   * Implements hook_preprocess_views_view_table().
   */
  #[Hook('preprocess_views_view_table')]
  public function preprocessViewsViewTable(&$variables) {
    // Check if view return results.
    if (!$variables['result']) {
      return;
    }
    // Check for a weight selector field.
    foreach ($variables['view']->field as $field_key => $field) {
      if (isset($field->options['plugin_id']) && $field->options['plugin_id'] == 'weight_selector') {
        // Check if the weight selector is on the first column.
        $is_first_column = array_search($field_key, array_keys($variables['view']->field)) > 0 ? FALSE : TRUE;
        // Add the tabledrag attributes.
        foreach ($variables['rows'] as $key => $row) {
          if ($is_first_column) {
            // If the weight selector is the first column move it to the last
            // column, in order to make the draggable widget appear.
            $weight_selector = $variables['rows'][$key]['columns'][$field->field];
            unset($variables['rows'][$key]['columns'][$field->field]);
            $variables['rows'][$key]['columns'][$field->field] = $weight_selector;
            $header_weight_selector = $variables['header'][$field->field];
            unset($variables['header'][$field->field]);
            $variables['header'][$field->field] = $header_weight_selector;
          }
          // Add draggable attribute.
          $variables['rows'][$key]['attributes']->addClass('draggable');
        }
        // The row key identify in an unique way a view grouped by a field.
        // Without row number, all the groups will share the same table_id
        // and just the first table can be draggable.
        $table_id = 'weight-table-' . $variables['view']->dom_id . '-row-' . $key;
        $variables['attributes']['id'] = $table_id;
        // Since 11.5 tabledrag.js finds tables by this attribute, which only
        // the table render element sets.
        $variables['attributes']['data-drupal-tabledrag'] = $table_id;
        $options = [
          'table_id' => $table_id,
          'action' => 'order',
          'relationship' => 'sibling',
          'group' => 'weight-selector',
        ];
        if (method_exists(Table::class, 'attachTabledrag')) {
          Table::attachTabledrag($variables, $options);
        }
        else {
          // @phpstan-ignore function.deprecated
          drupal_attach_tabledrag($variables, $options);
        }
      }
    }
  }

}

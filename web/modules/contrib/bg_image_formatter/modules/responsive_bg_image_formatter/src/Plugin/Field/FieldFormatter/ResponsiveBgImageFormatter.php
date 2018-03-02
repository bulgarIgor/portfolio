<?php

namespace Drupal\responsive_bg_image_formatter\Plugin\Field\FieldFormatter;

use Drupal\Core\Url;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\bg_image_formatter\Plugin\Field\FieldFormatter\BgImageFormatter;
use Drupal\responsive_image\Entity\ResponsiveImageStyle;

/**
 * @FieldFormatter(
 *  id = "responsive_bg_image_formatter",
 *  label = @Translation("Responsive Background Image"),
 *  field_types = {"image"}
 * )
 */
class ResponsiveBgImageFormatter extends BgImageFormatter {

  /**
   * Get the possible responsive image styles.
   */
  protected function getResponsiveImageStyles($withNone = FALSE) {
    $styles = ResponsiveImageStyle::loadMultiple();
    $options = array();

    if ($withNone && empty($styles)) {
      $options[''] = t('- Defined None -');
    }

    foreach ($styles as $name => $style) {
      $options[$name] = $style->label();
    }

    return $options;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    $element = parent::settingsForm($form, $form_state);
    $element['image_style']['#options'] = $this->getResponsiveImageStyles(TRUE);
    $element['image_style']['#description'] = $this->t(
      'Select <a href="@href_image_style">the responsive image style</a> to use.',
      array(
        '@href_image_style' =>
        Url::fromRoute('entity.responsive_image_style.collection')->toString(),
      )
    );

    unset($element['css_settings']['bg_image_media_query']);

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary() {
    $summary = parent::settingsSummary();
    $settings = $this->getSettings();
    $options = $this->getResponsiveImageStyles();

    if (isset($options[$settings['image_style']])) {
      $summary[1] = $this->t('URL for image style: @style', array('@style' => $options[$settings['image_style']]));
    }
    else {
      $summary[1] = $this->t('Original image style');
    }

    return $summary;
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    $elements = array();
    $index = 0;
    $settings = $this->getSettings();
    $css_settings = $settings['css_settings'];
    $selectors = array_filter(preg_split('/$/', $css_settings['bg_image_selector']));
    $files = $this->getEntitiesToView($items, $langcode);

    // Filter out empty selectors
    $selectors = array_map(function($value) {
      return trim($value, ',');
    }, $selectors);

    // Early opt-out if the field is empty.
    if (empty($files) || empty($settings['image_style'])) {
      return $elements;
    }

    // Prepare token data in bg image css selector.
    $token_data = array(
      'user' => \Drupal::currentUser(),
      $items->getEntity()->getEntityTypeId() => $items->getEntity(),
    );
    foreach ($selectors as &$selector) {
      $selector = \Drupal::token()->replace($selector, $token_data);
    }

    // Need an empty element so views renderer will see something to render.
    $elements[0] = [];

    foreach ($files as $delta => $file) {
      // Use specified selectors in round-robin order.
      $selector = $selectors[$index % count($selectors)];

      $vars = array(
        'uri' => $file->getFileUri(),
        'responsive_image_style_id' => $settings['image_style'],
      );
      template_preprocess_responsive_image($vars);

      // Split each source into multiple rules.
      foreach (array_reverse($vars['sources']) as $source_i => $source) {
        $attr = $source->toArray();

        $srcset = explode(', ', $attr['srcset']);

        foreach ($srcset as $src_i => $src) {
          list($src, $res) = explode(' ', $src);

          $media = isset($attr['media']) ? $attr['media'] : '';

          // Add "retina" to media query if this is a 2x image.
          if ($res && $res === '2x') {
            $media = "$media and (-webkit-min-device-pixel-ratio: 2), $media and (min-resolution: 192dpi)";
          }

          // Correct a bug in template_preprocess_responsive_image which
          // generates an invalid media rule "screen (max-width)" when no
          // min-width is specified. If this bug gets fixed, this replacement
          // will deactivate.
          $media = str_replace('screen (max-width', 'screen and (max-width', $media);

          $css_settings['bg_image_selector'] = $selector;
          $css = $this->getBackgroundImageCss($src, $css_settings);
          $css['media'] = $media;

          // Define unique key to prevent collisions when displaying multiple
          // background images on the same page.
          $html_head_key = 'responsive_bg_image_formatter_css__' . sha1(
              implode('__', [
                $items->getEntity()->uuid(),
                $items->getName(),
                $settings['image_style'],
                $delta,
                $src_i,
                $source_i,
              ])
            );

          $elements['#attached']['html_head'][] = [[
            '#tag' => 'style',
            '#attributes' => [
              'media' => $css['media'],
            ],
            '#value' => $css['style'],
          ], $html_head_key,
          ];
        }
      }

      $index++;
    }

    return $elements;
  }

}

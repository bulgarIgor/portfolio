<?php

namespace Drupal\Tests\rest\Functional\EntityResource\ResponsiveImageStyle;

use Drupal\Tests\rest\Functional\CookieResourceTestTrait;

/**
 * @group rest
 */
class ResponsiveImageStyleJsonCookieTest extends ResponsiveImageStyleResourceTestBase {

  use CookieResourceTestTrait;

  /**
   * {@inheritdoc}
   */
  protected static $format = 'json';

  /**
   * {@inheritdoc}
   */
  protected static $mimeType = 'application/json';

  /**
   * {@inheritdoc}
   */
  protected static $auth = 'cookie';

}
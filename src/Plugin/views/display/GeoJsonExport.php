<?php

namespace Drupal\views_geojson\Plugin\views\display;

use Drupal\rest\Plugin\views\display\RestExport;
use Drupal\views\ViewExecutable;
use Drupal\Component\Utility\String;
use Drupal\Core\State\StateInterface;
use Drupal\Core\Routing\RouteProviderInterface;
use Drupal\views\Plugin\views\display\PathPluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\RouteCollection;

/**
 * The plugin that handles Data response callbacks for REST resources.
 *
 * @ingroup views_display_plugins
 *
 * @ViewsDisplay(
 *   id = "geojson_export",
 *   title = @Translation("GeoJSON export"),
 *   help = @Translation("Create a GeoJSON export resource."),
 *   uses_route = TRUE,
 *   admin = @Translation("GeoJSON export"),
 *   returns_response = TRUE
 * )
 */
class GeoJsonExport extends PathPluginBase {
  /**
   * Overrides \Drupal\views\Plugin\views\display\DisplayPluginBase::$usesAJAX.
   */
  protected $usesAJAX = FALSE;

  /**
   * Overrides \Drupal\views\Plugin\views\display\DisplayPluginBase::$usesPager.
   */
  protected $usesPager = FALSE;

  /**
   * Overrides \Drupal\views\Plugin\views\display\DisplayPluginBase::$usesMore.
   */
  protected $usesMore = FALSE;

  /**
   * Overrides \Drupal\views\Plugin\views\display\DisplayPluginBase::$usesAreas.
   */
  protected $usesAreas = FALSE;

  /**
   * Overrides \Drupal\views\Plugin\views\display\DisplayPluginBase::$usesAreas.
   */
  protected $usesOptions = FALSE;

  /**
   * Overrides the content type of the data response, if needed.
   *
   * @var string
   */
  protected $contentType = 'json';

  /**
   * The mime type for the response.
   *
   * @var string
   */
  protected $mimeType;


  public function __construct(array $configuration, $plugin_id, $plugin_definition, RouteProviderInterface $route_provider, StateInterface $state) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $route_provider, $state);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('router.route_provider'),
      $container->get('state')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function initDisplay(ViewExecutable $view, array &$display, array &$options = NULL) {
    parent::initDisplay($view, $display, $options);

    $request_content_type = $this->view->getRequest()->getRequestFormat();
    // Only use the requested content type if it's not 'html'. If it is then
    // default to 'json' to aid debugging.
    // @todo Remove the need for this when we have better content negotiation.
    if ($request_content_type != 'html') {
      $this->setContentType($request_content_type);
    }

    $this->setMimeType($this->view->getRequest()
      ->getMimeType($this->contentType));

  }

  /**
   * {@inheritdoc}
   */
  protected function getType() {
    return 'data';
  }

  /**
   * {@inheritdoc}
   */
  public function usesExposed() {
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function displaysExposed() {
    return FALSE;
  }

  /**
   * Sets the request content type.
   *
   * @param string $mime_type
   *   The response mime type. E.g. 'application/json'.
   */
  public function setMimeType($mime_type) {
    $this->mimeType = $mime_type;
  }

  /**
   * Gets the mime type.
   *
   * This will return any overridden mime type, otherwise returns the mime type
   * from the request.
   *
   * @return string
   *   The response mime type. E.g. 'application/json'.
   */
  public function getMimeType() {
    return $this->mimeType;
  }

  /**
   * Sets the content type.
   *
   * @param string $content_type
   *   The content type machine name. E.g. 'json'.
   */
  public function setContentType($content_type) {
    $this->contentType = $content_type;
  }

  /**
   * Gets the content type.
   *
   * @return string
   *   The content type machine name. E.g. 'json'.
   */
  public function getContentType() {
    return $this->contentType;
  }

  /**
   * {@inheritdoc}
   */
  protected function defineOptions() {
    $options = parent::defineOptions();

    // Set the default style plugin to 'json'.
    $options['style']['contains']['type']['default'] = 'geojson';
    $options['row']['contains']['type']['default'] = 'data_field';
    $options['defaults']['default']['style'] = FALSE;
    $options['defaults']['default']['row'] = FALSE;

    // Remove css/exposed form settings, as they are not used for the data display.
    unset($options['exposed_form']);
    unset($options['exposed_block']);
    unset($options['css_class']);

    return $options;
  }

  /**
   * {@inheritdoc}
   */
  public function optionsSummary(&$categories, &$options) {
    parent::optionsSummary($categories, $options);

    unset($categories['page'], $categories['exposed']);
    // Hide some settings, as they aren't useful for pure data output.
    unset($options['show_admin_links'], $options['analyze-theme']);

    $categories['path'] = array(
      'title' => $this->t('Path settings'),
      'column' => 'second',
      'build' => array(
        '#weight' => -10,
      ),
    );

    $options['path']['category'] = 'path';
    $options['path']['title'] = $this->t('Path');

    // Remove css/exposed form settings, as they are not used for the data
    // display.
    unset($options['exposed_form']);
    unset($options['exposed_block']);
    unset($options['css_class']);
  }

  /**
   * {@inheritdoc}
   */
  public function collectRoutes(RouteCollection $collection) {
    parent::collectRoutes($collection);
    $view_id = $this->view->storage->id();
    $display_id = $this->display['id'];

    if ($route = $collection->get("view.$view_id.$display_id")) {
      $style_plugin = $this->getPlugin('style');
      // REST exports should only respond to get methods.
      $requirements = array('_method' => 'GET');

      // Add the new requirements to the route.
      $route->addRequirements($requirements);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function execute() {
    parent::execute();

    $output = $this->view->render();
    return new Response(drupal_render_root($output), 200, array('Content-type' => $this->getMimeType()));
  }

  /**
   * {@inheritdoc}
   */
  public function render() {
    $build = array();
    $build['#markup'] = $this->view->style_plugin->render();

    // Wrap the output in a pre tag if this is for a live preview.
    if (!empty($this->view->live_preview)) {
      $build['#prefix'] = '<pre>';
      $build['#markup'] = String::checkPlain($build['#markup']);
      $build['#suffix'] = '</pre>';
    }

    return $build;
  }

  /**
   * {@inheritdoc}
   *
   * The DisplayPluginBase preview method assumes we will be returning a render
   * array. The data plugin will already return the serialized string.
   */
  public function preview() {
    return $this->view->render();
  }
}

<?php

namespace Drupal\grants_handler\Plugin\Block;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultAllowed;
use Drupal\Core\Access\AccessResultForbidden;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Access\AccessResultNeutral;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Link;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Routing\CurrentRouteMatch;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Session\AccountProxy;
use Drupal\Core\Url;
use Drupal\grants_profile\GrantsProfileService;
use Drupal\helfi_helsinki_profiili\HelsinkiProfiiliUserData;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a service page block.
 *
 * @Block(
 *   id = "grants_handler_service_page_anon_block",
 *   admin_label = @Translation("Service Page Anon Block"),
 *   category = @Translation("Custom")
 * )
 */
class ServicePageAnonBlock extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * The helfi_helsinki_profiili service.
   *
   * @var \Drupal\helfi_helsinki_profiili\HelsinkiProfiiliUserData
   */
  protected HelsinkiProfiiliUserData $helfiHelsinkiProfiili;

  /**
   * Profile service.
   *
   * @var \Drupal\grants_profile\GrantsProfileService
   */
  protected GrantsProfileService $grantsProfileService;

  /**
   * Get route parameters.
   *
   * @var \Drupal\Core\Routing\CurrentRouteMatch
   */
  protected CurrentRouteMatch $routeMatch;

  /**
   * Get current user.
   *
   * @var \Drupal\Core\Session\AccountProxy
   */
  protected AccountProxy $currentUser;

  /**
   * Constructs a new ServicePageBlock instance.
   *
   * @param array $configuration
   *   The plugin configuration, i.e. an array with configuration values keyed
   *   by configuration option name. The special key 'context' may be used to
   *   initialize the defined contexts by setting it to an array of context
   *   values keyed by context names.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\helfi_helsinki_profiili\HelsinkiProfiiliUserData $helfi_helsinki_profiili
   *   The helfi_helsinki_profiili service.
   * @param \Drupal\grants_profile\GrantsProfileService $grantsProfileService
   *   Profile service.
   * @param \Drupal\Core\Routing\CurrentRouteMatch $routeMatch
   *   Get route params.
   * @param \Drupal\Core\Session\AccountProxy $user
   *   Current user.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    HelsinkiProfiiliUserData $helfi_helsinki_profiili,
    GrantsProfileService $grantsProfileService,
    CurrentRouteMatch $routeMatch,
    AccountProxy $user
    ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->helfiHelsinkiProfiili = $helfi_helsinki_profiili;
    $this->grantsProfileService = $grantsProfileService;
    $this->routeMatch = $routeMatch;
    $this->currentUser = $user;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('helfi_helsinki_profiili.userdata'),
      $container->get('grants_profile.service'),
      $container->get('current_route_match'),
      $container->get('current_user'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  protected function blockAccess(AccountInterface $account): AccessResultForbidden|AccessResultNeutral|AccessResult|AccessResultAllowed|AccessResultInterface {

    $node = $this->routeMatch->getParameter('node');

    if (!$node) {
      return AccessResult::forbidden('No referenced item');
    }

    $applicantTypes = $node->get('field_hakijatyyppi')->getValue();

    $currentRole = $this->grantsProfileService->getSelectedRoleData();
    $currentRoleType = NULL;
    if ($currentRole) {
      $currentRoleType = $currentRole['type'];
    }

    $isCorrectApplicantType = FALSE;

    foreach ($applicantTypes as $applicantType) {
      if (in_array($currentRoleType, $applicantType)) {
        $isCorrectApplicantType = TRUE;
      }
    }

    return AccessResult::allowedIf(!$isCorrectApplicantType);
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    $tOpts = ['context' => 'grants_handler'];

    $node = $this->routeMatch->getParameter('node');

    $applicantTypes = $node->get('field_hakijatyyppi')->getValue();

    $currentRole = $this->grantsProfileService->getSelectedRoleData();
    $currentRoleType = NULL;
    if ($currentRole) {
      $currentRoleType = $currentRole['type'];
    }

    $isCorrectApplicantType = FALSE;

    foreach ($applicantTypes as $applicantType) {
      if (in_array($currentRoleType, $applicantType)) {
        $isCorrectApplicantType = TRUE;
      }
    }

    $mandateUrl = Url::fromRoute(
      'grants_mandate.mandateform',
      [],
      [
        'attributes' => [
          'class' => ['hds-button', 'hds-button--primary'],
        ],
      ]
    );
    $mandateText = [
      '#theme' => 'edit-label-with-icon',
      '#icon' => 'swap-user',
      '#text_label' => $this->t('Change your role', [], $tOpts),
    ];

    $loginUrl = Url::fromRoute(
      'user.login',
      [],
      [
        'attributes' => [
          'class' => ['hds-button', 'hds-button--primary'],
        ],
      ]
    );
    $loginText = [
      '#theme' => 'edit-label-with-icon',
      '#icon' => 'user',
      '#text_label' => $this->t('Log in'),
    ];

    $link = NULL;

    if ($this->currentUser->isAuthenticated()) {
      $link = Link::fromTextAndUrl($mandateText, $mandateUrl);
      $text = $this->t('You do not have the necessary authorizations to make an application.', [], $tOpts);
    }
    else {
      $link = Link::fromTextAndUrl($loginText, $loginUrl);
      $text = $this->t('You do not have the necessary authorizations to make an application. Log in to grants service.', [], $tOpts);
    }

    $node = $this->routeMatch->getParameter('node');
    $webformArray = $node->get('field_webform')->getValue();

    if ($webformArray) {
      $webformName = $webformArray[0]['target_id'];

      $webformLink = Url::fromRoute('grants_webform_print.print_webform',
        [
          'webform' => $webformName,
        ]);
    }
    else {
      $webformLink = NULL;
    }

    $build['content'] = [
      '#theme' => 'grants_service_page_block',
      '#applicantType' => $isCorrectApplicantType,
      '#link' => $link,
      '#text' => $text,
      '#webformLink' => $webformLink,
      '#auth' => 'anon',
    ];

    return $build;
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheContexts(): array {
    // If you depends on \Drupal::routeMatch()
    // you must set context of this block with 'route' context tag.
    // Every new route this block will rebuild.
    return Cache::mergeContexts(parent::getCacheContexts(), ['route']);
  }

}

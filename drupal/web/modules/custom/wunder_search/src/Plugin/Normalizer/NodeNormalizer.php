<?php

namespace Drupal\wunder_search\Plugin\Normalizer;

use Drupal\Core\Config\ConfigFactory;
use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeManager;
use Drupal\Core\Entity\EntityTypeRepositoryInterface;
use Drupal\Core\Render\Renderer;
use Drupal\Core\Session\AccountSwitcherInterface;
use Drupal\Core\StringTranslation\TranslationManager;
use Drupal\Core\Theme\ThemeInitializationInterface;
use Drupal\Core\Theme\ThemeManagerInterface;
use Drupal\wunder_search\IndexerUserSession;
use Drupal\serialization\Normalizer\ContentEntityNormalizer;

/**
 * Normalizes / denormalizes Drupal nodes into an array structure good for ES.
 */
class NodeNormalizer extends ContentEntityNormalizer {

  /**
   * The interface or class that this Normalizer supports.
   *
   * @var array
   */
  protected $supportedInterfaceOrClass = ['Drupal\node\Entity\Node'];

  /**
   * Supported formats.
   *
   * @var array
   */
  protected $format = ['elasticsearch_helper'];

  /**
   * Config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactory
   */
  protected $configFactory;

  /**
   * Renderer.
   *
   * @var \Drupal\Core\Render\Renderer
   */
  protected $renderer;

  /**
   * Theme manager.
   *
   * @var \Drupal\Core\Theme\ThemeManagerInterface
   */
  protected $themeManager;

  /**
   * Theme initialization.
   *
   * @var \Drupal\Core\Theme\ThemeInitializationInterface
   */
  protected $themeInitialization;

  /**
   * Account switcher plugin.
   *
   * @var \Drupal\Core\Session\AccountSwitcherInterface
   */
  protected $accountSwitcher;

  /**
   * The Translation manager service.
   *
   * @var \Drupal\Core\StringTranslation\TranslationManager
   */
  protected $translationManager;

  /**
   * NodeNormalizerPluginBase constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManager $entity_type_manager
   *   Entity type manager.
   * @param \Drupal\Core\Entity\EntityTypeRepositoryInterface $entity_type_repository
   *   Entity type repository.
   * @param \Drupal\Core\Entity\EntityFieldManagerInterface $entity_field_manager
   *   Entity field manager.
   * @param \Drupal\Core\Config\ConfigFactory $config_factory
   *   Config factory.
   * @param \Drupal\Core\Render\Renderer $renderer
   *   Renderer.
   * @param \Drupal\Core\Theme\ThemeManagerInterface $theme_manager
   *   Theme manager.
   * @param \Drupal\Core\Theme\ThemeInitializationInterface $theme_initialization
   *   Theme initialization.
   * @param \Drupal\Core\Session\AccountSwitcherInterface $account_switcher
   *   Account switcher plugin.
   * @param \Drupal\Core\StringTranslation\TranslationManager $translationManager
   *   Translation manager service.
   */
  public function __construct(
    EntityTypeManager $entity_type_manager,
    EntityTypeRepositoryInterface $entity_type_repository,
    EntityFieldManagerInterface $entity_field_manager,
    ConfigFactory $config_factory,
    Renderer $renderer,
    ThemeManagerInterface $theme_manager,
    ThemeInitializationInterface $theme_initialization,
    AccountSwitcherInterface $account_switcher,
    TranslationManager $translationManager
  ) {
    parent::__construct($entity_type_manager, $entity_type_repository, $entity_field_manager);
    $this->configFactory = $config_factory;
    $this->renderer = $renderer;
    $this->themeManager = $theme_manager;
    $this->themeInitialization = $theme_initialization;
    $this->accountSwitcher = $account_switcher;
    $this->translationManager = $translationManager;
  }

  /**
   * {@inheritdoc}
   */
  public function normalize($object, $format = NULL, array $context = []): array|string|int|float|bool|\ArrayObject|NULL {
    $data = [
      'id' => $object->id(),
      'uuid' => $object->uuid(),
      'title' => $object->getTitle(),
      'status' => $object->isPublished(),
      'body' => $this->renderEntityPlainText($object, 'search_index', $context),
      'user' => [
        'name' => $object->getRevisionUser()->getAccountName(),
        'uid' => $object->getRevisionUser()->id(),
      ],
    ];

    return $data;
  }

  /**
   * Renders a content to a string.
   *
   * @param \Drupal\Core\Entity\ContentEntityBase $entity
   *   The node that needs to rendered.
   * @param string $viewmode
   *   The viewmode in which to render the entity as plain text.
   * @param array $context
   *   Context.
   *
   * @return string
   *   The rendered content as a string stripped of HTML tags.
   */
  private function renderEntityPlainText(ContentEntityBase $entity, string $viewmode, array $context) {
    $render_markup = $this->renderEntity($entity, $viewmode, $context);
    // Add some non-html spacing for improved excerpt highlighting.
    $render_markup = strtr($render_markup, [
      '</p>' => "</p>\n\n",
      '</div>' => "</div>\n\n\n",
    ]);
    return preg_replace('/\s+/', ' ', strip_tags($render_markup));
  }

  /**
   * Renders a content to a string using a given view mode.
   *
   * @param \Drupal\Core\Entity\ContentEntityBase $entity
   *   The node that needs to rendered.
   * @param string $view_mode
   *   The id of the view_mode.
   * @param array $context
   *   Context.
   *
   * @return string
   *   The rendered content as a string.
   */
  protected function renderEntity(ContentEntityBase $entity, $view_mode, array $context) {
    if (PHP_SAPI === 'cli') {
      $this->accountSwitcher->switchTo(new IndexerUserSession());
    }

    $render_markup = '';
    $current_active_theme = $this->themeManager->getActiveTheme();
    // Load the theme object for the theme.
    $frontend_theme = $this->themeInitialization->initTheme($this->configFactory->get('system.theme')->get('default'));
    // Switch the theme. This is needed as a renderer might be called from
    // hook_entity_update in a context with the backend theme active.
    // See MailsystemManager::mail() for a core example.
    $this->themeManager->setActiveTheme($frontend_theme);
    // Render the entity.
    try {
      // Get the object language.
      $langcode = $context['language'] ?? $entity->language()->getId();

      // Build a render array and render it into markup.
      $view_builder = $this->entityTypeManager
        ->getViewBuilder($entity->getEntityTypeId());
      $render_array = $view_builder->view($entity, $view_mode, $langcode);

      $render_markup = $this->renderer->renderPlain($render_array);
    }
    finally {
      // Revert the active theme, this is done inside a finally block so it is
      // executed even if an exception is thrown during rendering the entity.
      $this->themeManager->setActiveTheme($current_active_theme);

      // Revert the user session.
      if (PHP_SAPI === 'cli') {
        $this->accountSwitcher->switchBack();
      }
    }
    return $render_markup;
  }

}

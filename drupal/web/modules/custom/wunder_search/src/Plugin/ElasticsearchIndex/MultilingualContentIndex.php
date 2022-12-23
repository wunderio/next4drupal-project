<?php

namespace Drupal\wunder_search\Plugin\ElasticsearchIndex;

use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\elasticsearch_helper\Elasticsearch\Index\FieldDefinition;
use Drupal\elasticsearch_helper\Elasticsearch\Index\MappingDefinition;
use Drupal\elasticsearch_helper\ElasticsearchLanguageAnalyzer;
use Drupal\elasticsearch_helper\Event\ElasticsearchOperations;
use Drupal\elasticsearch_helper\Plugin\ElasticsearchIndexBase;
use Elasticsearch\Client;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Serializer\Serializer;

/**
 * Multilingual index.
 *
 * @ElasticsearchIndex(
 *   id = "content_index",
 *   label = @Translation("Multilingual content index"),
 *   indexName = "content-{langcode}",
 *   entityType = "node"
 * )
 */
class MultilingualContentIndex extends ElasticsearchIndexBase {

  /**
   * The language manager instance.
   *
   * @var \Drupal\Core\Language\LanguageManagerInterface
   */
  protected $languageManager;

  /**
   * MultilingualContentIndex constructor.
   *
   * @param array $configuration
   *   Configuration.
   * @param string $plugin_id
   *   Plugin id.
   * @param array $plugin_definition
   *   Plugin definition.
   * @param \Elasticsearch\Client $client
   *   The elasticsearch client.
   * @param \Symfony\Component\Serializer\Serializer $serializer
   *   The serializer.
   * @param \Psr\Log\LoggerInterface $logger
   *   Logger interface.
   * @param \Drupal\Core\Language\LanguageManagerInterface $language_manager
   *   Language manager interface.
   */
  public function __construct(array $configuration, $plugin_id, array $plugin_definition, Client $client, Serializer $serializer, LoggerInterface $logger, LanguageManagerInterface $language_manager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $client, $serializer, $logger);

    $this->languageManager = $language_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('elasticsearch_helper.elasticsearch_client'),
      $container->get('serializer'),
      $container->get('logger.factory')->get('elasticsearch_helper'),
      $container->get('language_manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function serialize($source, $context = []) {
    $data = parent::serialize($source, $context);

    // Add the language code to be used as a token.
    $data['langcode'] = $source->language()->getId();

    return $data;
  }

  /**
   * {@inheritdoc}
   */
  public function index($source) {
    foreach ($source->getTranslationLanguages() as $langcode => $language) {
      $translation = $source->getTranslation($langcode);
      parent::index($translation);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function delete($source) {
    foreach ($source->getTranslationLanguages() as $langcode => $language) {
      $translation = $source->getTranslation($langcode);
      parent::delete($translation);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function setup() {
    try {
      // Create one index per language, so that we can have different analyzers.
      foreach ($this->languageManager->getLanguages() as $langcode => $language) {
        // Get index name.
        $index_name = $this->getIndexName(['langcode' => $langcode]);

        // Check if index exists.
        if (!$this->client->indices()->exists(['index' => $index_name])) {
          // Get index definition.
          $index_definition = $this->getIndexDefinition(['langcode' => $langcode]);

          // Get analyzer for the language.
          $analyzer = ElasticsearchLanguageAnalyzer::get($langcode);

          // Put analyzer parameter to all "text" fields in the mapping.
          foreach ($index_definition->getMappingDefinition()->getProperties() as $property) {
            if ($property->getDataType()->getType() == 'text') {
              $property->addOption('analyzer', $analyzer);
            }
          }

          $this->createIndex($index_name, $index_definition);
        }
      }
    }
    catch (\Throwable $e) {
      $request_wrapper = $request_wrapper ?? NULL;
      $this->dispatchOperationErrorEvent($e, ElasticsearchOperations::INDEX_CREATE, $request_wrapper);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getIndexDefinition(array $context = []) {
    // Get index definition.
    $index_definition = parent::getIndexDefinition($context);

    // Get analyzer for the language.
    $analyzer = ElasticsearchLanguageAnalyzer::get($context['langcode']);

    // Add custom settings.
    $index_definition->getSettingsDefinition()->addOptions([
      'analysis' => [
        'analyzer' => [
          $analyzer => [
            'tokenizer' => 'standard',
          ],
        ],
      ],
    ]);

    return $index_definition;
  }

  /**
   * {@inheritdoc}
   */
  public function getMappingDefinition(array $context = []) {
    $user_property = FieldDefinition::create('object')
      ->addProperty('uid', FieldDefinition::create('integer'))
      ->addProperty('name', FieldDefinition::create('keyword'));

    return MappingDefinition::create()
      ->addProperty('id', FieldDefinition::create('integer'))
      ->addProperty('uuid', FieldDefinition::create('keyword'))
      ->addProperty('title', FieldDefinition::create('text'))
      ->addProperty('status', FieldDefinition::create('keyword'))
      ->addProperty('body', FieldDefinition::create('text'))
      ->addProperty('user', $user_property);
  }

}

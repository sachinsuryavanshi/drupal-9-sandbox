<?php

namespace Drupal\Tests\salesforce_mapping\Functional;

use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\datetime\Plugin\Field\FieldType\DateTimeItemInterface;
use Drupal\KernelTests\KernelTestBase;
use Drupal\node\Entity\Node;
use Drupal\salesforce_mapping\Entity\MappedObject;
use Drupal\salesforce_mapping\Entity\SalesforceMapping;
use Drupal\salesforce_mapping\PushParams;
use Drupal\Tests\BrowserTestBase;
use DateTime;

/**
 * Test that PushParams correctly creates data structures for Salesforce.
 *
 * @group salesforce_mapping
 */
class PushParamsTest extends BrowserTestBase {

  /**
   * Default theme required for D9.
   *
   * @var string
   */
  protected $defaultTheme  = 'stark';

  /**
   * Required modules.
   *
   * @var array
   */
  protected static $modules = [
    'typed_data',
    'options',
    'dynamic_entity_reference',
    'salesforce',
    'salesforce_mapping',
    'salesforce_push',
    'salesforce_pull',
    'salesforce_mapping_test',
  ];

  /**
   * Test PushParams instantiation, where all the work gets done.
   */
  public function testPushParams() {
    date_default_timezone_set('America/New_York');
    $mapping = SalesforceMapping::load('test_mapping');
    $storedDate = date(DateTimeItemInterface::DATETIME_STORAGE_FORMAT, \Drupal::time()->getRequestTime());

    // Entity 1 is the target reference.
    $entity1 = Node::create([
      'type' => 'salesforce_mapping_test_content',
      'title' => 'Test Example',
    ]
    );
    $entity1->save();

    // Mapped Object to be used for RelatedIDs push params property.
    $mappedObject = \Drupal::entityTypeManager()
      ->getStorage('salesforce_mapped_object')
      ->loadByEntityAndMapping($entity1, $mapping);

    $mappedObject->set('salesforce_id', '0123456789ABCDEFGH');
    $mappedObject->save();

    // Entity 2 to be mapped to Salesforce.
    $entity2 = Node::create([
      'type' => 'salesforce_mapping_test_content',
      'title' => 'Test Example 2',
      'field_salesforce_test_bool' => 1,
      'field_salesforce_test_date' => $storedDate,
      'field_salesforce_test_email' => 'test2@example.com',
      'field_salesforce_test_link' => 'https://example.com',
      'field_salesforce_test_reference' => $entity1,
      'field_salesforce_test_multi' => [['value' => 'Value 1'], ['value' => 'Value 2'], ['value' => 'Value 3']],
    ]);
    $entity2->save();

    $expectedDate = new DrupalDateTime($storedDate, 'UTC');

    // Create a PushParams and assert it's created as we expect.
    $pushParams = new PushParams($mapping, $entity2);
    $expected = [
      'FirstName' => 'SALESFORCE TEST',
      'Email' => 'test2@example.com',
      'Birthdate' => $expectedDate->format('Y-m-d\TH:i:sO'),
      'd5__Do_Not_Mail__c' => TRUE,
      'ReportsToId' => '0123456789ABCDEFGH',
      'RecordTypeId' => '012i0000001B15mAAC',
      'Description' => 'https://example.com',
      'd5__Multipicklist_Test__c' => 'Value 1;Value 2;Value 3'
    ];
    $actual = $pushParams->getParams();
    ksort($actual);
    ksort($expected);
    $this->assertEquals($expected, $actual);
  }

  /**
   * Test PushParams instantiation with blank date.
   */
  public function testPushEmptyDate() {
    date_default_timezone_set('America/New_York');
    $mapping = SalesforceMapping::load('test_mapping');

    // Entity 1 is the target reference.
    $entity1 = Node::create([
      'type' => 'salesforce_mapping_test_content',
      'title' => 'Test Example',
    ]);
    $entity1->save();

    // Mapped Object to be used for RelatedIDs push params property.
    $mappedObject = \Drupal::entityTypeManager()
      ->getStorage('salesforce_mapped_object')
      ->loadByEntityAndMapping($entity1, $mapping);

    $mappedObject->set('salesforce_id', '0123456789ABCDEFGH');
    $mappedObject->save();

    // Entity 2 to be mapped to Salesforce.
    $entity2 = Node::create([
      'type' => 'salesforce_mapping_test_content',
      'title' => 'Test Example 2',
      'field_salesforce_test_bool' => 1,
      'field_salesforce_test_date' => '',
      'field_salesforce_test_email' => 'test2@example.com',
      'field_salesforce_test_link' => 'https://example.com',
      'field_salesforce_test_reference' => $entity1,
      'field_salesforce_test_multi' => ['Value 1', 'Value 2', 'Value 3'],
    ]);
    $entity2->save();

    // Create a PushParams and assert it's created as we expect.
    $pushParams = new PushParams($mapping, $entity2);
    $expected = [
      'FirstName' => 'SALESFORCE TEST',
      'Email' => 'test2@example.com',
      'Birthdate' => null,
      'd5__Do_Not_Mail__c' => TRUE,
      'ReportsToId' => '0123456789ABCDEFGH',
      'RecordTypeId' => '012i0000001B15mAAC',
      'Description' => 'https://example.com',
      'd5__Multipicklist_Test__c' => 'Value 1;Value 2;Value 3'
    ];
    $actual = $pushParams->getParams();
    ksort($actual);
    ksort($expected);
    $this->assertEquals($expected, $actual);
  }

}

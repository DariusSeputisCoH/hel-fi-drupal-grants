<?php

namespace Drupal\grants_profile\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\TypedData\TypedDataManager;
use Drupal\grants_profile\TypedData\Definition\GrantsProfileDefinition;
use Drupal\multivalue_form_element\Element\MultiValue;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a Grants Profile form.
 */
class GrantsProfileForm extends FormBase {

  /**
   * Drupal\Core\TypedData\TypedDataManager definition.
   *
   * @var \Drupal\Core\TypedData\TypedDataManager
   */
  protected TypedDataManager $typedDataManager;

  /**
   * Constructs a new AddressForm object.
   */
  public function __construct(TypedDataManager $typed_data_manager) {
    $this->typedDataManager = $typed_data_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): GrantsProfileForm|static {
    return new static(
      $container->get('typed_data_manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'grants_profile_grants_profile';
  }

  /**
   * {@inheritdoc}
   * @throws \Drupal\Core\TypedData\Exception\ReadOnlyException
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {

    /** @var \Drupal\grants_profile\GrantsProfileService $grantsProfileService */
    $grantsProfileService = \Drupal::service('grants_profile.service');
    $selectedCompany = $grantsProfileService->getSelectedCompany();
    $grantsProfileContent = $grantsProfileService->getGrantsProfileContent($selectedCompany, true);

    $form_state->setStorage(['grantsProfileContent' => $grantsProfileContent]);

    $form['foundingYear'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Founding year'),
      '#required' => TRUE,
      '#default_value' => $grantsProfileContent['foundingYear'],
    ];
    $form['companyNameShort'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Company short name'),
      '#required' => TRUE,
      '#default_value' => $grantsProfileContent['companyNameShort'],
    ];
    $form['companyHomePage'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Company www address'),
      '#required' => TRUE,
      '#default_value' => $grantsProfileContent['companyHomePage'],
    ];
    $form['companyEmail'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Company email'),
      '#required' => TRUE,
      '#default_value' => $grantsProfileContent['companyEmail'],
    ];
    $form['businessPurpose'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Business Purpose'),
      '#required' => TRUE,
      '#default_value' => $grantsProfileContent['businessPurpose'],
    ];

    $adressMarkup = '<ul>';
    foreach ($grantsProfileContent["addresses"] as $key => $address) {
      $adressMarkup .= '<li><a href="/grants-profile/address/'.$key.'">' . $address['street'] . '</a></li>';
    }
    $adressMarkup .= '</ul>';

    $form['address_markup'] = [
      '#type' => 'markup',
      '#markup' => $adressMarkup,
      '#suffix' => '<div><a href="/grants-profile/address/new">New Address</a></div>'
    ];

    $bankAccountMarkup = '<ul>';
    foreach ($grantsProfileContent["bankAccounts"] as $key => $address) {
      $bankAccountMarkup .= '<li><a href="/grants-profile/bank-accounts/'.$key.'">' . $address['bankAccount'] . '</a></li>';
    }
    $bankAccountMarkup .= '</ul>';

    $form['bankAccount_markup'] = [
      '#type' => 'markup',
      '#markup' => $bankAccountMarkup,
      '#suffix' => '<div><a href="/grants-profile/bank-accounts/new">New Bank account</a></div>'
    ];

    $officialsMarkup = '<ul>';
    foreach ($grantsProfileContent["officials"] as $key => $address) {
      $officialsMarkup .= '<li><a href="/grants-profile/application-officials/'.$key.'">' . $address['name'] . '</a></li>';
    }
    $officialsMarkup .= '</ul>';

    $form['officials_markup'] = [
      '#type' => 'markup',
      '#markup' => $officialsMarkup,
      '#suffix' => '<div><a href="/grants-profile/application-officials/new">New official</a></div>'
    ];

//    $form['addresses'] = [
//      '#type' => 'multivalue',
//      '#title' => $this->t('Addresses'),
//      '#cardinality' => MultiValue::CARDINALITY_UNLIMITED,
//      '#default_value' => $grantsProfile['addresses'],
//      'street' => [
//        '#type' => 'textfield',
//        '#title' => $this->t('Street'),
//        '#required' => TRUE,
//      ],
//      'city' => [
//        '#type' => 'textfield',
//        '#title' => $this->t('City'),
//        '#required' => TRUE,
//      ],
//      'postCode' => [
//        '#type' => 'textfield',
//        '#title' => $this->t('Post code'),
//        '#required' => TRUE,
//      ],
//      'country' => [
//        '#type' => 'textfield',
//        '#title' => $this->t('City'),
//        '#required' => TRUE,
//      ],
//      'address_id' => [
//        '#type' => 'hidden',
//        '#value' => 124,
//      ],
//    ];
//
//    $form['bankAccounts'] = [
//      '#type' => 'multivalue',
//      '#title' => $this->t('Bank accounts'),
//      '#cardinality' => MultiValue::CARDINALITY_UNLIMITED,
//      '#default_value' => $grantsProfile['bankAccounts'],
//      'bankAccount' => [
//        '#type' => 'textfield',
//        '#title' => $this->t('Bank account'),
//        '#required' => TRUE,
//      ],
//      'bankAccount_id' => [
//        '#type' => 'hidden',
//        '#value' => 123,
//      ],
//    ];
//
//    $form['officials'] = [
//      '#type' => 'multivalue',
//      '#title' => $this->t('Application officials'),
//      '#cardinality' => MultiValue::CARDINALITY_UNLIMITED,
////      '#default_value' => $grantsProfile['officials'],
////      '#max_delta' => MultiValue::CARDINALITY_UNLIMITED,
//      'name' => [
//        '#type' => 'textfield',
//        '#title' => $this->t('Name'),
//        '#required' => TRUE,
//      ],
//      'role' => [
//        '#type' => 'select',
//        '#title' => $this->t('Role'),
//        '#required' => TRUE,
//        '#options' => [
//          1 => $this->t('Puheenjohtaja'),
//          2 => $this->t('Taloudesta vastaava'),
//          3 => $this->t('Sihteeri'),
//          4 => $this->t('Toiminnanjohtaja'),
//          5 => $this->t('Varapuheenjohtaja'),
//          6 => $this->t('Muu'),
//        ],
//      ],
//      'email' => [
//        '#type' => 'textfield',
//        '#title' => $this->t('Email'),
//        '#required' => TRUE,
//      ],
//      'phone' => [
//        '#type' => 'textfield',
//        '#title' => $this->t('Phone'),
//        '#required' => TRUE,
//      ],
//      'official_id' => [
//        '#type' => 'hidden',
//        '#value' => 123,
//      ],
//    ];



    $form['actions'] = [
      '#type' => 'actions',
    ];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Send'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {

    $storage = $form_state->getStorage();
    if (!isset($storage['grantsProfileContent'])) {
      $this->messenger()->addError($this->t('grantsProfileContent not found!'));
      return;
    }

    $grantsProfileContent = $storage['grantsProfileContent'];

    $values = $form_state->getValues();

    foreach ($grantsProfileContent as $key => $value) {
      if (array_key_exists($key, $values)) {
        $grantsProfileContent[$key] = $values[$key];
      }
    }

    // TODO: täytyy laittaa storageen tuo profile

    $grantsProfileDefinition = GrantsProfileDefinition::create('grants_profile_profile');
    // Create data object.
    $grantsProfileData = $this->typedDataManager->create($grantsProfileDefinition);
    $grantsProfileData->setValue($grantsProfileContent);
    // Validate inserted data.
    $violations = $grantsProfileData->validate();
    // If there's violations in data.
    if ($violations->count() != 0) {
      foreach ($violations as $violation) {
        // Print errors by form item name.
        $form_state->setErrorByName(
          $violation->getPropertyPath(),
          $violation->getMessage());
      }
    }
    else {
      // Move addressData object to form_state storage.
      $form_state->setStorage(['grantsProfileData' => $grantsProfileData]);
    }

    $d = 'asdf';

  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->messenger()->addStatus($this->t('The message has been sent.'));

    $storage = $form_state->getStorage();
    if (!isset($storage['grantsProfileData'])) {
      $this->messenger()->addError($this->t('grantsProfileData not found!'));
      return;
    }

    $grantsProfileData = $storage['grantsProfileData'];

    /** @var \Drupal\grants_profile\GrantsProfileService $grantsProfileService */
    $grantsProfileService = \Drupal::service('grants_profile.service');

    $profileDataArray = $grantsProfileData->toArray();

    $grantsProfileService->saveGrantsProfile($profileDataArray);

    $success = $grantsProfileService->saveGrantsProfileAtv();


    $d = 'asdf';


    //    $form_state->setRedirect('<front>');
  }

}

<?php

namespace Drupal\grants_profile;

use Drupal\Core\Http\RequestStack;
use Drupal\Core\Logger\LoggerChannel;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Core\Logger\LoggerChannelFactory;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\grants_handler\ApplicationHandler;
use Drupal\grants_metadata\AtvSchema;
use Drupal\helfi_atv\AtvDocument;
use Drupal\helfi_atv\AtvDocumentNotFoundException;
use Drupal\helfi_atv\AtvService;
use Drupal\helfi_helsinki_profiili\HelsinkiProfiiliUserData;
use Drupal\helfi_yjdh\Exception\YjdhException;
use Drupal\helfi_yjdh\YjdhClient;

/**
 * Handle all profile functionality.
 */
class GrantsProfileService {

  use StringTranslationTrait;

  const DOCUMENT_STATUS_NEW = 'DRAFT';

  const DOCUMENT_STATUS_SAVED = 'READY';

  /**
   * The helfi_atv service.
   *
   * @var \Drupal\helfi_atv\AtvService
   */
  protected AtvService $atvService;

  /**
   * Request stack for session access.
   *
   * @var \Drupal\Core\Http\RequestStack
   */
  protected RequestStack $requestStack;

  /**
   * The Messenger service.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected MessengerInterface $messenger;

  /**
   * The Messenger service.
   *
   * @var \Drupal\helfi_helsinki_profiili\HelsinkiProfiiliUserData
   */
  protected HelsinkiProfiiliUserData $helsinkiProfiili;

  /**
   * ATV Schema mapper.
   *
   * @var \Drupal\grants_metadata\AtvSchema
   */
  protected AtvSchema $atvSchema;

  /**
   * Access to YTJ / Yrtti.
   *
   * @var \Drupal\helfi_yjdh\YjdhClient
   */
  protected YjdhClient $yjdhClient;

  /**
   * Logger.
   *
   * @var \Drupal\Core\Logger\LoggerChannelFactory|\Drupal\Core\Logger\LoggerChannelInterface|\Drupal\Core\Logger\LoggerChannel
   */
  protected LoggerChannelFactory|LoggerChannelInterface|LoggerChannel $logger;

  /**
   * Constructs a GrantsProfileService object.
   *
   * @param \Drupal\helfi_atv\AtvService $helfi_atv
   *   The helfi_atv service.
   * @param \Drupal\Core\Http\RequestStack $requestStack
   *   Storage factory for temp store.
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   Show messages to user.
   * @param \Drupal\helfi_helsinki_profiili\HelsinkiProfiiliUserData $helsinkiProfiiliUserData
   *   Access to Helsinki profiili data.
   * @param \Drupal\grants_metadata\AtvSchema $atv_schema
   *   Atv chema mapper.
   * @param \Drupal\helfi_yjdh\YjdhClient $yjdhClient
   *   Access to yjdh data.
   * @param \Drupal\Core\Logger\LoggerChannelFactory $loggerFactory
   *   Logger service.
   */
  public function __construct(
    AtvService $helfi_atv,
    RequestStack $requestStack,
    MessengerInterface $messenger,
    HelsinkiProfiiliUserData $helsinkiProfiiliUserData,
    AtvSchema $atv_schema,
    YjdhClient $yjdhClient,
    LoggerChannelFactory $loggerFactory
  ) {
    $this->atvService = $helfi_atv;
    $this->requestStack = $requestStack;
    $this->messenger = $messenger;
    $this->helsinkiProfiili = $helsinkiProfiiliUserData;
    $this->atvSchema = $atv_schema;
    $this->yjdhClient = $yjdhClient;
    $this->logger = $loggerFactory->get('helfi_atv');
  }

  /**
   * Create new profile to be saved to ATV.
   *
   * @param array $data
   *   Data for the new profile document.
   *
   * @return Drupal\helfi_atv\AtvDocument
   *   New profile
   */
  public function newProfile(array $data): AtvDocument {

    $newProfileData = [];
    $selectedCompanyArray = $this->getSelectedCompany();
    $selectedCompany = $selectedCompanyArray['identifier'];
    $userData = $this->helsinkiProfiili->getUserData();

    // If data is already in profile format, use that as is.
    if (isset($data['content'])) {
      $newProfileData = $data;
    }
    else {
      // Or create new content field.
      $newProfileData['content'] = $data;
    }

    $newProfileData['type'] = 'grants_profile';
    $newProfileData['business_id'] = $selectedCompany;
    $newProfileData['user_id'] = $userData["sub"];
    $newProfileData['status'] = self::DOCUMENT_STATUS_NEW;
    $newProfileData['deletable'] = TRUE;

    $newProfileData['tos_record_id'] = $this->newProfileTosRecordId();
    $newProfileData['tos_function_id'] = $this->newProfileTosFunctionId();

    $newProfileData['metadata'] = [
      'business_id' => $selectedCompany,
      'appenv' => ApplicationHandler::getAppEnv(),
    ];

    return $this->atvService->createDocument($newProfileData);
  }

  /**
   * Transaction ID for new profile.
   *
   * @return string
   *   Transaction ID
   *
   * @todo Maybe these are Document level stuff?
   *
   * @todo This can probaably be hardcoded.
   */
  protected function newTransactionId($transactionId): string {
    return md5($transactionId);
  }

  /**
   * TOS ID.
   *
   * @return string
   *   TOS id
   *
   * @todo Maybe these are Document level stuff?
   */
  protected function newProfileTosRecordId(): string {
    return 'eb30af1d9d654ebc98287ca25f231bf6';
  }

  /**
   * Function Id.
   *
   * @return string
   *   New function ID.
   *
   * @todo Maybe these are Document level stuff?
   */
  protected function newProfileTosFunctionId(): string {
    return 'eb30af1d9d654ebc98287ca25f231bf6';
  }

  /**
   * Format data from tempstore & save document back to ATV.
   *
   * @return bool|AtvDocument
   *   Did save succeed?
   *
   * @throws \Drupal\helfi_atv\AtvDocumentNotFoundException
   * @throws \Drupal\helfi_atv\AtvFailedToConnectException
   * @throws \GuzzleHttp\Exception\GuzzleException
   */
  public function saveGrantsProfile(array $documentContent): bool|AtvDocument {
    // Get selected company.
    $selectedCompany = $this->getSelectedCompany();
    // Get grants profile.
    $grantsProfileDocument = $this->getGrantsProfile($selectedCompany['identifier'], TRUE);

    // Make sure business id is saved.
    $documentContent['businessId'] = $selectedCompany['identifier'];

    $transactionId = $this->newTransactionId(time());

    if ($grantsProfileDocument == NULL) {
      $newGrantsProfileDocument = $this->newProfile($documentContent);
      $newGrantsProfileDocument->setStatus(self::DOCUMENT_STATUS_SAVED);
      $newGrantsProfileDocument->setTransactionId($transactionId);

      $this->logger->info('Grants profile POSTed, transactionID: %transId', ['%transId' => $transactionId]);
      return $this->atvService->postDocument($newGrantsProfileDocument);
    }
    else {

      foreach ($documentContent['bankAccounts'] as $key => $bank_account) {
        unset($documentContent['bankAccounts'][$key]['confirmationFileName']);
      }

      $payloadData = [
        'content' => $documentContent,
        'metadata' => $grantsProfileDocument->getMetadata(),
        'transaction_id' => $transactionId,
      ];
      $this->logger->info('Grants profile PATCHed, transactionID: %transactionId', ['%transactionId' => $transactionId]);
      return $this->atvService->patchDocument($grantsProfileDocument->getId(), $payloadData);
    }
  }

  /**
   * Save address to session.
   *
   * @param string $address_id
   *   Address id in store.
   * @param array $address
   *   Address array.
   *
   * @return bool
   *   Return result.
   *
   * @throws \GuzzleHttp\Exception\GuzzleException
   */
  public function saveAddress(string $address_id, array $address): bool {
    $selectedCompany = $this->getSelectedCompany();
    $profileContent = $this->getGrantsProfileContent($selectedCompany['identifier']);
    $addresses = (isset($profileContent['addresses']) && $profileContent['addresses'] !== NULL) ? $profileContent['addresses'] : [];

    // Reset keys.
    $addresses = array_values($addresses);

    if ($address_id == 'new') {
      $nextId = count($addresses);
    }
    else {
      $nextId = $address_id;
    }

    $addresses[$nextId] = $address;
    $profileContent['addresses'] = $addresses;
    return $this->setToCache($selectedCompany['identifier'], $profileContent);
  }

  /**
   * Remove address from profile & CACHE.
   *
   * @param string $address_id
   *   Address id in store.
   *
   * @return bool
   *   If success.
   *
   * @throws \GuzzleHttp\Exception\GuzzleException
   */
  public function removeAddress(string $address_id): bool {
    $selectedCompany = $this->getSelectedCompany();
    $profileContent = $this->getGrantsProfileContent($selectedCompany['identifier']);
    $addresses = (isset($profileContent['addresses']) && $profileContent['addresses'] !== NULL) ? $profileContent['addresses'] : [];

    $profileContent['addresses'] = array_filter($addresses, function ($address) use ($address_id) {
      return $address['address_id'] !== $address_id;
    });
    return $this->setToCache($selectedCompany['identifier'], $profileContent);
  }

  /**
   * Delete attachment from selected company's grants profile document.
   *
   * @param string $selectedCompany
   *   Selected company.
   * @param string $attachmentId
   *   Attachment to delete.
   *
   * @return \Drupal\helfi_atv\AtvDocument|bool|array
   *   Return value varies.
   *
   * @throws \Drupal\helfi_atv\AtvDocumentNotFoundException
   * @throws \Drupal\helfi_atv\AtvFailedToConnectException
   * @throws \GuzzleHttp\Exception\GuzzleException
   */
  public function deleteAttachment(string $selectedCompany, string $attachmentId): AtvDocument|bool|array {
    $grantsProfile = $this->getGrantsProfile($selectedCompany);
    return $this->atvService->deleteAttachment($grantsProfile->getId(), $attachmentId);
  }

  /**
   * Get address from store.
   *
   * @param string $address_id
   *   Address id to fetch.
   * @param bool $refetch
   *   Refetch data from ATV.
   *
   * @return string[]
   *   Array containing address or new address
   *
   * @throws \GuzzleHttp\Exception\GuzzleException
   */
  public function getAddress(string $address_id, $refetch = FALSE): array {
    $selectedCompany = $this->tempStore->get('selected_company');
    $profileContent = $this->getGrantsProfileContent($selectedCompany['identifier'], $refetch);

    if (isset($profileContent["addresses"][$address_id])) {
      return $profileContent["addresses"][$address_id];
    }
    else {
      return [
        'street' => '',
        'city' => '',
        'postCode' => '',
        'country' => '',
      ];
    }
  }

  /**
   * Get official data from store.
   *
   * @param string $official_id
   *   ID to get.
   *
   * @return string[]
   *   Array containing official data
   */
  public function getOfficial(string $official_id): array {
    $selectedCompany = $this->getSelectedCompany();
    $profileContent = $this->getGrantsProfileContent($selectedCompany['identifier']);

    if (isset($profileContent['officials'][$official_id])) {
      return $profileContent['officials'][$official_id];
    }
    else {
      return [
        'name' => '',
        'role' => '',
        'email' => '',
        'phone' => '',
      ];
    }
  }

  /**
   * Get bank account data from store.
   *
   * @param string $bank_account_id
   *   ID to get.
   *
   * @return string[]
   *   Array containing official data
   *
   * @throws \GuzzleHttp\Exception\GuzzleException
   */
  public function getBankAccount(string $bank_account_id): array {
    $selectedCompany = $this->getSelectedCompany();
    $profileContent = $this->getGrantsProfileContent($selectedCompany['identifier']);

    $bankAccount = array_filter($profileContent["bankAccounts"], fn($account) => $account['bank_account_id'] == $bank_account_id);

    if (!empty($bankAccount)) {
      return reset($bankAccount);
    }
    else {
      return [
        'bankAccount' => '',
      ];
    }
  }

  /**
   * SAve official to store + ATV.
   *
   * @param string $official_id
   *   Id to save, "new" if adding a new.
   * @param array $official
   *   Data to be saved.
   *
   * @return bool
   *   success or not?
   *
   * @throws \GuzzleHttp\Exception\GuzzleException
   */
  public function saveOfficial(string $official_id, array $official) {
    $selectedCompany = $this->getSelectedCompany();
    $profileContent = $this->getGrantsProfileContent($selectedCompany['identifier']);
    $officials = (isset($profileContent['officials']) && $profileContent['officials'] !== NULL) ? $profileContent['officials'] : [];

    // Reset keys.
    $officials = array_values($officials);

    if ($official_id == 'new') {
      $nextId = count($officials);
    }
    else {
      $nextId = $official_id;
    }

    $officials[$nextId] = $official;
    $profileContent['officials'] = $officials;
    return $this->setToCache($selectedCompany['identifier'], $profileContent);
  }

  /**
   * Remove official from profile data & save to CACHE.
   *
   * @param string $official_id
   *   Id to save, "new" if adding a new.
   *
   * @return bool
   *   Is the removal successful.
   *
   * @throws \GuzzleHttp\Exception\GuzzleException
   */
  public function removeOfficial(string $official_id) {
    $selectedCompany = $this->getSelectedCompany();
    $profileContent = $this->getGrantsProfileContent($selectedCompany['identifier']);
    $officials = (isset($profileContent['officials']) && $profileContent['officials'] !== NULL) ? $profileContent['officials'] : [];

    unset($officials[(int) $official_id]);

    $profileContent['officials'] = array_filter($officials, function ($official) use ($official_id) {
      return $official['official_id'] !== $official_id;
    });
    return $this->setToCache($selectedCompany['identifier'], $profileContent);
  }

  /**
   * Save bank account to ATV.
   *
   * @param string $bank_account_id
   *   Id to save, "new" if adding a new.
   * @param array $bank_account
   *   Data to be saved.
   *
   * @throws \GuzzleHttp\Exception\GuzzleException
   */
  public function saveBankAccount(string $bank_account_id, array $bank_account) {
    $selectedCompany = $this->getSelectedCompany();
    $profileContent = $this->getGrantsProfileContent($selectedCompany['identifier']);
    $bankAccounts = (isset($profileContent['bankAccounts']) && $profileContent['bankAccounts'] !== NULL) ? $profileContent['bankAccounts'] : [];

    // Reset keys.
    $bankAccounts = array_values($bankAccounts);

    if ($bank_account_id == 'new') {
      $nextId = count($bankAccounts);
    }
    else {
      $nextId = $bank_account_id;
    }

    $bankAccounts[$nextId] = $bank_account;
    $profileContent['bankAccounts'] = $bankAccounts;
    $this->setToCache($selectedCompany['identifier'], $profileContent);
  }

  /**
   * Remove bank account from profile data.
   *
   * @param string $bank_account_id
   *   Id to save, "new" if adding a new.
   */
  public function removeBankAccount(string $bank_account_id): void {
    $selectedCompany = $this->getSelectedCompany();
    $profileContent = $this->getGrantsProfileContent($selectedCompany['identifier']);
    $bankAccounts = (isset($profileContent['bankAccounts']) && $profileContent['bankAccounts'] !== NULL) ? $profileContent['bankAccounts'] : [];

    $profileContent['bankAccounts'] = [];

    foreach ($bankAccounts as $bankAccount) {

      if ($bankAccount['bank_account_id'] !== $bank_account_id) {

        $profileContent['bankAccounts'][] = $bankAccount;

      }
      else {

        // Delete attachment from Atv.
        $grantsProfile = $this->getGrantsProfile($selectedCompany['identifier']);
        $attachment = $grantsProfile->getAttachmentForFilename($bankAccount['confirmationFile']);

        try {
          $this->deleteAttachment($selectedCompany['identifier'], $attachment['id']);

          $this->logger->debug('Attachment deletion success: %id.',
            [
              '%id' => $bankAccount['bank_account_id'],
            ]
          );
        }
        catch (\Exception $e) {
          $this->logger->debug('Attachment deletion failed: %id.',
            [
              '%id' => $bankAccount['bank_account_id'],
            ]
                  );
        }

      }

    }
    $this->setToCache($selectedCompany['identifier'], $profileContent);
  }

  /**
   * Make sure we have needed fields in our profile document.
   *
   * @param string $businessId
   *   Business id for profile data.
   * @param array $profileContent
   *   Profile content.
   *
   * @return array
   *   Profile content with required fields.
   *
   * @throws \Drupal\helfi_yjdh\Exception\YjdhException
   */
  public function initGrantsProfile(string $businessId, array $profileContent): array {
    // Try to get association details.
    $assosiationDetails = $this->yjdhClient->getAssociationBasicInfo($businessId);
    // If they're available, use them.
    if (!empty($assosiationDetails)) {
      $profileContent["companyName"] = $assosiationDetails["AssociationNameInfo"][0]["AssociationName"];
      $profileContent["businessId"] = $assosiationDetails["BusinessId"];
      $profileContent["companyStatus"] = $assosiationDetails["AssociationStatus"];
      $profileContent["companyStatusSpecial"] = $assosiationDetails["AssociationSpecialCondition"];
      $profileContent["registrationDate"] = $assosiationDetails["RegistryDate"];
      $profileContent["companyHome"] = $assosiationDetails["Address"][0]["City"];
    }
    else {
      try {
        // If not, get company details and use them.
        $companyDetails = $this->yjdhClient->getCompany($businessId);

      }
      catch (\Exception $e) {
        $companyDetails = NULL;
      }

      if ($companyDetails == NULL) {
        throw new YjdhException('Company details not found');
      }

      if (!$companyDetails["TradeName"]["Name"]) {
        throw new YjdhException('Company name not set, cannot proceed');
      }
      if (!$companyDetails["BusinessId"]) {
        throw new YjdhException('Company BusinessId not set, cannot proceed');
      }

      $profileContent["companyName"] = $companyDetails["TradeName"]["Name"];
      $profileContent["businessId"] = $companyDetails["BusinessId"];
      $profileContent["companyStatus"] = $companyDetails["CompanyStatus"]["Status"]["PrimaryCode"] ?? '-';
      $profileContent["companyStatusSpecial"] = $companyDetails["CompanyStatus"]["Status"]["SecondaryCode"] ?? '-';
      $profileContent["registrationDate"] = $companyDetails["RegistrationHistory"]["RegistryEntry"][0]["RegistrationDate"] ?? '-';
      $profileContent["companyHome"] = $companyDetails["PostalAddress"]["DomesticAddress"]["City"] ?? '-';

    }

    if (!isset($profileContent['foundingYear'])) {
      $profileContent['foundingYear'] = NULL;
    }
    if (!isset($profileContent['companyNameShort'])) {
      $profileContent['companyNameShort'] = NULL;
    }
    if (!isset($profileContent['companyHomePage'])) {
      $profileContent['companyHomePage'] = NULL;
    }
    if (!isset($profileContent['companyEmail'])) {
      $profileContent['companyEmail'] = NULL;
    }
    if (!isset($profileContent['businessPurpose'])) {
      $profileContent['businessPurpose'] = NULL;
    }
    if (!isset($profileContent['practisesBusiness'])) {
      $profileContent['practisesBusiness'] = NULL;
    }

    if (!isset($profileContent['addresses'])) {
      $profileContent['addresses'] = [];
    }
    if (!isset($profileContent['officials'])) {
      $profileContent['officials'] = [];
    }
    if (!isset($profileContent['bankAccounts'])) {
      $profileContent['bankAccounts'] = [];
    }

    return $profileContent;

  }

  /**
   * Get "content" array from document in ATV.
   *
   * @param mixed $business
   *   Business id OR full business object.
   * @param bool $refetch
   *   If true, data is fetched always.
   *
   * @return array
   *   Content
   *
   * @throws \GuzzleHttp\Exception\GuzzleException
   */
  public function getGrantsProfileContent(
    mixed $business,
    bool $refetch = FALSE
  ): array {

    if (is_array($business)) {
      $businessId = $business['identifier'];
    }
    else {
      $businessId = $business;
    }

    if ($businessId == NULL) {
      return [];
    }

    if ($refetch === FALSE && $this->isCached($businessId)) {
      $profileData = $this->getFromCache($businessId);
      return $profileData->getContent();
    }

    $profileData = $this->getGrantsProfile($businessId, $refetch);

    if ($profileData == NULL) {
      return [];
    }

    return $profileData->getContent();

  }

  /**
   * Get "content" array from document in ATV.
   *
   * @param string $businessId
   *   What business data is fetched.
   * @param bool $refetch
   *   If true, data is fetched always.
   *
   * @return array
   *   Content
   */
  public function getGrantsProfileAttachments(
    string $businessId,
    bool $refetch = FALSE
  ): array {

    if ($refetch === FALSE && $this->isCached($businessId)) {
      $profileData = $this->getFromCache($businessId);
      return $profileData->getAttachments();
    }
    else {
      $profileData = $this->getGrantsProfile($businessId, $refetch);
    }

    return $profileData->getAttachments();

  }

  /**
   * Get profile Document.
   *
   * @param string $businessId
   *   Business id for profile.
   * @param bool $refetch
   *   Force refetching of the data.
   *
   * @return \Drupal\helfi_atv\AtvDocument|null
   *   Profiledata
   *
   * @throws \GuzzleHttp\Exception\GuzzleException
   */
  public function getGrantsProfile(
    string $businessId,
    bool $refetch = FALSE
  ): AtvDocument|null {
    if ($refetch === FALSE) {
      if ($this->isCached($businessId)) {
        $document = $this->getFromCache($businessId);
        return $document;
      }
    }

    // Get profile document from ATV.
    try {
      $profileDocument = $this->getGrantsProfileFromAtv($businessId, $refetch);
      if ($profileDocument) {
        $this->setToCache($businessId, $profileDocument);
        return $profileDocument;
      }
    }
    catch (AtvDocumentNotFoundException $e) {
      return NULL;
    }

    return NULL;
  }

  /**
   * Get profile data from ATV.
   *
   * @param string $businessId
   *   Id to be fetched.
   * @param bool $refetch
   *   Force refetching and bypass caching.
   *
   * @return \Drupal\helfi_atv\AtvDocument|bool
   *   Profile data
   *
   * @throws \Drupal\helfi_atv\AtvDocumentNotFoundException
   * @throws \GuzzleHttp\Exception\GuzzleException
   */
  private function getGrantsProfileFromAtv(string $businessId, $refetch = FALSE): AtvDocument|bool {

    $searchParams = [
      'business_id' => $businessId,
      'type' => 'grants_profile',
      'lookfor' => 'appenv:' . ApplicationHandler::getAppEnv(),
    ];

    try {
      $searchDocuments = $this->atvService->searchDocuments($searchParams, $refetch);
    }
    catch (\Exception $e) {
      throw new AtvDocumentNotFoundException('Not found');
    }

    if (empty($searchDocuments)) {
      return FALSE;
    }
    // @todo merge profiles if multiple is saved for some reason.
    return reset($searchDocuments);
  }

  /**
   * Get selected company id.
   *
   * @return array|null
   *   Selected company
   */
  public function getSelectedCompany(): ?array {
    if ($this->isCached('selected_company')) {
      return $this->getFromCache('selected_company');
    }
    return NULL;
  }

  /**
   * Set selected business id to store.
   *
   * @param array $companyData
   *   Company details.
   *
   * @return bool
   *   Success.
   */
  public function setSelectedCompany(array $companyData): bool {
    return $this->setToCache('selected_company', $companyData);
  }

  /**
   * Get selected company id.
   *
   * @return string|null
   *   Selected company
   */
  public function getApplicantType(): ?string {
    if ($this->isCached('applicant_type')) {
      $data = $this->getFromCache('applicant_type');
      return $data['selected_type'];
    }
    return '';
  }

  /**
   * Set selected business id to store.
   *
   * @param string $selected_type
   *   Type to be saved.
   */
  public function setApplicantType(string $selected_type): bool {
    return $this->setToCache('applicant_type', ['selected_type' => $selected_type]);
  }

  /**
   * Whether or not we have made this query?
   *
   * @param string $key
   *   Used key for caching.
   *
   * @return bool
   *   Is this cached?
   */
  public function clearCache($key = ''): bool {
    $session = $this->requestStack->getCurrentRequest()->getSession();
    try {
      // $session->clear();
      return TRUE;
    }
    catch (\Exception $e) {
      return FALSE;
    }
  }

  /**
   * Whether or not we have made this query?
   *
   * @param string|null $key
   *   Used key for caching.
   *
   * @return bool
   *   Is this cached?
   */
  private function isCached(?string $key): bool {
    $session = $this->requestStack->getCurrentRequest()->getSession();

    $cacheData = $session->get($key);
    return !is_null($cacheData);
  }

  /**
   * Get item from cache.
   *
   * @param string $key
   *   Key to fetch from tempstore.
   *
   * @return array|\Drupal\helfi_atv\AtvDocument|null
   *   Data in cache or null
   */
  private function getFromCache(string $key): array|AtvDocument|null {
    $session = $this->requestStack->getCurrentRequest()->getSession();
    $retval = !empty($session->get($key)) ? $session->get($key) : NULL;
    return $retval;
  }

  /**
   * Add item to cache.
   *
   * @param string $key
   *   Used key for caching.
   * @param array|\Drupal\helfi_atv\AtvDocument $data
   *   Cached data.
   *
   * @return bool
   *   Did save succeed?
   *
   * @throws \GuzzleHttp\Exception\GuzzleException
   */
  private function setToCache(string $key, array|AtvDocument $data): bool {

    $session = $this->requestStack->getCurrentRequest()->getSession();

    if (gettype($data) == 'object') {
      $session->set($key, $data);
      return TRUE;
    }

    if (
      isset($data['content']) ||
      $key == 'selected_company' ||
      $key == 'applicant_type'
    ) {
      $session->set($key, $data);
      return TRUE;
    }
    else {
      $grantsProfile = $this->getGrantsProfile($key);
      $grantsProfile->setContent($data);
      $session->set($key, $grantsProfile);
      return TRUE;
    }

  }

}
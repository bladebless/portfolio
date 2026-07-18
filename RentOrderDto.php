<?php

namespace CBit\Avtokran\Main\Internals\Exchange\DTO;

use Bitrix\Highloadblock\HighloadBlockTable;
use Bitrix\Main\Type\Contract\Arrayable;
use Bitrix\Main\Type\Contract\Jsonable;
use Bitrix\Main\Type\DateTime;
use Bitrix\Main\Web\Json;
use CBit\Avtokran\Main\Internals\UserType\HlbToPayloadMap;
use CBit\Avtokran\Main\Internals\Utils;
use CBit\Avtokran\Main\Module\Configuration;
use CBit\Avtokran\Main\Service\CompanyService;
use CBit\Avtokran\Main\Service\ConstructionSiteService;

class RentOrderDto implements Jsonable, Arrayable
{
  public static $sDateFormat = "Y-m-d H:i:s";

  readonly public ?string $id;
  readonly public ?string $title;
  readonly public ?string $xmlId;
  readonly public ?bool $isApproved;
  readonly public ?DateTime $createdDate;
  readonly public ?DateTime $fromDate;
  readonly public ?DateTime $toDate;

  readonly public ?array $constructionSite;
  readonly public ?string $region;
  readonly public ?string $deliveryMethod;
  readonly public ?array $rentEquipment;
  readonly public ?array $ownEquipment;
  readonly public ?array $company;

  readonly public ?array $contacts;
  readonly public ?bool $isPassRequired;
  readonly public ?array $passContact;
  readonly public ?array $engineers;
  readonly public ?array $workers;
  readonly public ?array $work;
  readonly public ?bool $isPowerLinesExists;
  readonly public ?array $contract;
  readonly public ?array $maintenance;
  readonly public ?array $signatureFile;
  readonly public ?array $scanFile;
  readonly public ?array $signers;

  /**
   * @param  array  $arFields
   * @throws \Bitrix\Main\ObjectException
   * @throws \InvalidArgumentException
   */
  public function __construct(array $arFields)
  {

    if (empty($arFields)) {
      throw new \InvalidArgumentException("Invalid fields");
    }

    $this->id = isset($arFields['ID']) ? (string) $arFields['ID'] : null;
    $this->title = isset($arFields['TITLE']) ? (string) $arFields['TITLE'] : null;
    $this->xmlId = isset($arFields['XML_ID']) ? (string) $arFields['XML_ID'] : null;


    $this->createdDate = !empty($arFields['CREATED_DATE'])
        ? new DateTime(Utils::normalizeDate($arFields['CREATED_DATE']), Utils::$sDateFormat)
        : null;

    $this->fromDate = !empty($arFields['FROM_DATE'])
        ? new DateTime(Utils::normalizeDate($arFields['FROM_DATE']), Utils::$sDateFormat)
        : null;

    $this->toDate = !empty($arFields['TO_DATE'])
        ? new DateTime(Utils::normalizeDate($arFields['TO_DATE']), Utils::$sDateFormat)
        : null;

    $this->constructionSite = isset($arFields['CONSTRUCTION_SITE']) ? (array) $arFields['CONSTRUCTION_SITE'] : [];
    $this->region = isset($arFields['REGION']) ? (string) $arFields['REGION'] : null;
    $this->deliveryMethod = isset($arFields['DELIVERY_METHOD']) ? (string) $arFields['DELIVERY_METHOD'] : null;

    $this->work = isset($arFields['WORK']) ? (array) $arFields['WORK'] : null;

    $this->isPassRequired = isset($arFields['IS_PASS_REQUIRED']) && 'Y' == $arFields['IS_PASS_REQUIRED'];
    $this->isPowerLinesExists = isset($arFields['IS_POWER_LINES_EXISTS']) && 'Y' == $arFields['IS_POWER_LINES_EXISTS'];

    $this->isApproved = isset($arFields['IS_APPROVED']) && 'Y' == $arFields['IS_APPROVED'];

    $arOwnEquip = [];
    if (is_array($arFields['OWN_EQUIPMENT']) && !empty($arFields['OWN_EQUIPMENT'])) {
      foreach ($arFields['OWN_EQUIPMENT'] as $arEquip) {
        $arOwnEquip[] = [
            'TYPE' => $arEquip['TYPE'] ?? '',// XML_ID | GUID
            'SPEC' => $arEquip['SPEC'] ?? '',
        ];
      }
    }

    $arRentEquip = [];
    if (is_array($arFields['RENT_EQUIPMENT']) && !empty($arFields['RENT_EQUIPMENT'])) {
      foreach ($arFields['RENT_EQUIPMENT'] as $arEquip) {
        $fromDate = !empty($arEquip['FROM_DATE'])
            ? new DateTime(Utils::normalizeDate($arEquip['FROM_DATE']), Utils::$sDateFormat)
            : null;

        $toDate = !empty($arEquip['TO_DATE'])
            ? new DateTime(Utils::normalizeDate($arEquip['TO_DATE']), Utils::$sDateFormat)
            : null;

        $arRentEquip[] = [
            'MODEL'     => $arEquip['MODEL'] ?? '',
            'QUANTITY'  => $arEquip['QUANTITY'] ?? 0,
            'FROM_DATE' => $fromDate,
            'TO_DATE'   => $toDate,
        ];
      }
    }

    $this->contacts = isset($arFields['CONTACTS']) ? (array) $arFields['CONTACTS'] : [];
    $this->engineers = isset($arFields['ENGINEERS']) ? (array) $arFields['ENGINEERS'] : [];
    $this->workers = isset($arFields['WORKERS']) ? (array) $arFields['WORKERS'] : [];
    $this->ownEquipment = $arOwnEquip;
    $this->rentEquipment = $arRentEquip;
    $this->passContact = isset($arFields['PASS_PROVIDER_CONTACT']) ? (array) $arFields['PASS_PROVIDER_CONTACT'] : [];

    if (!empty($arFields['CONTRACT']['DATE'])) {
      $arFields['CONTRACT']['DATE'] = Utils::normalizeDate($arFields['CONTRACT']['DATE']);
    }
    $this->contract = isset($arFields['CONTRACT']) ? (array) $arFields['CONTRACT'] : [];

    $this->company = isset($arFields['COMPANY']) ? (array) $arFields['COMPANY'] : [];

    $this->maintenance = [
        'TYPE' => $arFields['MAINTENANCE']['TYPE'] ?? '',
        'ID'   => $arFields['MAINTENANCE']['ID'] ?? '',
        'DATE' => $arFields['MAINTENANCE']['DATE'] ? Utils::normalizeDate($arFields['MAINTENANCE']['DATE']) : '',
    ];

    $this->signatureFile = isset($arFields['SIGNATURE_FILE']) ? (array) $arFields['SIGNATURE_FILE'] : null;
    $this->scanFile = isset($arFields['SCAN_FILE']) ? (array) $arFields['SCAN_FILE'] : null;
    $this->signers = [
        'ACCOUNTANT_FULL_NAME' => $arFields['SIGNERS']['ACCOUNTANT_FULL_NAME'] ?? '',
        'HEAD_FULL_NAME'       => $arFields['SIGNERS']['HEAD_FULL_NAME'] ?? '',
    ];

  }

  public function toJson($options = 0): string
  {

    $arCompany = [
        'guid'               => $this->company['XML_ID'] ?? '',
        'title'              => $this->company['NAME'] ?? '',
        'inn'                => $this->company['INN'] ?? '',
        'phone'              => $this->company['PHONE'] ?? '',
        'email'              => $this->company['EMAIL'] ?? '',
        //--------------
        'headFullName'       => $this->company['HEAD_FULL_NAME'] ?? '',
        'accountantFullName' => $this->company['ACCOUNTANT_FULL_NAME'] ?? '',
    ];

    $arSigners = [
        'headFullName'       => $this->signers['HEAD_FULL_NAME'] ?? '',
        'accountantFullName' => $this->signers['ACCOUNTANT_FULL_NAME'] ?? '',
    ];

    $arContacts = [];
    foreach ($this->contacts as $contact) {
      $arContacts[] = [
          'guidkm'   => $contact['GUID'] ?? '',
          'fullName' => $contact['FULL_NAME'] ?? '',
          'phone'    => $contact['PHONE'] ?? '',
          'email'    => $contact['EMAIL'] ?? '',
      ];
    }

    $arPassContact = [
        'fullName' => $this->passContact['FULL_NAME'] ?? '',
        'phone'    => $this->passContact['PHONE'] ?? '',
        'email'    => $this->passContact['EMAIL'] ?? '',
    ];

    $arRentEquip = [];
    foreach ($this->rentEquipment as $equipment) {
      $fromDate = is_a($equipment['FROM_DATE'], DateTime::class)
          ? $equipment['FROM_DATE']->format(static::$sDateFormat) : null;
      $toDate = is_a($equipment['TO_DATE'], DateTime::class)
          ? $equipment['TO_DATE']->format(static::$sDateFormat) : null;

      $arRentEquip[] = [
          'model'    => $equipment['MODEL'] ?? '',
          'quantity' => $equipment['QUANTITY'] ?? 0,
          'toDate'   => $toDate,
          'fromDate' => $fromDate,
      ];
    }

    $arOwnEquip = [];
    foreach ($this->ownEquipment as $equipment) {
      $arOwnEquip[] = [
          'type'     => $equipment['TYPE'] ?? '',
          'techSpec' => $equipment['SPEC'] ?? '',
      ];
    }

    $arLiftOperatorWorkers = [];
    if (isset($this->workers['LIFT_OPERATOR_WORKERS']) && is_array($this->workers['LIFT_OPERATOR_WORKERS'])) {
      foreach ((array) $this->workers['LIFT_OPERATOR_WORKERS'] as $workerCert) {
        $arLiftOperatorWorkers[] = [
            'fullName'    => $workerCert['FULL_NAME'] ?? '',
            'certificate' => $workerCert['CERT'] ?? '',
        ];
      }
    }

    $arCradleWorkers = [];
    if (isset($this->workers['CRADLE_WORKERS']) && is_array($this->workers['CRADLE_WORKERS'])) {
      foreach ((array) $this->workers['CRADLE_WORKERS'] as $workerCert) {
        $arCradleWorkers[] = [
            'fullName'    => $workerCert['FULL_NAME'] ?? '',
            'certificate' => $workerCert['CERT'] ?? '',
        ];
      }
    }

    $arSignatureFile = $this->signatureFile ?? [];
    $arScanFile = $this->scanFile ?? [];

    $arPayload = [
        'guid'               => $this->xmlId,
        'id'                 => $this->id,
        'title'              => $this->title,
        'isApproved'         => $this->isApproved,
        'createdDate'        => ($this->createdDate instanceof DateTime)
            ? $this->createdDate->format(static::$sDateFormat)
            : null,
        'fromDate'           => ($this->fromDate instanceof DateTime)
            ? $this->fromDate->format(static::$sDateFormat)
            : null,
        'toDate'             => ($this->toDate instanceof DateTime)
            ? $this->toDate->format(static::$sDateFormat)
            : null,
        'description'        => $this->work['DESCRIPTION'] ?? '',
        'constructionSite'   => $this->constructionSite['XML_ID'],
        'company'            => $arCompany,
        'contacts'           => $arContacts,
        'contract'           => $this->contract['XML_ID'],
        'maintenance'        => [
            'type' => $this->maintenance['TYPE'],
            'id'   => $this->maintenance['ID'],
            'date' => $this->maintenance['DATE'],
        ],
        'equipment'          => $arRentEquip,
        'engineers'          => [
            'respProductionCtrl'  => [
                'fullName'    => $this->engineers['RESP_PRODUCTION_CTRL']['FULL_NAME'] ?? '',
                'certificate' => $this->engineers['RESP_PRODUCTION_CTRL']['CERT'] ?? '',
            ],
            'respMaintaining'     => [
                'fullName'    => $this->engineers['RESP_MAINTAINING']['FULL_NAME'] ?? '',
                'certificate' => $this->engineers['RESP_MAINTAINING']['CERT'] ?? '',
            ],
            'respSafePerformance' => [
                'fullName'    => $this->engineers['RESP_SAFE_PERFORMANCE']['FULL_NAME'] ?? '',
                'certificate' => $this->engineers['RESP_SAFE_PERFORMANCE']['CERT'] ?? '',
            ],
        ],
        'workers'            => [
            'liftOperatorWorkers' => $arLiftOperatorWorkers,
            'cradleWorkers'       => $arCradleWorkers,
        ],
        'isPassRequired'     => $this->isPassRequired,
        'passProvider'       => [
          // 'useMainContact' => false,
          'passContact' => $arPassContact,
        ],
        'isPowerLinesExists' => $this->isPowerLinesExists,
        'ownEquipment'       => $arOwnEquip,
        'signatureFile'      => $arSignatureFile,
        'scanFile'           => $arScanFile,
        'region'             => $this->region ?? '',// GUID | XML_ID
        'deliverymetod'     => $this->deliveryMethod,
        'signers'            => $arSigners,
    ];

    return Json::encode($arPayload);
  }

  public function toArray(): array
  {
    $arRentEquip = [];
    foreach ($this->rentEquipment as $equipment) {
      $fromDate = is_a($equipment['FROM_DATE'], DateTime::class)
          ? $equipment['FROM_DATE']->format(static::$sDateFormat) : null;
      $toDate = is_a($equipment['TO_DATE'], DateTime::class)
          ? $equipment['TO_DATE']->format(static::$sDateFormat) : null;

      $arRentEquip[] = [
          'MODEL'     => $equipment['MODEL'] ?? '',
          'QUANTITY'  => $equipment['QUANTITY'] ?? 0,
          'TO_DATE'   => $toDate,
          'FROM_DATE' => $fromDate,
      ];
    }
    $arSignatureFile = $this->signatureFile ?? [
      // TODO: //
    ];
    $arScanFile = $this->scanFile ?? [
      // TODO: //
    ];

    return [
        'ID'                    => $this->id,
        'TITLE'                 => $this->title,
        'XML_ID'                => $this->xmlId,
        'COMPANY'               => $this->company,
        'RENT_EQUIPMENT'        => $arRentEquip,
        'WORK'                  => $this->work,
        'MAINTENANCE'           => $this->maintenance,
        'CONSTRUCTION_SITE'     => $this->constructionSite,// array
        'REGION'                => $this->region,// GUID | XML_ID
        'CONTACTS'              => $this->contacts,
        'ENGINEERS'             => $this->engineers,
        'WORKERS'               => $this->workers,
        'IS_PASS_REQUIRED'      => $this->isPassRequired ? 'Y' : 'N',
        'PASS_PROVIDER_CONTACT' => $this->passContact,
        'IS_POWER_LINES_EXISTS' => $this->isPowerLinesExists ? 'Y' : 'N',
        'OWN_EQUIPMENT'         => $this->ownEquipment,
        'CONTRACT'              => $this->contract,//array
        'IS_APPROVED'           => $this->isApproved ? 'Y' : 'N',
        'CREATED_DATE'          => is_a($this->createdDate, DateTime::class)
            ? $this->createdDate->format(static::$sDateFormat)
            : null,
        'FROM_DATE'             => is_a($this->fromDate, DateTime::class)
            ? $this->fromDate->format(static::$sDateFormat)
            : null,
        'TO_DATE'               => is_a($this->toDate, DateTime::class)
            ? $this->toDate->format(static::$sDateFormat)
            : null,
        'SIGNATURE_FILE'        => $arSignatureFile,
        'SCAN_FILE'             => $arScanFile,
        'SIGNERS'               => $this->signers,
    ];
  }

  // ==========================================================
  public static function fromArray(array $arFields): static
  {
    $dto = new static($arFields);

    return $dto;
  }

  /**
   * @param  string  $sJson
   * @return static
   * @throws \InvalidArgumentException
   */
  public static function fromJson(string $sJson): static
  {
    try {
      $arJsonFields = Json::decode($sJson);
    } //
    catch (\Throwable $e) {
      throw new \InvalidArgumentException("Invalid json string");
    }

    $arJsonCompany = $arJsonFields['company'] ?? [];
    $arCompany = [
        'ID'                   => $arJsonCompany['id'] ?? '',
        'XML_ID'               => $arJsonCompany['guid'] ?? '',
        'NAME'                 => $arJsonCompany['title'] ?? '',
        'INN'                  => $arJsonCompany['inn'] ?? '',
        'PHONE'                => $arJsonCompany['phone'] ?? '',
        'EMAIL'                => $arJsonCompany['email'] ?? '',
        'HEAD_FULL_NAME'       => $arJsonCompany['headFullName'] ?? '',
        'ACCOUNTANT_FULL_NAME' => $arJsonCompany['accountantFullName'] ?? '',
    ];

    $sConSiteXmlId = is_array($arJsonFields['constructionSite']) ?
        $arJsonFields['constructionSite']['guid'] ?? '' :
        $arJsonFields['constructionSite'] ?? '';

    $arConSite = [
        'ID'     => is_array($arJsonFields['constructionSite']) ? $arJsonFields['constructionSite']['id'] ?? 0 : 0,
        'XML_ID' => $sConSiteXmlId,
        'NAME'   => is_array($arJsonFields['constructionSite']) ? $arJsonFields['constructionSite']['name'] ?? '' : '',
    ];

    if (!empty($sConSiteXmlId)) {
      $arConSiteList = ConstructionSiteService::getInstance()->getList(['=XML_ID' => $sConSiteXmlId]);
      if (0 < count($arConSiteList)) {
        $arConSite = array_intersect_key(current($arConSiteList), array_flip(['ID', 'XML_ID', 'NAME']));
      }
      unset($arConSiteList);
    }

    $sContractXmlId = is_array($arJsonFields['contract']) ? $arJsonFields['contract']['guid'] ?? '' : $arJsonFields['contract'] ?? '';

    $arContract = [
        'ID'     => 0,
        'XML_ID' => $sContractXmlId,
        'NAME'   => is_array($arJsonFields['contract']) ? $arJsonFields['contract']['name'] ?? '' : '',
        'NUMBER' => is_array($arJsonFields['contract']) ? $arJsonFields['contract']['number'] ?? 0 : 0,
        'DATE'   => is_array($arJsonFields['contract']) ? $arJsonFields['contract']['name'] ?? '' : '',
    ];
    if (!empty($sContractXmlId)) {
      $arContractList = CompanyService::getInstance()->getContractList(['=XML_ID' => $sContractXmlId]);
      if (0 < count($arContractList)) {
        $arContractItem = current($arContractList);
        $arContract = [
            'ID'     => $arContractItem['ID'],
            'XML_ID' => $arContractItem['XML_ID'],
            'NAME'   => $arContractItem['NAME'],
            'NUMBER' => $arContractItem['PROPS']['NUMBER']['VALUE'] ?? '',
            'DATE'   => $arContractItem['PROPS']['DATE']['VALUE'] ?? '',
        ];
        unset($arContractItem);
      }
      unset($arContractList);
    }



    $arSigners = [
        'HEAD_FULL_NAME'       => $arJsonFields['signers']['headFullName'] ?: $arCompany['HEAD_FULL_NAME'],
        'ACCOUNTANT_FULL_NAME' => $arJsonFields['signers']['accountantFullName'] ?: $arCompany['ACCOUNTANT_FULL_NAME'],
    ];

    $arContacts = [];
    foreach ((array) $arJsonFields['contacts'] as $contact) {
      $arContacts[] = [
          'ID'        => $contact['id'] ?? '',
          'FULL_NAME' => $contact['fullName'] ?? '',
          'PHONE'     => $contact['phone'] ?? '',
          'EMAIL'     => $contact['email'] ?? '',
      ];
    }

    $arPassContact = [
        'FULL_NAME' => $arJsonFields['passProvider']['passContact']['fullName'] ?? '',
        'PHONE'     => $arJsonFields['passProvider']['passContact']['phone'] ?? '',
        'EMAIL'     => $arJsonFields['passProvider']['passContact']['email'] ?? '',
    ];

    $arRentEquip = [];
    foreach ($arJsonFields['equipment'] as $equipment) {
      $arRentEquip[] = [
          'MODEL'     => $equipment['model'] ?? '',
          'QUANTITY'  => $equipment['quantity'] ?? 0,
          'FROM_DATE' => $equipment['fromDate'] ?? null,
          'TO_DATE'   => $equipment['toDate'] ?? null,
      ];
    }

    $arOwnEquip = [];
    foreach ($arJsonFields['ownEquipment'] as $equipment) {
      $arOwnEquip[] = [
          'TYPE' => $equipment['type'] ?? '',// GUID 1C = XML_ID
          'SPEC' => $equipment['techSpec'] ?? '',
      ];
    }

    $arLiftOperatorWorkers = [];
    if (isset($arJsonFields['workers']['liftOperatorWorkers']) && is_array($arJsonFields['workers']['liftOperatorWorkers'])) {
      foreach ((array) $arJsonFields['workers']['liftOperatorWorkers'] as $workerCert) {
        $arLiftOperatorWorkers[] = [
            'FULL_NAME' => $workerCert['fullName'] ?? '',
            'CERT'      => $workerCert['certificate'] ?? '',
        ];
      }
    }

    $arCradleWorkers = [];
    if (isset($arJsonFields['workers']['cradleWorkers']) && is_array($arJsonFields['workers']['cradleWorkers'])) {
      foreach ((array) $arJsonFields['workers']['cradleWorkers'] as $workerCert) {
        $arCradleWorkers[] = [
            'FULL_NAME' => $workerCert['fullName'] ?? '',
            'CERT'      => $workerCert['certificate'] ?? '',
        ];
      }
    }

    $arSignatureFile = $arJsonFields['signatureFile'] ?? [];
    $arScanFile = $arJsonFields['scanFile'] ?? [];

    $arFields = [
        'ID'                    => $arJsonFields['id'] ?? '',
        'TITLE'                 => $arJsonFields['title'] ?? sprintf("RentOrder#%s", $arJsonFields['guid'] ?? '-'),
        'XML_ID'                => $arJsonFields['guid'] ?? '',
        'COMPANY'               => $arCompany,
        'RENT_EQUIPMENT'        => $arRentEquip,
        'WORK'                  => [
            'TYPE'        => $arJsonFields['work']['type'] ?? '',// GUID | XML_ID
            'DESCRIPTION' => $arJsonFields['work']['description'] ?? $arJsonFields['description'] ?? '',
        ],
        'MAINTENANCE'           => [
            'TYPE' => $arJsonFields['maintenance']['type'] ?? '',// GUID | XML_ID
            'ID'   => $arJsonFields['maintenance']['id'] ?? '',
            'DATE' => $arJsonFields['maintenance']['date'] ?? '',
        ],
        'CONSTRUCTION_SITE'     => $arConSite,
        'REGION'                => $arJsonFields['region'] ?? '',// GUID | XML_ID
        'DELIVERY_METHOD'       => $arJsonFields['deliverymetod'] ?? '',
        'CONTACTS'              => $arContacts,
        'ENGINEERS'             => [
            'RESP_PRODUCTION_CTRL'  => [
                'FULL_NAME' => $arJsonFields['engineers']['respProductionCtrl']['fullName'] ?? '',
                'CERT'      => $arJsonFields['engineers']['respProductionCtrl']['certificate'] ?? '',
            ],
            'RESP_MAINTAINING'      => [
                'FULL_NAME' => $arJsonFields['engineers']['respMaintaining']['fullName'] ?? '',
                'CERT'      => $arJsonFields['engineers']['respMaintaining']['certificate'] ?? '',
            ],
            'RESP_SAFE_PERFORMANCE' => [
                'FULL_NAME' => $arJsonFields['engineers']['respSafePerformance']['fullName'] ?? '',
                'CERT'      => $arJsonFields['engineers']['respSafePerformance']['certificate'] ?? '',
            ],
        ],
        'WORKERS'               => [
            'LIFT_OPERATOR_WORKERS' => $arLiftOperatorWorkers,
            'CRADLE_WORKERS'        => $arCradleWorkers,
        ],
        'IS_PASS_REQUIRED'      => isset($arJsonFields['isPassRequired']) && (bool) $arJsonFields['isPassRequired'] ? 'Y' : 'N',
        'PASS_PROVIDER_CONTACT' => $arPassContact,
        'IS_POWER_LINES_EXISTS' => isset($arJsonFields['isPowerLinesExists']) && (bool) $arJsonFields['isPowerLinesExists'] ? 'Y' : 'N',
        'OWN_EQUIPMENT'         => $arOwnEquip,
        'IS_CONTRACT_EXISTS'    => isset($arJsonFields['isContractExists']) && (bool) $arJsonFields['isContractExists'] ? 'Y' : 'N',
        'CONTRACT'              => $arContract,
        'SIGNATURE_FILE'        => $arSignatureFile,
        'SCAN_FILE'             => $arScanFile,
        'SIGNERS'               => $arSigners,
        'IS_APPROVED'           => isset($arJsonFields['isApproved']) && (bool) $arJsonFields['isApproved'] ? 'Y' : 'N',
        'CREATED_DATE'          => $arJsonFields['createdDate'] ?? '',
        'FROM_DATE'             => $arJsonFields['fromDate'] ?? '',
        'TO_DATE'               => $arJsonFields['toDate'] ?? '',

    ];

    return static::fromArray($arFields);
  }

  public static function fromIblockElement(array $arElement = []): ?static
  {
    if (!empty($arElement)) {
      try {
        $arRespProductionCtrl = !empty($arElement['PROPS']['ENGINEER_RESP_PRODUCTION_CTRL']['~VALUE'])
            ? Json::decode($arElement['PROPS']['ENGINEER_RESP_PRODUCTION_CTRL']['~VALUE'])
            : [];
      } catch (\Throwable $e) {
        $arRespProductionCtrl = [];
      }

      try {
        $arRespMaintaining = !empty($arElement['PROPS']['ENGINEER_RESP_MAINTAINING']['~VALUE'])
            ? Json::decode($arElement['PROPS']['ENGINEER_RESP_MAINTAINING']['~VALUE'])
            : [];
      } catch (\Throwable $e) {
        $arRespMaintaining = [];
      }

      try {
        $arRespSafePerformance = !empty($arElement['PROPS']['ENGINEER_RESP_SAFE_PERFORMANCE']['~VALUE'])
            ? Json::decode($arElement['PROPS']['ENGINEER_RESP_SAFE_PERFORMANCE']['~VALUE'])
            : [];
      } catch (\Throwable $e) {
        $arRespSafePerformance = [];
      }

      try {
        $arPassContact = !empty($arElement['PROPS']['PASS_PROVIDER_CONTACT']['~VALUE'])
            ? Json::decode($arElement['PROPS']['PASS_PROVIDER_CONTACT']['~VALUE'])
            : [];
      } catch (\Throwable $e) {
        $arPassContact = [];
      }


      $arCompany = [];
      if (!empty($arElement['COMPANY'])) {
        $arCompany = [
            'ID'                   => $arElement['COMPANY']['ID'] ?? '',
            'XML_ID'               => $arElement['COMPANY']['XML_ID'] ?? '',
            'NAME'                 => $arElement['COMPANY']['NAME'] ?? '',
            'INN'                  => $arElement['COMPANY']['INN'] ?? '',
            'PHONE'                => $arElement['COMPANY']['PHONE'] ?? '',
            'EMAIL'                => $arElement['COMPANY']['EMAIL'] ?? '',
            //--------------------
            'HEAD_FULL_NAME'       => $arElement['COMPANY']['HEAD_FULL_NAME'] ?? '',
            'ACCOUNTANT_FULL_NAME' => $arElement['COMPANY']['ACCOUNTANT_FULL_NAME'] ?? '',
        ];
      }


      $arConSite = [];
      if (!empty($arElement['CONSTRUCTION_SITE'])) {
        $arConSite = [
            'ID'     => $arElement['CONSTRUCTION_SITE']['ID'] ?? '',
            'XML_ID' => $arElement['CONSTRUCTION_SITE']['XML_ID'] ?? '',
            'NAME'   => $arElement['CONSTRUCTION_SITE']['NAME'] ?? '',
        ];
      }

      $arContract = [];
      if (!empty($arElement['CONTRACT'])) {
          $arContract = [
              'ID'     => $arElement['CONTRACT']['ID'] ?? '',
              'XML_ID' => $arElement['CONTRACT']['XML_ID'] ?? '',
              'NAME'   => $arElement['CONTRACT']['NAME'] ?? '',
              'NUMBER' => $arElement['CONTRACT']['NUMBER'] ?? '',
              'DATE'   => $arElement['CONTRACT']['DATE'] ?? '',
          ];

      }

      $arSigners = [
          'HEAD_FULL_NAME'       => $arElement['PROPS']['HEAD_FULL_NAME']['~VALUE'] ?: $arCompany['HEAD_FULL_NAME'],
          'ACCOUNTANT_FULL_NAME' => $arElement['PROPS']['ACCOUNTANT_FULL_NAME']['~VALUE'] ?: $arCompany['ACCOUNTANT_FULL_NAME'],
      ];

      $arContacts = [];
      foreach ((array) $arElement['PROPS']['CONTACTS']['~VALUE'] as $sJson) {
        try {
          $arJson = Json::decode($sJson);
          $arContacts[] = $arJson;
        } catch (\Throwable $e) {
        }

      }

      $contactIds = array_filter(array_map('intval', array_column($arContacts, 'ID')));
      if (!empty($contactIds)) {
          $contactsDbResult = \CIBlockElement::GetList(
              ['ID' => 'ASC'],
              [
                  'IBLOCK_ID' => Configuration::getInstance()->getContactPersonIBlockId(),
                  'ID' => $contactIds,
              ],
              false,
              ['nTopCount' => count($contactIds)],
              ['ID', 'XML_ID', 'NAME']
          );
          $contactsGUIDMap = [];
          while ($contactDb = $contactsDbResult->Fetch()) {
              $contactsGUIDMap[$contactDb['ID']] = $contactDb;
          }
          foreach ($arContacts as &$arContact) {
              if (array_key_exists($arContact['ID'], $contactsGUIDMap)) {
                  $arContact['GUID'] = $contactsGUIDMap[$arContact['ID']]['XML_ID'];
                  $arContact['FULL_NAME'] = $contactsGUIDMap[$arContact['ID']]['NAME'];
              }
          }
      }

      $arLiftOperatorWorkers = [];
      if (isset($arElement['PROPS']['LIFT_OPERATOR_WORKERS']['~VALUE'])) {
        foreach ((array) $arElement['PROPS']['LIFT_OPERATOR_WORKERS']['~VALUE'] as $sJson) {
          try {
            $arJson = Json::decode($sJson);
            $arLiftOperatorWorkers[] = $arJson;
          } catch (\Throwable $e) {
          }

        }
      }

      $arCradleWorkers = [];
      if (isset($arElement['PROPS']['CRADLE_WORKERS']['~VALUE'])) {
        foreach ((array) $arElement['PROPS']['CRADLE_WORKERS']['~VALUE'] as $sJson) {
          try {
            $arJson = Json::decode($sJson);
            $arCradleWorkers[] = $arJson;
          } catch (\Throwable $e) {
          }
        }
      }

      $arRentEquip = [];
      if (isset($arElement['RENT_EQUIPMENT']) && !empty($arElement['RENT_EQUIPMENT'])) {
        foreach ($arElement['RENT_EQUIPMENT'] as $arItem) {
          $arRentEquip[] = [
              'MODEL'     => $arItem['EQUIPMENT_XML_ID'] ?? $arItem['EQUIPMENT_ID'] ?? '',
              'QUANTITY'  => $arItem['QUANTITY'] ?? 0,
              'FROM_DATE' => $arItem['DATE_FROM'] ?? null,
              'TO_DATE'   => $arItem['DATE_TO'] ?? null,
          ];
        }
      }

      $arOwnEquip = [];
      if (isset($arElement['PROPS']['OWN_EQUIPMENT']['~VALUE'])) {
        if (HlbToPayloadMap::USER_TYPE == $arElement['PROPS']['OWN_EQUIPMENT']['USER_TYPE']) {
          $arEquipMap = static::getHlbElements($arElement['PROPS']['OWN_EQUIPMENT']['USER_TYPE_SETTINGS']['HLB_ID']);
          foreach ((array) $arElement['PROPS']['OWN_EQUIPMENT']['~VALUE'] as $sJson) {
            try {
              $arJson = Json::decode($sJson);
              $arOwnEquip[] = [
                  'TYPE' => $arJson['ITEM_ID'] ? $arEquipMap[$arJson['ITEM_ID']] : '',//ID-> GUID | XML_ID
                  'SPEC' => $arJson['PAYLOAD'] ?? '',
              ];
            } catch (\Throwable $e) {
              //
            }
          }
        }
      }

      $arSignatureFile = \CFile::MakeFileArray($arElement['PROPS']['SIGNATURE_FILE']['~VALUE'] ?? '');
      $arScanFile = \CFile::MakeFileArray($arElement['PROPS']['SCAN_FILE']['~VALUE'] ?? '');

      $arFields = [
          'ID'             => $arElement['ID'] ?? '',
          'TITLE'          => $arElement['NAME'] ?? '',
          'XML_ID'         => $arElement['XML_ID'] ?? '',
          'COMPANY'        => $arCompany,
          'RENT_EQUIPMENT' => $arRentEquip,
          'WORK'           => [
              'TYPE'        => $arElement['PROPS']['WORK_TYPE']['VALUE_XML_ID'] ?? '',// GUID | XML_ID
              'DESCRIPTION' => $arElement['PROPS']['WORK_DESCRIPTION']['~VALUE'] ?? '',
          ],

          'MAINTENANCE'           => [
              'TYPE' => $arElement['PROPS']['MAINTENANCE_TYPE']['VALUE_XML_ID'] ?? '',// GUID | XML_ID
              'ID'   => $arElement['PROPS']['MAINTENANCE_ID']['~VALUE'] ?? '',
              'DATE' => $arElement['PROPS']['MAINTENANCE_DATE']['~VALUE'] ?? '',
          ],
          'REGION'                => $arElement['PROPS']['REGION']['VALUE_XML_ID'] ?? '',// GUID | XML_ID
          'DELIVERY_METHOD'       => $arElement['PROPS']['DELIVERY_METHOD']['~VALUE'] ?? '',
          'CONSTRUCTION_SITE'     => $arConSite,
          'CONTACTS'              => $arContacts,
          'ENGINEERS'             => [
              'RESP_PRODUCTION_CTRL'  => $arRespProductionCtrl ?? [],
              'RESP_MAINTAINING'      => $arRespMaintaining ?? [],
              'RESP_SAFE_PERFORMANCE' => $arRespSafePerformance ?? [],
          ],
          'WORKERS'               => [
              'LIFT_OPERATOR_WORKERS' => $arLiftOperatorWorkers,
              'CRADLE_WORKERS'        => $arCradleWorkers,
          ],
          'IS_PASS_REQUIRED'      => 'Y' == $arElement['PROPS']['IS_PASS_REQUIRED']['~VALUE'] ?? false,
          'PASS_PROVIDER_CONTACT' => $arPassContact,
          'IS_POWER_LINES_EXISTS' => 'Y' == $arElement['PROPS']['IS_POWER_LINES_EXISTS']['~VALUE'] ?? false,
          'OWN_EQUIPMENT'         => $arOwnEquip,
          'IS_CONTRACT_EXISTS'    => 'Y' == $arElement['PROPS']['IS_CONTRACT_EXISTS']['~VALUE'] ?? false,
          'CONTRACT'              => $arContract,
          'IS_APPROVED'           => 'Y' == $arElement['PROPS']['IS_APPROVED']['~VALUE'] ?? false,
          'CREATED_DATE'          => $arElement['DATE_CREATE'] ?? '',
          'FROM_DATE'             => $arElement['PROPS']['FROM_DATE']['~VALUE'] ?? '',
          'TO_DATE'               => $arElement['PROPS']['TO_DATE']['~VALUE'] ?? '',
          'SIGNATURE_FILE' => $arSignatureFile,
          'SCAN_FILE'      => $arScanFile,
          'SIGNERS'        => $arSigners,
      ];

      return static::fromArray($arFields);
    }

    return null;
  }


  protected static function getHlbElements(int $iHlbId): array
  {
    static $arCache = [];

    if (0 >= $iHlbId) {
      return [];
    }

    if (!isset($arCache[$iHlbId])) {
      $arCache[$iHlbId] = [];

      $hlblock = HighloadBlockTable::compileEntity($iHlbId);

      $ormClass = $hlblock->getDataClass();

      $iterator = $ormClass::getList([
          'select' => ['ID', 'UF_XML_ID'],
          'filter' => ['!UF_XML_ID' => [null, false, '']],
      ]);
      while ($row = $iterator->fetch()) {
        $arCache[$iHlbId][$row['ID']] = $row['UF_XML_ID'];
      }
      unset ($row, $iterator);
    }


    return $arCache[$iHlbId];
  }

}

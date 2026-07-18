<?php

namespace CBit\Avtokran\Main\Internals\Exchange\DTO;

use Bitrix\Main\ArgumentException;
use Bitrix\Main\Security\Random;
use Bitrix\Main\Type\Contract\Arrayable;
use Bitrix\Main\Type\Contract\Jsonable;
use Bitrix\Main\Type\DateTime;
use Bitrix\Main\Web\Json;
use CBit\Avtokran\Main\Internals\Utils;
use CBit\Avtokran\Main\Module\Configuration;
use CBit\Avtokran\Main\Service\RentRegistryService;
use CBit\Avtokran\Main\Service\RentRenewalService;

class RentRenewalDto implements Jsonable, Arrayable
{
  readonly public ?string $id;
  readonly public ?string $name;
  readonly public ?string $xmlId;

  readonly public ?string $stage; // waiting | approved | declined
  readonly public ?DateTime $toDate;
  readonly public ?string $registryId; // (RENT_REGISTRY_ITEM_ID) ID элемента Иб "Реестр объектов в аренде"
  //
  readonly public ?string $rentOrder;// GUID документа основания записи в регистре 1С
  readonly public ?string $equipment;// GUID объекта аренды
  readonly public ?string $comment;

  public function __construct(array $arFields)
  {
    if (empty($arFields)) {
      throw new \InvalidArgumentException("Invalid fields");
    }

    $this->id = $arFields['ID'] ?? 0;
    $this->name = $arFields['NAME'] ?: $arFields['TITLE'] ?: "#NONAME#";
    $this->xmlId = $arFields['XML_ID'] ?: $arFields['GUID'] ?: '';

    $this->stage = $arFields['STAGE'] ?? Configuration::getInstance()->getRentRegistryRenewalOrdersStageMap()['W'];
    $this->toDate = !empty($arFields['DATE_TO'])
        ? new DateTime(Utils::normalizeDate($arFields['DATE_TO']), Utils::$sDateFormat)
        : null;
    $this->registryId = $arFields['REGISTRY_ITEM_ID'] ?? 0;

    // equipment + rentOrder это поля которые может выдать 1С ( т.к. у записей реестра нет GUID ) 
    // XML_ID запись в битриксе и хранится в поле registryId DTO
    $this->equipment = $arFields['EQUIPMENT'] ?? null;
    $this->comment = $arFields['COMMENT'] ?? null;
    $this->rentOrder = $arFields['RENT_ORDER'] ?? null;
  }

  public function toJson($options = 0): string
  {
    $arFields = [
        'guid'       => $this->xmlId,
        'id'         => $this->id,
        'name'       => $this->name,
        'stage'      => $this->stage,
        'toDate'     => ($this->toDate instanceof DateTime) ? $this->toDate->format(Utils::$sDateFormat) : null,
        'registryId' => $this->registryId,
        'comment'    => $this->comment,

        'rentOrder'  => $this->rentOrder,
        'equipment'  => $this->equipment,
    ];

    return Json::encode($arFields);
  }

  public function toArray(): array
  {
    return [
        'ID'               => $this->id,
        'NAME'             => $this->name,
        'XML_ID'           => $this->xmlId,
        'STAGE'            => $this->stage,
        'DATE_TO'          => is_a($this->toDate, DateTime::class)
            ? $this->toDate->format(Utils::$sDateFormat)
            : null,
        'REGISTRY_ITEM_ID' => $this->registryId,
        'COMMENT'          => $this->comment,
        'RENT_ORDER'       => $this->rentOrder,
        'EQUIPMENT'        => $this->equipment,
    ];
  }


  public static function fromArray(array $arFields): static
  {
    $dto = new static($arFields);

    return $dto;
  }

  public static function fromJson(string $sJson): static
  {
    try {
      $arJsonFields = Json::decode($sJson);
    } //
    catch (ArgumentException $e) {
      throw new \InvalidArgumentException("Invalid json string");
    }

    $sDefaultName = sprintf("#NONAME#%s", $arJsonFields['xmlId'] ?? $arJsonFields['guid'] ?? '');

    //region обратная совместимость с предыдущей версией
    if (empty($arJsonFields['stage'])) {
      $arStageMap = Configuration::getInstance()->getRentRegistryRenewalOrdersStageMap();
      $arJsonFields['stage'] = $arStageMap['W'];

      if ((bool) $arJsonFields['isDeclined']) {
        $arJsonFields['stage'] = $arStageMap['D'];
      } elseif ((bool) $arJsonFields['isApproved']) {
        $arJsonFields['stage'] = $arStageMap['A'];
      }
    }

    $arFields = [
        'ID'               => $arJsonFields['id'] ?? 0,
        'XML_ID'           => $arJsonFields['xmlId'] ?? $arJsonFields['guid'] ?? '',
        'NAME'             => $arJsonFields['name'] ?? $arJsonFields['title'] ?? $sDefaultName,
        'DATE_TO'          => $arJsonFields['toDate'] ?? null,
        'REGISTRY_ITEM_ID' => $arJsonFields['registryId'] ?? 0,
        'STAGE'            => $arJsonFields['stage'] ?? null,
        'COMMENT'          => $arJsonFields['comment'] ?? null,
        //
        'RENT_ORDER'       => $arJsonFields['rentOrder'] ?? '', //GUID документа основания записи в регистре 1С
        'EQUIPMENT'        => $arJsonFields['equipment'] ?? '',
    ];

    if (0 >= $arFields['REGISTRY_ITEM_ID'] && !empty($arFields['XML_ID'])) {
      $arRenewalOrder = current(RentRenewalService::getList(['=XML_ID' => $arFields['XML_ID']]));
      $arFields['REGISTRY_ITEM_ID'] = $arRenewalOrder['REGISTRY_ITEM_ID'] ?? 0;
      unset($arRenewalOrder);
    }


    if (0 >= $arFields['REGISTRY_ITEM_ID'] && !empty($arFields['RENT_ORDER']) && !empty($arFields['EQUIPMENT'])) {
      // пробуем добыть ID  из ИБ реестра
      $iRentRegistryOrdersIblockId = Configuration::getInstance()->getRentRegistryOrdersIblockId();
      if (0 < $iRentRegistryOrdersIblockId) {
        $oIterator = \CIBlockElement::GetList(
            ['ID' => 'ASC'],
            [
                '=IBLOCK_ID'                    => $iRentRegistryOrdersIblockId,
                '=PROPERTY_ORDER_GUID'          => $arFields['RENT_ORDER'],
                '=PROPERTY_EQUIPMENT_ID.XML_ID' => $arFields['EQUIPMENT'],
            ],
            false,
            false,
            [
                'ID', 'IBLOCK_ID', 'XML_ID',
                'PROPERTY_COMPANY_ID',
                'PROPERTY_ORDER_GUID',
                'PROPERTY_EQUIPMENT_ID',
                'PROPERTY_EQUIPMENT_ID.XML_ID',
            ]
        );

        if ($arRentRegistryOrder = $oIterator->GetNext()) {
          $arFields['REGISTRY_ITEM_ID'] = $arRentRegistryOrder['ID'];
        }
        unset($arRentRegistryOrder, $oIterator);
      }
    }

    return static::fromArray($arFields);
  }

  public static function fromIblockById(int $iItemId): ?static
  {

    if (0 >= $iItemId) {
      return null;
    }

    $arElements = RentRenewalService::getList(['=ID' => $iItemId]);
    if (!empty($arElements)) {
      $arItem = current($arElements);
      if (!empty($arItem)) {
        $arFields = [
            'ID'               => $arItem['ID'],
            'XML_ID'           => $arItem['XML_ID'],
            'NAME'             => $arItem['NAME'],
            'IS_APPROVED'      => $arItem['IS_APPROVED'],
            'RENT_ORDER'       => $arItem['RENT_ORDER'],
            'EQUIPMENT'        => $arItem['EQUIPMENT'],
            'DATE_TO'          => $arItem['DATE_TO'],
            'REGISTRY_ITEM_ID' => $arItem['REGISTRY_ITEM_ID'],
            'COMMENT'          => $arItem['COMMENT'] ?? null,

        ];

        return static::fromArray($arFields);
      }
    }

    return null;
  }

}

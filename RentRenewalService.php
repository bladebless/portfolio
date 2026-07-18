<?php

namespace CBit\Avtokran\Main\Service;

use Bitrix\Main\Error;
use Bitrix\Main\ObjectNotFoundException;
use Bitrix\Main\Result;
use CBit\Avtokran\Main\Internals\Exchange\DTO\RentRenewalDto;
use CBit\Avtokran\Main\Module\Configuration;
use CBit\Core\Internals\BaseSingleton;

class RentRenewalService extends BaseSingleton
{
  public static $sDateFormat = "Y-m-d H:i:s";

  protected Configuration $oConfig;

  public function __construct()
  {
    parent::__construct();
    $this->oConfig = Configuration::getInstance();
  }

  public static function getDtoByElementId(int $iItemId): ?RentRenewalDto
  {
    if (0 >= $iItemId) {
      throw new \InvalidArgumentException("Invalid itemId");
    }

    return RentRenewalDto::fromIblockById($iItemId);
  }

  public static function getList(array $arFilter = []): array
  {
    $iRentRegistryRenewalOrdersIblockId = Configuration::getInstance()->getRentRegistryRenewalOrdersIblockId();
    $iRentRegistryCatalogIblockId = Configuration::getInstance()->getRentRegistryCatalogIblockId();

    if (0 >= $iRentRegistryRenewalOrdersIblockId) {
      throw new ObjectNotFoundException('Invalid RentRegistryRenewalOrdersIblockId');
    }

    if (0 >= $iRentRegistryCatalogIblockId) {
      throw new ObjectNotFoundException('Invalid RentRegistryCatalogIblockId');
    }

    $oIterator = \CIBlockElement::GetList(
        ['ID' => 'ASC'],
        array_merge($arFilter, ['=IBLOCK_ID' => $iRentRegistryRenewalOrdersIblockId]),
        false,
        false,
        [
            'ID', 'IBLOCK_ID', 'NAME', 'XML_ID', 'ACTIVE',
            'PROPERTY_RENT_REGISTRY_ITEM_ID',
            'PROPERTY_RENT_REGISTRY_ITEM_ID.PROPERTY_EQUIPMENT_ID',
            'PROPERTY_RENT_REGISTRY_ITEM_ID.PROPERTY_ORDER_GUID',
            'PROPERTY_STAGE',
            'PROPERTY_DATE_TO',
            'PROPERTY_COMMENT',
        ]
    );

    $arResult = [];
    while ($arElement = $oIterator->GetNext()) {

      $arElement['EQUIPMENT_XML_ID'] = '';;// GUID | XML_ID
      $iEquipmentId = $arElement['PROPERTY_RENT_REGISTRY_ITEM_ID_PROPERTY_EQUIPMENT_ID_VALUE'] ?? 0;
      if (0 < (int) $iEquipmentId && 0 < (int) $iRentRegistryCatalogIblockId) {
        $oCatalogIterator = \CIBlockElement::GetList(
            ['ID' => 'ASC'],
            [
                '=IBLOCK_ID' => $iRentRegistryCatalogIblockId,
                '=ID'        => $iEquipmentId,

            ],
            false,
            false,
            [
                'ID', 'IBLOCK_ID', 'NAME', 'XML_ID',
            ]
        );

        if ($arCatElement = $oCatalogIterator->GetNext()) {
          $arElement['EQUIPMENT_XML_ID'] = $arCatElement['XML_ID'];
        }
        unset($arCatElement, $oCatalogIterator);
      }

      $arResult[] = [
          'ID'               => $arElement['ID'],
          'XML_ID'           => $arElement['XML_ID'],
          'NAME'             => $arElement['NAME'],
          'ACTIVE'           => $arElement['ACTIVE'],
          //
          'STAGE'            => $arElement['PROPERTY_STAGE_VALUE'],
          'DATE_TO'          => $arElement['PROPERTY_DATE_TO_VALUE'],
          'COMMENT'          => $arElement['PROPERTY_COMMENT_VALUE'] ?? '',
          'RENT_ORDER'       => $arElement['PROPERTY_RENT_REGISTRY_ITEM_ID_PROPERTY_ORDER_GUID_VALUE'],
          'EQUIPMENT'        => $arElement['EQUIPMENT_XML_ID'],
          'REGISTRY_ITEM_ID' => $arElement['PROPERTY_RENT_REGISTRY_ITEM_ID_VALUE'],
      ];
    }

    return $arResult;
  }

  public function upsetByDto(RentRenewalDto $oDto): Result
  {

    $oResult = new Result();

    $iRentRegistryRenewalOrdersIblockId = $this->oConfig->getRentRegistryRenewalOrdersIblockId();
    if (0 >= $iRentRegistryRenewalOrdersIblockId) {
      throw new ObjectNotFoundException('Invalid RentRegistryRenewalOrdersIblockId');
    }

    // Добавляем/Обновляем
    $arDto = $oDto->toArray();

    if (!empty($arDto) && is_array($arDto)) {

      if (!empty($arDto['XML_ID']) || !empty($arDto['ID'])) {
        $oIblockEl = new \CIBlockElement();

        $arRenewalOrder = \CIBlockElement::GetList(
            ['ID' => 'ASC'],
            [
                '=IBLOCK_ID' => $iRentRegistryRenewalOrdersIblockId,
                [
                    'LOGIC' => 'OR',
                    ['=XML_ID' => $arDto['XML_ID'] ?? ''],
                    ['=ID' => $arDto['ID'] ?? 0],
                ],
            ],
            false, false,
            ['ID', 'IBLOCK_ID', 'XML_ID'])->GetNext();

        $iId = $arRenewalOrder['ID'] ?? 0;
        unset($arRenewalOrder);

        $bIsNew = 0 >= (int) $iId;

        //
        if ($bIsNew) {
          if (!empty($arDto['REGISTRY_ITEM_ID']) && 0 < (int) $arDto['REGISTRY_ITEM_ID']) {

            $iId = $oIblockEl->Add([
                'NAME'            => $arDto['NAME'],
                'XML_ID'          => $arDto['XML_ID'],
                'IBLOCK_ID'       => $iRentRegistryRenewalOrdersIblockId,
                'PROPERTY_VALUES' => [
                    'DATE_TO'               => $arDto['DATE_TO'],
                    'STAGE'                 => $arDto['STAGE'],
                    'RENT_REGISTRY_ITEM_ID' => $arDto['REGISTRY_ITEM_ID'],
                    'COMMENT'               => $arDto['COMMENT'] ?? '',
                ],
            ]);

            if ($iId) {
              $oResult->setData(['ID' => $iId]);
            } else {
              $oResult->addError(new Error($oIblockEl->LAST_ERROR));
            }

          } else {
            $oResult->addError(
                new Error("Не указан ID элемента реестра объектов в аренде: REGISTRY_ITEM_ID")
            );
          }

        } //
        else {
          $bIsUpdated = $oIblockEl->Update($iId, [
              'NAME'   => $arDto['NAME'],
              'XML_ID' => $arDto['XML_ID'],
          ]);

          if ($bIsUpdated) {
            \CIBlockElement::SetPropertyValuesEx($iId, $iRentRegistryRenewalOrdersIblockId,
                array_merge([],
                    !empty($arDto['DATE_TO']) ? ['DATE_TO' => $arDto['DATE_TO']] : [],
                    !empty($arDto['STAGE']) ? ['STAGE' => $arDto['STAGE']] : [],
                    !empty($arDto['REGISTRY_ITEM_ID']) ? ['RENT_REGISTRY_ITEM_ID' => $arDto['REGISTRY_ITEM_ID']] : [],
                    !empty($arDto['COMMENT']) ? ['COMMENT' => $arDto['COMMENT']] : [],
                )
            );
            $oResult->setData(['ID' => $iId]);

          } else {
            $oResult->addError(new Error($oIblockEl->LAST_ERROR));
          }
        }
      } //
      else {
        $oResult->addError(new Error("Не заполнен GUID у запроса на прологнацию"));
      }
    } //
    else {
      $oResult->addError(new Error("Не заполенены данные запроса на пролонгацию"));
    }


    return $oResult;
  }

}

<?php

declare(strict_types=1);

namespace Kommandhub\DemoData\Installer;

use Kommandhub\DemoData\Util\DemoDataConstants;
use Shopware\Core\Content\Category\CategoryDefinition;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\System\CustomField\CustomFieldTypes;

/**
 * Creates the custom field set that marks generated demo data.
 *
 * Every method is idempotent: the plugin bootstrap runs them on both install
 * and update, so a second run must be a no-op rather than a duplicate.
 *
 * Field names are global across the installation — always prefix them with the
 * plugin slug. Keep the canonical names in one constants class so a rename
 * cannot silently orphan stored data.
 */
class CustomFieldsInstaller
{
    private const CUSTOM_FIELDSET_NAME = DemoDataConstants::CUSTOM_FIELD_SET;

    private const CUSTOM_FIELDSET = [
        'name' => self::CUSTOM_FIELDSET_NAME,
        'config' => [
            'label' => [
                'en-GB' => 'Kommandhub Demo Data',
                'de-DE' => 'Kommandhub Demodaten',
                'fr-FR' => 'Données de démonstration Kommandhub',
                Defaults::LANGUAGE_SYSTEM => 'Kommandhub Demo Data',
            ],
        ],
        'customFields' => [
            [
                'name' => DemoDataConstants::FIELD_SOURCE_KEY,
                'type' => CustomFieldTypes::TEXT,
                'config' => [
                    'label' => [
                        'en-GB' => 'Demo data source key',
                        'de-DE' => 'Demodaten-Quellschlüssel',
                        'fr-FR' => 'Clé source des données de démonstration',
                        Defaults::LANGUAGE_SYSTEM => 'Demo data source key',
                    ],
                    'customFieldPosition' => 1,
                ],
            ],
            [
                'name' => DemoDataConstants::FIELD_GENERATED,
                'type' => CustomFieldTypes::BOOL,
                'config' => [
                    'label' => [
                        'en-GB' => 'Generated demo data',
                        'de-DE' => 'Generierte Demodaten',
                        'fr-FR' => 'Données de démonstration générées',
                        Defaults::LANGUAGE_SYSTEM => 'Generated demo data',
                    ],
                    'customFieldPosition' => 2,
                ],
            ],
            [
                'name' => DemoDataConstants::FIELD_CHANNEL_KEY,
                'type' => CustomFieldTypes::TEXT,
                'config' => [
                    'label' => [
                        'en-GB' => 'Demo sales channel key',
                        'de-DE' => 'Demo-Verkaufskanalschlüssel',
                        'fr-FR' => 'Clé du canal de vente de démonstration',
                        Defaults::LANGUAGE_SYSTEM => 'Demo sales channel key',
                    ],
                    'customFieldPosition' => 3,
                ],
            ],
        ],
    ];

    /**
     * Entities the field set is attached to.
     */
    private const RELATED_ENTITIES = [
        ProductDefinition::ENTITY_NAME,
        CategoryDefinition::ENTITY_NAME,
    ];

    public function __construct(
        private readonly EntityRepository $customFieldSetRepository,
        private readonly EntityRepository $customFieldSetRelationRepository
    ) {
    }

    public function install(Context $context): void
    {
        if ($this->getCustomFieldSetIds($context) !== []) {
            return;
        }

        $this->customFieldSetRepository->upsert([self::CUSTOM_FIELDSET], $context);
    }

    public function addRelations(Context $context): void
    {
        $relationsToInsert = [];

        foreach ($this->getCustomFieldSetIds($context) as $customFieldSetId) {
            foreach (self::RELATED_ENTITIES as $entityName) {
                if ($this->relationExists($context, $customFieldSetId, $entityName)) {
                    continue;
                }

                $relationsToInsert[] = [
                    'customFieldSetId' => $customFieldSetId,
                    'entityName' => $entityName,
                ];
            }
        }

        if ($relationsToInsert === []) {
            return;
        }

        $this->customFieldSetRelationRepository->upsert($relationsToInsert, $context);
    }

    /**
     * Only called when the merchant did NOT ask to keep user data — deleting the
     * field set deletes every value stored in it. The generated catalogue itself
     * is never touched: demo products may well have real orders against them by
     * the time somebody uninstalls this.
     */
    public function uninstall(Context $context): void
    {
        $ids = $this->getCustomFieldSetIds($context);

        if ($ids === []) {
            return;
        }

        $this->customFieldSetRepository->delete(
            array_map(static fn (string $id) => ['id' => $id], $ids),
            $context
        );
    }

    /**
     * @return string[]
     */
    private function getCustomFieldSetIds(Context $context): array
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('name', self::CUSTOM_FIELDSET_NAME));

        return $this->customFieldSetRepository->searchIds($criteria, $context)->getIds();
    }

    private function relationExists(Context $context, string $customFieldSetId, string $entityName): bool
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('customFieldSetId', $customFieldSetId));
        $criteria->addFilter(new EqualsFilter('entityName', $entityName));

        return $this->customFieldSetRelationRepository->searchIds($criteria, $context)->getTotal() > 0;
    }
}
